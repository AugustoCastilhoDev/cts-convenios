<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Valida a configuração de e-mail (driver, chave e remetente) antes de o
 * Motor de Alertas depender dela. Mostra o driver e o remetente em uso para
 * evitar o engano de "enviar" pelo driver `log` sem perceber.
 */
#[Signature('email:testar {destino : E-mail que receberá a mensagem de teste}')]
#[Description('Envia um e-mail de teste usando a configuração de e-mail atual')]
class EnviarEmailTeste extends Command
{
    public function handle(): int
    {
        $destino = $this->argument('destino');
        $driver = config('mail.default');
        $remetente = config('mail.from.address');

        $this->line("Driver: {$driver} | Remetente: {$remetente}");

        try {
            Mail::raw(
                'Teste de envio do CTS Convênios. Se você recebeu esta mensagem, o e-mail está configurado corretamente.',
                fn ($mensagem) => $mensagem->to($destino)->subject('Teste de e-mail — CTS Convênios'),
            );
        } catch (Throwable $erro) {
            $this->error("Falha no envio: {$erro->getMessage()}");

            return self::FAILURE;
        }

        $this->info($driver === 'log'
            ? 'Mensagem gravada no log (driver "log"): nada foi enviado de verdade.'
            : "Mensagem enviada para {$destino}.");

        return self::SUCCESS;
    }
}
