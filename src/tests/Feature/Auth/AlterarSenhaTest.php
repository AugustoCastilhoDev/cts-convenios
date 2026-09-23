<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class AlterarSenhaTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    private function token(string $email, string $senha = 'password'): string
    {
        return $this->postJson('/api/login', ['email' => $email, 'password' => $senha, 'device_name' => 'teste'])
            ->assertOk()
            ->json('token');
    }

    public function test_troca_a_propria_senha_e_desconecta_os_outros_dispositivos(): void
    {
        $admin = $this->criarAdmin();
        $atual = $this->token($admin->email);
        $this->token($admin->email); // segundo dispositivo

        $this->assertSame(2, $admin->tokens()->count());

        // O login acima deixa o usuário no guard "web" deste processo de teste; sem
        // limpar, o Sanctum trataria a chamada seguinte como sessão, não como token.
        $this->app['auth']->forgetGuards();

        $this->withToken($atual)->putJson('/api/me/password', [
            'current_password' => 'password',
            'password' => 'NovaSenha123',
        ])->assertOk();

        // Só o token usado na troca sobrevive.
        $this->assertSame(1, $admin->tokens()->count());
        $this->withToken($atual)->getJson('/api/me')->assertOk();

        // Após uma chamada por token o guard padrão fica em "sanctum"; o login usa o "web".
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'password', 'device_name' => 'x'])
            ->assertUnprocessable();
        $this->token($admin->email, 'NovaSenha123');
    }

    public function test_recusa_senha_atual_errada_senha_fraca_e_senha_igual_a_atual(): void
    {
        $gestor = $this->criarGestor();
        $token = $this->token($gestor->email);

        $this->withToken($token)->putJson('/api/me/password', ['current_password' => 'errada', 'password' => 'NovaSenha123'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->withToken($token)->putJson('/api/me/password', ['current_password' => 'password', 'password' => 'curta'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->withToken($token)->putJson('/api/me/password', ['current_password' => 'password', 'password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_exige_autenticacao(): void
    {
        $this->putJson('/api/me/password', ['current_password' => 'a', 'password' => 'b'])->assertUnauthorized();
    }
}
