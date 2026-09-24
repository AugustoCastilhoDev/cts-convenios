<?php

namespace App\Services;

use App\Mail\NovoContatoComercial;
use App\Models\ContatoComercial;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContatoComercialService
{
    /**
     * Grava o pedido e avisa a equipe por e-mail (pela fila: o visitante não
     * espera o envio). Sem destino configurado, o pedido fica só no banco.
     *
     * @param  array<string, mixed>  $dados
     */
    public function registrar(array $dados, ?string $ip): ContatoComercial
    {
        $contato = new ContatoComercial($dados);
        $contato->forceFill(['aceite_em' => now(), 'ip' => $ip])->save();

        $destino = config('contato.destino');

        if (filled($destino)) {
            Mail::to($destino)->queue(new NovoContatoComercial($contato));
        } else {
            Log::warning('Pedido de contato gravado sem aviso por e-mail: CONTATO_DESTINO não configurado.', [
                'contato_id' => $contato->id,
            ]);
        }

        return $contato;
    }
}
