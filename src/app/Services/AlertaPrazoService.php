<?php

namespace App\Services;

use App\Enums\TipoPrazo;
use App\Enums\UserRole;
use App\Jobs\EnviarAlertaPrazo;
use App\Mail\AlertaPrazoConvenio;
use App\Models\AlertaPrazo;
use App\Models\Convenio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Motor de Alertas: identifica prazos que cruzaram um marco da régua,
 * registra cada alerta uma única vez e despacha o envio pela fila.
 */
class AlertaPrazoService
{
    /**
     * Valor de marco_dias para o aviso único de "prazo vencido".
     */
    public const MARCO_VENCIDO = -1;

    /**
     * Varredura completa: registra os alertas novos e despacha o envio de
     * todos os pendentes (inclusive os que falharam em execuções anteriores).
     *
     * @return array{criados: int, despachados: int}
     */
    public function processar(?CarbonImmutable $hoje = null): array
    {
        $hoje ??= $this->hoje();

        $criados = 0;
        foreach ($this->identificarNovos($hoje) as $candidato) {
            $criados += $this->registrar($hoje, $candidato) ? 1 : 0;
        }

        return ['criados' => $criados, 'despachados' => $this->despacharPendentes()];
    }

    /**
     * Alertas que ainda não existem, sem gravar nada (base do --dry-run).
     *
     * @return Collection<int, array{convenio: Convenio, tipo: TipoPrazo, marco: int, dias: int}>
     */
    public function identificarNovos(CarbonImmutable $hoje): Collection
    {
        $candidatos = collect();

        foreach (TipoPrazo::cases() as $tipo) {
            $campo = $tipo->campo();

            Convenio::query()
                ->whereIn('status', array_map(fn ($status) => $status->value, $tipo->statusMonitorados()))
                ->whereBetween($campo, [
                    $hoje->subDays(config('alertas.janela_vencido_dias'))->toDateString(),
                    $hoje->addDays(max(config('alertas.marcos')))->toDateString(),
                ])
                ->whereHas('tenant', fn ($query) => $query->where('active', true))
                ->chunkById(200, function ($convenios) use (&$candidatos, $tipo, $campo, $hoje) {
                    foreach ($convenios as $convenio) {
                        $dias = $this->diasRestantes($hoje, $convenio->{$campo});
                        $marco = $this->marcoAplicavel($dias);

                        if ($marco === null || $this->alertaExiste($convenio, $tipo, $marco)) {
                            continue;
                        }

                        $candidatos->push(compact('convenio', 'tipo', 'marco', 'dias'));
                    }
                });
        }

        return $candidatos;
    }

    /**
     * Menor marco da régua que ainda cobre os dias restantes: se o agendador
     * ficou parado e o prazo já passou de um marco, o alerta atrasado sai
     * uma vez, no marco mais próximo. Prazo vencido recente usa MARCO_VENCIDO.
     */
    public function marcoAplicavel(int $dias): ?int
    {
        if ($dias < 0) {
            return $dias >= -config('alertas.janela_vencido_dias') ? self::MARCO_VENCIDO : null;
        }

        $marcos = array_filter(config('alertas.marcos'), fn (int $marco) => $marco >= $dias);

        return $marcos === [] ? null : min($marcos);
    }

    public function diasRestantes(CarbonImmutable $hoje, CarbonInterface $prazo): int
    {
        $data = CarbonImmutable::parse($prazo->toDateString(), $hoje->getTimezone())->startOfDay();

        return (int) $hoje->diffInDays($data, false);
    }

    /**
     * Envia o e-mail do alerta. Chamado pelo job; é seguro repetir (só marca
     * como enviado depois de o envio ser aceito).
     */
    public function enviar(AlertaPrazo $alerta): void
    {
        $convenio = $alerta->convenio;

        if (! $convenio || $this->estaObsoleto($alerta, $convenio)) {
            $alerta->forceFill(['cancelado_em' => now()])->save();

            return;
        }

        $destinatarios = $this->destinatarios($convenio);

        if ($destinatarios === []) {
            Log::warning('Alerta de prazo sem destinatários: permanece pendente.', [
                'alerta_id' => $alerta->id,
                'tenant_id' => $alerta->tenant_id,
            ]);

            return;
        }

        $redirecionado = filled(config('alertas.redirecionar_para'));
        $dias = $this->diasRestantes($this->hoje(), $alerta->data_prazo);

        Mail::to($redirecionado ? [config('alertas.redirecionar_para')] : $destinatarios)
            ->send(new AlertaPrazoConvenio(
                $alerta,
                $dias,
                $redirecionado ? $destinatarios : [],
            ));

        $alerta->forceFill([
            'enviado_em' => now(),
            'destinatarios' => count($destinatarios),
        ])->save();
    }

    /**
     * Gestores e Fiscais ativos da prefeitura dona do convênio.
     *
     * @return array<int, string>
     */
    public function destinatarios(Convenio $convenio): array
    {
        return User::query()
            ->where('tenant_id', $convenio->tenant_id)
            ->where('active', true)
            ->whereIn('role', [UserRole::GestorConvenios->value, UserRole::FiscalControleInterno->value])
            ->pluck('email')
            ->all();
    }

    /**
     * @param  array{convenio: Convenio, tipo: TipoPrazo, marco: int, dias: int}  $candidato
     */
    private function registrar(CarbonImmutable $hoje, array $candidato): bool
    {
        /** @var Convenio $convenio */
        $convenio = $candidato['convenio'];
        $tipo = $candidato['tipo'];

        $alerta = new AlertaPrazo;
        $alerta->forceFill([
            'tenant_id' => $convenio->tenant_id,
            'convenio_id' => $convenio->id,
            'tipo_prazo' => $tipo,
            'marco_dias' => $candidato['marco'],
            'data_prazo' => $convenio->{$tipo->campo()}->toDateString(),
        ]);

        try {
            $alerta->save();
        } catch (UniqueConstraintViolationException) {
            // Outra execução registrou o mesmo alerta primeiro: nada a fazer.
            return false;
        }

        return true;
    }

    private function despacharPendentes(): int
    {
        return AlertaPrazo::query()
            ->whereNull('enviado_em')
            ->whereNull('cancelado_em')
            ->whereHas('convenio', fn ($query) => $query
                ->whereHas('tenant', fn ($tenant) => $tenant->where('active', true)))
            ->pluck('id')
            ->each(fn (string $id) => EnviarAlertaPrazo::dispatch($id))
            ->count();
    }

    private function alertaExiste(Convenio $convenio, TipoPrazo $tipo, int $marco): bool
    {
        return AlertaPrazo::query()
            ->where('convenio_id', $convenio->id)
            ->where('tipo_prazo', $tipo->value)
            ->where('data_prazo', $convenio->{$tipo->campo()}->toDateString())
            ->where('marco_dias', $marco)
            ->exists();
    }

    /**
     * O prazo mudou ou o convênio saiu dos status monitorados depois de o
     * alerta ser registrado: o e-mail estaria desatualizado.
     */
    private function estaObsoleto(AlertaPrazo $alerta, Convenio $convenio): bool
    {
        $tipo = $alerta->tipo_prazo;

        return $convenio->{$tipo->campo()}?->toDateString() !== $alerta->data_prazo->toDateString()
            || ! in_array($convenio->status, $tipo->statusMonitorados(), true);
    }

    private function hoje(): CarbonImmutable
    {
        return CarbonImmutable::today(config('alertas.timezone'));
    }
}
