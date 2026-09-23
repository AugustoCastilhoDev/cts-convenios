<?php

namespace App\Jobs;

use App\Models\AlertaPrazo;
use App\Services\AlertaPrazoService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

/**
 * Envia um alerta de prazo. Recebe só o id: relê o registro na hora de
 * enviar, então um alerta cancelado ou já enviado nesse meio-tempo é
 * ignorado. ShouldBeUnique evita dois envios simultâneos do mesmo alerta.
 */
#[Tries(4)]
#[Backoff(60, 300, 900)]
#[Timeout(60)]
class EnviarAlertaPrazo implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $alertaId) {}

    public function uniqueId(): string
    {
        return $this->alertaId;
    }

    public function handle(AlertaPrazoService $alertas): void
    {
        $alerta = AlertaPrazo::find($this->alertaId);

        if ($alerta && ! $alerta->enviado_em && ! $alerta->cancelado_em) {
            $alertas->enviar($alerta);
        }
    }
}
