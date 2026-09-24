<?php

namespace App\Services;

use App\Enums\StatusConvenio;
use App\Enums\StatusExecucaoContrato;
use App\Enums\TipoPrazo;
use App\Models\ContratoVinculado;
use App\Models\Convenio;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Indicadores do painel financeiro. Só lê: o escopo de prefeitura vem dos
 * global scopes dos models (Administrador Interno enxerga todas).
 *
 * "Carteira ativa" = convênios que ainda não foram finalizados.
 *
 * Com `$secretaria` (valor do enum, ou "sem_secretaria"), tudo é recalculado só
 * para ela — menos a `regularidade`, que é do município inteiro.
 */
class DashboardService
{
    /**
     * Prazos dentro desta janela (em dias) entram em "prazos críticos"; os já
     * vencidos entram sempre, por serem o risco de inadimplência no CADIN.
     */
    public const JANELA_PRAZOS_DIAS = 90;

    private const LIMITE_PRAZOS_CRITICOS = 10;

    /**
     * @return array<string, mixed>
     */
    public function resumo(?string $secretaria = null, ?CarbonImmutable $hoje = null): array
    {
        $hoje ??= CarbonImmutable::today(config('alertas.timezone'));

        return [
            'secretaria' => $secretaria,
            // Adimplência é do município: não muda com o filtro de secretaria.
            'regularidade' => $this->regularidade($hoje),
            'resumo' => $this->totais($secretaria),
            'por_etapa' => $this->porEtapa($secretaria),
            'prazos_criticos' => $this->prazosCriticos($hoje, $secretaria),
            'contratos_por_execucao' => $this->contratosPorExecucao($secretaria),
        ];
    }

    private function ativos(?string $secretaria): Builder
    {
        return Convenio::query()
            ->where('status', '!=', StatusConvenio::Finalizado)
            ->daSecretaria($secretaria);
    }

    private function contratosDaCarteira(?string $secretaria): Builder
    {
        return ContratoVinculado::query()
            ->whereHas('convenio', fn (Builder $query) => $query
                ->where('status', '!=', StatusConvenio::Finalizado)
                ->daSecretaria($secretaria));
    }

