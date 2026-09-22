<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    public function test_usuario_consegue_logar_com_credenciais_corretas(): void
    {
        $gestor = $this->criarGestor();

        $this->postJson('/api/login', [
            'email' => $gestor->email,
            'password' => 'password',
            'device_name' => 'teste',
        ])
            ->assertOk()
            ->assertJsonStructure(['user', 'token'])
            ->assertJsonPath('user.email', $gestor->email);
    }

    public function test_login_falha_com_senha_incorreta(): void
    {
        $gestor = $this->criarGestor();

        $this->postJson('/api/login', [
            'email' => $gestor->email,
            'password' => 'senha-errada',
            'device_name' => 'teste',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_me_retorna_o_usuario_autenticado(): void
    {
        $gestor = $this->criarGestor();
        $token = $gestor->createToken('teste')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('email', $gestor->email);
    }

    public function test_logout_revoga_o_token_atual(): void
    {
        $gestor = $this->criarGestor();
        $token = $gestor->createToken('teste')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertOk();

        // O Guard do Sanctum cacheia o usuário resolvido na própria requisição
        // simulada; como o TestCase reaproveita o container entre chamadas
        // dentro do mesmo teste (nunca acontece em produção — cada request
        // real é um processo PHP-FPM novo), é preciso forçar o Guard a
        // reavaliar o token contra o banco. Confirmado manualmente via HTTP
        // real (curl) que a revogação funciona sem esse passo.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_rota_protegida_exige_autenticacao(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }
}
