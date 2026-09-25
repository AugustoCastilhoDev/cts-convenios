<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class SenhaTemporariaTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    private function token(string $email, string $senha): string
    {
        // Depois de um actingAs o guard padrão é "sanctum" e o login usa o "web"; depois do login o usuário
        // fica no guard "web" e o Sanctum trataria a chamada seguinte como sessão. Limpa nos dois lados.
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        $token = $this->postJson('/api/login', ['email' => $email, 'password' => $senha, 'device_name' => 'teste'])
            ->assertOk()
            ->json('token');
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        return $token;
    }

    /** Senha temporária mostrada ao administrador na criação (guardada pelo teste, como ele repassaria). */
    private string $temporaria = '';

    /** Cria um usuário pela API, como o administrador faz na tela. */
    private function criarPelaApi(string $papel = 'gestor_convenios'): User
    {
        $tenant = $this->criarTenant();
        Sanctum::actingAs($this->criarAdmin());

        $resposta = $this->postJson('/api/users', [
            'name' => 'Pessoa Nova',
            'email' => 'nova@exemplo.gov.br',
            'role' => $papel,
            'tenant_id' => $tenant->id,
        ])->assertCreated();

        $this->temporaria = $resposta->json('senha_temporaria');
        $this->app['auth']->forgetGuards();

        return User::findOrFail($resposta->json('data.id'));
    }

    public function test_conta_criada_pelo_admin_nasce_com_senha_temporaria(): void
    {
        $usuario = $this->criarPelaApi();

        $this->assertTrue($usuario->must_change_password);
    }

    public function test_com_senha_temporaria_so_passa_o_perfil_e_a_troca_de_senha(): void
    {
        $usuario = $this->criarPelaApi();
        $token = $this->token($usuario->email, $this->temporaria);

        // Bloqueado: qualquer tela de trabalho responde 403 com o código que o sistema entende.
        foreach (['/api/convenios', '/api/dashboard', '/api/notificacoes'] as $rota) {
            $this->withToken($token)->getJson($rota)
                ->assertForbidden()
                ->assertJsonPath('codigo', 'senha_temporaria');
        }

        // Liberado: ver quem é (com o aviso) e trocar a senha.
        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('must_change_password', true);

        $this->withToken($token)->putJson('/api/me/password', [
            'current_password' => $this->temporaria,
            'password' => 'MinhaSenhaPropria99',
        ])->assertOk();

        // Depois da troca, o mesmo token trabalha normalmente e o aviso some.
        $this->withToken($token)->getJson('/api/convenios')->assertOk();
        $this->withToken($token)->getJson('/api/me')->assertJsonPath('must_change_password', false);
    }

    public function test_a_troca_exige_a_senha_temporaria_e_uma_senha_diferente(): void
    {
        $usuario = $this->criarPelaApi();
        $token = $this->token($usuario->email, $this->temporaria);

        $this->withToken($token)->putJson('/api/me/password', ['current_password' => 'errada-mesmo', 'password' => 'MinhaSenhaPropria99'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->withToken($token)->putJson('/api/me/password', ['current_password' => $this->temporaria, 'password' => $this->temporaria])
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertTrue($usuario->fresh()->must_change_password);
    }

    public function test_sair_continua_funcionando_com_senha_temporaria(): void
    {
        $usuario = $this->criarPelaApi();
        $token = $this->token($usuario->email, $this->temporaria);

        $this->withToken($token)->postJson('/api/logout')->assertOk();
    }

    public function test_admin_que_redefine_a_senha_de_alguem_a_torna_temporaria(): void
    {
        $gestor = $this->criarGestor();
        $this->assertFalse($gestor->must_change_password);

        Sanctum::actingAs($this->criarAdmin());
        $this->postJson("/api/users/{$gestor->id}/redefinir-senha")->assertOk();

        $this->assertTrue($gestor->fresh()->must_change_password);
    }

    public function test_editar_outros_dados_nao_liga_a_senha_temporaria(): void
    {
        $gestor = $this->criarGestor();

        Sanctum::actingAs($this->criarAdmin());
        $this->putJson("/api/users/{$gestor->id}", ['name' => 'Nome Novo'])->assertOk();

        $this->assertFalse($gestor->fresh()->must_change_password);
    }

    public function test_conta_comum_e_o_administrador_criado_pelo_comando_nao_sao_afetados(): void
    {
        $gestor = $this->criarGestor();
        $token = $this->token($gestor->email, 'password');

        $this->withToken($token)->getJson('/api/convenios')->assertOk();
        $this->assertFalse($this->criarAdmin()->must_change_password);
    }
}