    /**
     * Situação de adimplência do município segundo os prazos cadastrados no CTS: qualquer
     * prazo (vigência ou prestação de contas) já vencido em convênio ainda monitorado é risco.
     * Não é consulta oficial ao CADIN/CAUC/SIAFI.
     *
     * @return array{situacao: string, prazos_vencidos: int, convenios_afetados: int, maior_atraso_dias: int}
     */
    private function regularidade(CarbonImmutable $hoje): array
    {
        $vencidos = 0;
        $convenios = [];
        $maiorAtraso = 0;

        foreach (TipoPrazo::cases() as $tipo) {
            $campo = $tipo->campo();

            Convenio::query()
                ->whereIn('status', $tipo->statusMonitorados())
                ->whereNotNull($campo)
                ->where($campo, '<', $hoje->toDateString())
                ->get(['id', $campo])
                ->each(function (Convenio $convenio) use (&$vencidos, &$convenios, &$maiorAtraso, $campo, $hoje) {
                    $vencidos++;
                    $convenios[$convenio->id] = true;
                    $maiorAtraso = max($maiorAtraso, (int) $convenio->{$campo}->toImmutable()->startOfDay()->diffInDays($hoje));
                });
        }

        return [
            'situacao' => $vencidos > 0 ? 'risco' : 'regular',
            'prazos_vencidos' => $vencidos,
            'convenios_afetados' => count($convenios),
            'maior_atraso_dias' => $maiorAtraso,
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function totais(?string $secretaria): array
    {
        $carteira = $this->ativos($secretaria)
            ->selectRaw('count(*) as quantidade, coalesce(sum(valor_repasse), 0) as repasse, coalesce(sum(valor_contrapartida), 0) as contrapartida')
            ->first();

        $contratado = (float) $this->contratosDaCarteira($secretaria)->sum('valor_contratado');

        $valorTotal = (float) $carteira->repasse + (float) $carteira->contrapartida;

        // Convênios em que os contratos já somam mais do que o valor disponível.
        $comExcesso = $this->ativos($secretaria)
            ->whereRaw('(valor_repasse + valor_contrapartida) < coalesce((
                select sum(c.valor_contratado) from contratos_vinculados c
                where c.convenio_id = convenios.id and c.deleted_at is null
            ), 0)')
            ->count();

        return [
            'convenios_ativos' => (int) $carteira->quantidade,
            'valor_total' => $valorTotal,
            'total_contratado' => $contratado,
            'saldo_disponivel' => $valorTotal - $contratado,
            'percentual_contratado' => $valorTotal > 0 ? round($contratado / $valorTotal * 100, 1) : 0.0,
            'convenios_com_excesso_contratado' => $comExcesso,
        ];
    }

    /**
     * Todas as etapas, na ordem do fluxo, inclusive as vazias (o gráfico não
     * pode "sumir" com uma coluna só porque não há convênios nela).
     *
     * @return array<int, array{status: string, label: string, quantidade: int, valor_total: float}>
     */
    private function porEtapa(?string $secretaria): array
    {
        $agrupado = Convenio::query()
            ->daSecretaria($secretaria)
            ->selectRaw('status, count(*) as quantidade, coalesce(sum(valor_repasse + valor_contrapartida), 0) as valor_total')
            ->groupBy('status')
            ->get()
            ->keyBy(fn ($linha) => $linha->status->value);

        return array_map(fn (StatusConvenio $status) => [
            'status' => $status->value,
            'label' => $status->label(),
            'quantidade' => (int) ($agrupado[$status->value]->quantidade ?? 0),
            'valor_total' => (float) ($agrupado[$status->value]->valor_total ?? 0),
        ], StatusConvenio::cases());
    }

    /**
     * Mesmos prazos e status que o Motor de Alertas monitora, do mais urgente
     * (já vencido) ao mais distante.
     *
     * @return array<int, array<string, mixed>>
     */
    private function prazosCriticos(CarbonImmutable $hoje, ?string $secretaria): array
    {
        $limite = $hoje->addDays(self::JANELA_PRAZOS_DIAS)->toDateString();
        $prazos = collect();

        foreach (TipoPrazo::cases() as $tipo) {
            $campo = $tipo->campo();

            Convenio::query()
                ->daSecretaria($secretaria)
                ->whereIn('status', $tipo->statusMonitorados())
                ->whereNotNull($campo)
                ->where($campo, '<=', $limite)
                ->get()
                ->each(function (Convenio $convenio) use ($prazos, $tipo, $campo, $hoje) {
                    $data = $convenio->{$campo};

                    $prazos->push([
                        'convenio_id' => $convenio->id,
                        'numero_convenio' => $convenio->numero_convenio,
                        'objeto' => $convenio->objeto,
                        'secretaria' => $convenio->secretaria?->value,
                        'tipo_prazo' => $tipo->value,
                        'tipo_prazo_label' => $tipo->label(),
                        'data_prazo' => $data->toDateString(),
                        'dias' => (int) $hoje->diffInDays($data->toImmutable()->startOfDay(), false),
                    ]);
                });
        }

        return $prazos->sortBy('dias')->take(self::LIMITE_PRAZOS_CRITICOS)->values()->all();
    }

    /**
     * @return array<int, array{status: string, label: string, quantidade: int}>
     */
    private function contratosPorExecucao(?string $secretaria): array
    {
        $agrupado = $this->contratosDaCarteira($secretaria)
            ->selectRaw('status_execucao, count(*) as quantidade')
            ->groupBy('status_execucao')
            ->get()
            ->keyBy(fn ($linha) => $linha->status_execucao->value);

        return array_map(fn (StatusExecucaoContrato $status) => [
            'status' => $status->value,
            'label' => $status->label(),
            'quantidade' => (int) ($agrupado[$status->value]->quantidade ?? 0),
        ], StatusExecucaoContrato::cases());
    }
}
