<?php

namespace App\Console\Commands;

use App\Services\AlertaPrazoService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Varredura diária do Motor de Alertas (agendada em routes/console.php).
 * Com --dry-run só lista o que seria gerado, sem gravar nem enviar.
 */
#[Signature('alertas:processar {--dry-run : Apenas lista os alertas que seriam gerados}')]
#[Description('Verifica os prazos dos convênios e dispara os alertas da régua (90/60/30/15 dias e vencido)')]
class ProcessarAlertasPrazo extends Command
{
    public function handle(AlertaPrazoService $alertas): int
    {
        if ($this->option('dry-run')) {
            $novos = $alertas->identificarNovos(CarbonImmutable::today(config('alertas.timezone')));

            $this->table(
                ['Convênio', 'Prazo', 'Dias', 'Marco'],
                $novos->map(fn (array $item) => [
                    $item['convenio']->numero_convenio,
                    $item['tipo']->label(),
                    $item['dias'],
                    $item['marco'] === AlertaPrazoService::MARCO_VENCIDO ? 'vencido' : $item['marco'],
                ])->all(),
            );
            $this->info("{$novos->count()} alerta(s) seriam gerados (nada foi gravado ou enviado).");

            return self::SUCCESS;
        }

        $resultado = $alertas->processar();

        $this->info("Alertas novos: {$resultado['criados']} | Envios despachados para a fila: {$resultado['despachados']}");

        return self::SUCCESS;
    }
}
