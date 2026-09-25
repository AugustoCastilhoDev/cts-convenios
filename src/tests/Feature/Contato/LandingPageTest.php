<?php

namespace Tests\Feature\Contato;

use App\Mail\NovoContatoComercial;
use App\Models\ContatoComercial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagina_inicial_e_publica_e_indexavel(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('O prazo do convênio não passa em branco.', false)
            ->assertSee('id="form-contato"', false)
            ->assertSee('rel="canonical"', false)
            ->assertDontSee('noindex', false);
    }

    public function test_o_sistema_mora_em_app_e_nao_e_indexado(): void
    {
        $this->get('/app/login')->assertOk()->assertSee('noindex', false);
        $this->get('/app/convenios/qualquer-id')->assertOk()->assertSee('<div id="app">', false);
        $this->get('/app/admin/auditoria')->assertOk();
    }

    public function test_enderecos_antigos_redirecionam_para_o_novo_caminho(): void
    {
        $this->get('/login')->assertRedirect('/app/login');
        $this->get('/convenios')->assertRedirect('/app/convenios');
        $this->get('/convenios/abc-123')->assertRedirect('/app/convenios/abc-123');
        $this->get('/admin/usuarios')->assertRedirect('/app/admin/usuarios');
    }

    public function test_api_nao_e_capturada_pelas_rotas_da_pagina(): void
    {
        $this->getJson('/api/rota-que-nao-existe')->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_paginas_publicas_nao_criam_sessao_nem_enviam_cookies(): void
    {
        foreach (['/', '/privacidade', '/termos', '/app/login'] as $caminho) {
            $resposta = $this->get($caminho)->assertOk();

            $this->assertEmpty($resposta->headers->getCookies(), "{$caminho} não deveria enviar cookies");
        }

        $this->assertDatabaseCount('sessions', 0);
    }

    public function test_robots_barra_o_sistema_e_a_api(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Disallow: /app/', $robots);
        $this->assertStringContainsString('Disallow: /api/', $robots);
    }

    public function test_email_de_aviso_de_contato_renderiza_com_os_dados_e_o_texto_do_visitante_escapado(): void
    {
        $contato = new ContatoComercial([
            'nome' => 'Maria Souza',
            'cargo' => 'Secretária',
            'municipio' => 'Exemplópolis/SP',
            'email' => 'maria@exemplopolis.sp.gov.br',
            'mensagem' => '<script>alert(1)</script> Quero uma demonstração',
        ]);
        $contato->forceFill(['created_at' => now()]);

        $html = (new NovoContatoComercial($contato))->render();

        $this->assertStringContainsString('Maria Souza', $html);
        $this->assertStringContainsString('Exemplópolis/SP', $html);
        $this->assertStringContainsString('Quero uma demonstração', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }
}
