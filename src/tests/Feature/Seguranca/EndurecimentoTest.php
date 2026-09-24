<?php

namespace Tests\Feature\Seguranca;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class EndurecimentoTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    public function test_login_bloqueia_a_sexta_tentativa_seguida_com_mensagem_clara(): void
    {
        RateLimiter::clear('x');
        $gestor = $this->criarGestor();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => $gestor->email, 'password' => 'errada', 'device_name' => 't'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => $gestor->email, 'password' => 'password', 'device_name' => 't'])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Muitas tentativas de login. Aguarde um minuto e tente novamente.');
    }

    public function test_limite_de_login_e_por_email_um_usuario_nao_trava_o_outro(): void
    {
        $gestor = $this->criarGestor();
        $fiscal = $this->criarFiscal($gestor->tenant);

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/login', ['email' => $gestor->email, 'password' => 'errada', 'device_name' => 't']);
        }

        $this->postJson('/api/login', ['email' => $fiscal->email, 'password' => 'password', 'device_name' => 't'])
            ->assertOk();
    }

    public function test_token_expira_depois_de_doze_horas(): void
    {
        $this->travelTo('2026-09-23 08:00:00');
        $gestor = $this->criarGestor();
        $token = $gestor->createToken('teste')->plainTextToken;

        $this->travelTo('2026-09-23 19:00:00');
        $this->withToken($token)->getJson('/api/me')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->travelTo('2026-09-23 21:00:00');
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_tamanho_de_pagina_tem_teto(): void
    {
        Sanctum::actingAs($this->criarAdmin());

        $this->getJson('/api/audits?por_pagina=100000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 200);

        $this->getJson('/api/audits?por_pagina=0')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1);
    }

    public function test_respostas_levam_cabecalhos_de_seguranca(): void
    {
        $resposta = $this->getJson('/api/me')->assertUnauthorized();

        $resposta->assertHeader('X-Content-Type-Options', 'nosniff');
        $resposta->assertHeader('X-Frame-Options', 'DENY');
        $resposta->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'none'", $resposta->headers->get('Content-Security-Policy'));
        $resposta->assertHeaderMissing('Strict-Transport-Security');

        // Em HTTPS (atrás do proxy) inclui HSTS.
        $this->getJson('/api/me', ['X-Forwarded-Proto' => 'https'])
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_seeder_de_demonstracao_recusa_rodar_em_producao(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(RuntimeException::class);

        // Direto na classe: `db:seed --force` (usado em scripts de deploy) contorna a confirmação do artisan.
        $this->app->make(DatabaseSeeder::class)->run();
    }
}
