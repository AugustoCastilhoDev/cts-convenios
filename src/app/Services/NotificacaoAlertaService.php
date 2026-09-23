<?php

namespace App\Services;

use App\Models\AlertaPrazo;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Sino de alertas do sistema: mostra os mesmos alertas de prazo que o Motor
 * envia por e-mail, sem depender da caixa de entrada. Não gera nada — só lê
 * `alertas_prazo` e guarda quem já leu cada um.
 */
class NotificacaoAlertaService
{
    /** Só alertas gerados nesta janela aparecem; os mais antigos já estão no histórico do convênio. */
    private const JANELA_DIAS = 60;

    private const LIMITE_ITENS = 20;

    /** Quantas linhas recentes examinamos antes de agrupar por prazo. */
    private const LIMITE_BUSCA = 200;

    public function __construct(private readonly AlertaPrazoService $alertas) {}

    /**
     * Cada item ganha `dias`, recalculado para hoje: o alerta de "15 dias" pode
     * ter sido gerado há uma semana, e o texto nunca deve ficar desatualizado.
     *
     * @return array{itens: Collection<int, AlertaPrazo>, nao_lidas: int}
     */
    public function listar(User $usuario): array
    {
        $hoje = CarbonImmutable::today(config('alertas.timezone'));
        $vigentes = $this->vigentes($usuario);

        $itens = $vigentes->take(self::LIMITE_ITENS)->values()->each(
            fn (AlertaPrazo $alerta) => $alerta->setAttribute('dias', $this->alertas->diasRestantes($hoje, $alerta->data_prazo))
        );

        return [
            'itens' => $itens,
            'nao_lidas' => $vigentes->reject(fn (AlertaPrazo $alerta) => $alerta->lida)->count(),
        ];
    }

    public function marcarLida(User $usuario, AlertaPrazo $alerta): void
    {
        $usuario->alertasLidos()->syncWithoutDetaching([$alerta->id => ['lido_em' => now()]]);
    }

    public function marcarTodasLidas(User $usuario): void
    {
        $pendentes = $this->vigentes($usuario)->reject(fn (AlertaPrazo $alerta) => $alerta->lida);

        $usuario->alertasLidos()->syncWithoutDetaching(
            $pendentes->mapWithKeys(fn (AlertaPrazo $alerta) => [$alerta->id => ['lido_em' => now()]])->all()
        );
    }

    /**
     * Um alerta por prazo (o mais recente): quando chega o de 30 dias, o de 60
     * deixa de aparecer. Some também o que já está desatualizado (prazo alterado,
     * convênio fora dos status monitorados ou excluído) — mesma regra do e-mail.
     *
     * @return Collection<int, AlertaPrazo>
     */
    private function vigentes(User $usuario): Collection
    {
        return AlertaPrazo::query()
            ->with('convenio')
            ->whereNull('cancelado_em')
            ->where('created_at', '>=', now()->subDays(self::JANELA_DIAS))
            ->whereHas('convenio')
            ->withExists(['leitores as lida' => fn ($query) => $query->where('users.id', $usuario->id)])
            ->latest('created_at')
            ->latest('id')
            ->limit(self::LIMITE_BUSCA)
            ->get()
            ->reject(fn (AlertaPrazo $alerta) => $this->alertas->estaObsoleto($alerta, $alerta->convenio))
            ->unique(fn (AlertaPrazo $alerta) => $alerta->convenio_id.'|'.$alerta->tipo_prazo->value.'|'.$alerta->data_prazo->toDateString())
            ->values();
    }
}
