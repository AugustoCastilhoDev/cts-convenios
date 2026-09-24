<?php

namespace Tests\Feature\Contato;

use App\Mail\NovoContatoComercial;
use App\Models\ContatoComercial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContatoComercialTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(array $sobrescreve = []): array
    {
        return array_merge([
            'nome' => 'Maria Souza',
            'cargo' => 'Secretária de Administração',
            'municipio' => 'Exemplópolis/SP',
            'email' => 'maria@exemplopolis.sp.gov.br',
            'telefone' => '(11) 91234-5678',
            'mensagem' => 'Gostaria de uma demonstração.',
            'aceite' => true,
        ], $sobrescreve);
    }

    public function test_grava_o_pedido_e_avisa_a_equipe_por_email(): void
    {
        Mail::fake();
        config(['contato.destino' => 'equipe@example.com']);

        $this->postJson('/api/contato', $this->payload())
            ->assertCreated()
            ->assertJsonPath('message', 'Recebemos seu contato. Retornaremos em breve.');

        $contato = ContatoComercial::firstOrFail();
        $this->assertSame('Exemplópolis/SP', $contato->municipio);
        $this->assertNotNull($contato->aceite_em);
        $this->assertNotNull($contato->ip);

        Mail::assertQueued(NovoContatoComercial::class, fn ($mail) => $mail->hasTo('equipe@example.com')
            && $mail->hasReplyTo('maria@exemplopolis.sp.gov.br'));
    }

    public function test_sem_destino_configurado_o_pedido_e_gravado_sem_email(): void
    {
        Mail::fake();
        config(['contato.destino' => null]);

        $this->postJson('/api/contato', $this->payload())->assertCreated();

        $this->assertDatabaseCount('contatos_comerciais', 1);
        Mail::assertNothingQueued();
    }

    public function test_exige_campos_obrigatorios_e_o_aceite_do_uso_dos_dados(): void
    {
        $this->postJson('/api/contato', $this->payload(['nome' => '']))->assertUnprocessable()->assertJsonValidationErrors('nome');
        $this->postJson('/api/contato', $this->payload(['municipio' => '']))->assertUnprocessable()->assertJsonValidationErrors('municipio');
        $this->postJson('/api/contato', $this->payload(['email' => 'nao-e-email']))->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/contato', $this->payload(['telefone' => 'abc']))->assertUnprocessable()->assertJsonValidationErrors('telefone');
        $this->postJson('/api/contato', $this->payload(['aceite' => false]))->assertUnprocessable()->assertJsonValidationErrors('aceite');

        $this->assertDatabaseCount('contatos_comerciais', 0);
    }

    public function test_campo_armadilha_preenchido_por_robo_nao_grava_nada(): void
    {
        Mail::fake();
        config(['contato.destino' => 'equipe@example.com']);

        $this->postJson('/api/contato', $this->payload(['website' => 'http://spam.example']))->assertCreated();

        $this->assertDatabaseCount('contatos_comerciais', 0);
        Mail::assertNothingQueued();
    }

    public function test_limita_a_cinco_pedidos_por_hora_por_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/contato', $this->payload())->assertCreated();
        }

        $this->postJson('/api/contato', $this->payload())->assertStatus(429);
    }

    public function test_endpoint_e_publico_mas_nao_aceita_campos_fora_da_lista(): void
    {
        $this->postJson('/api/contato', $this->payload(['id' => 'forcado', 'ip' => '1.2.3.4', 'aceite_em' => '2000-01-01']))
            ->assertCreated();

        $contato = ContatoComercial::firstOrFail();
        $this->assertNotSame('forcado', $contato->id);
        $this->assertNotSame('1.2.3.4', $contato->ip);
        $this->assertGreaterThan(2020, $contato->aceite_em->year);
    }
}
