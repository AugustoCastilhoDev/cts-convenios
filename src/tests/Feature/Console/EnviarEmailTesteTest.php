<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EnviarEmailTesteTest extends TestCase
{
    public function test_envia_o_email_de_teste_para_o_destino_informado(): void
    {
        config(['mail.default' => 'array']);

        $this->artisan('email:testar', ['destino' => 'alguem@exemplo.com.br'])
            ->expectsOutputToContain('Mensagem enviada para alguem@exemplo.com.br')
            ->assertSuccessful();

        $mensagens = Mail::mailer('array')->getSymfonyTransport()->messages();

        $this->assertCount(1, $mensagens);
        $this->assertSame('alguem@exemplo.com.br', $mensagens[0]->getEnvelope()->getRecipients()[0]->getAddress());
    }
}
