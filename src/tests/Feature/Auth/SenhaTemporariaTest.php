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

    public function test_a_senha_temporaria_vale_pelo_prazo_configurado_e_a_resposta_informa_ate_quando(): void
    {
        config(['seguranca.senha_temporaria_dias' => 3]);
        $this->travel(now())->days(0);

        $tenant = $this->criarTenant();
        Sanctum::actingAs($this->criarAdmin());
        $resposta = $this->postJson('/api/users', ['name' => 'Pessoa Nova', 'email' => 'prazo@exemplo.gov.br', 'role' => 'gestor_convenios', 'tenant_id' => $tenant->id])
            ->assertCreated();

        $limite = now()->addDays(3);
        $this->assertEqualsWithDelta($limite->timestamp, User::findOrFail($resposta->json('data.id'))->senha_temporaria_expira_em->timestamp, 5);
        $this->assertNotNull($resposta->json('senha_temporaria_expira_em'));
        $resposta->assertJsonPath('data.senha_temporaria_expirada', false);
    }

    public function test_com_a_senha_temporaria_vencida_o_login_e_recusado_mas_dentro_do_prazo_entra(): void
    {
        $usuario = $this->criarPelaApi();

        // Dentro do prazo.
        $this->travel(6)->days();
        $this->token($usuario->email, $this->temporaria);

        // Vencida (o padrão são 7 dias).
        $this->travel(2)->days();
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->postJson('/api/login', ['email' => $usuario->email, 'password' => $this->temporaria, 'device_name' => 'teste'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonFragment(['email' => [__('A senha temporária venceu. Peça a um administrador para gerar outra ou use "Esqueci minha senha".')]]);
    }

    public function test_senha_errada_de_conta_com_temporaria_vencida_nao_revela_o_vencimento(): void
    {
        $usuario = $this->criarPelaApi();
        $this->travel(8)->days();
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        $this->postJson('/api/login', ['email' => $usuario->email, 'password' => 'chute-qualquer', 'device_name' => 'teste'])
            ->assertUnprocessable()
            ->assertJsonFragment(['email' => [__('As credenciais informadas não conferem.')]]);
    }

    public function test_o_administrador_gera_outra_senha_e_o_prazo_recomeca(): void
    {
        $usuario = $this->criarPelaApi();
        $this->travel(8)->days();
        $this->assertTrue($usuario->fresh()->senhaTemporariaExpirada());

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->criarAdmin());
        $lista = $this->getJson('/api/users?busca=nova@exemplo')->assertOk();
        $lista->assertJsonPath('data.0.senha_temporaria_expirada', true);

        $nova = $this->postJson("/api/users/{$usuario->id}/redefinir-senha")->assertOk()->assertJsonPath('data.senha_temporaria_expirada', false);
        $this->assertFalse($usuario->fresh()->senhaTemporariaExpirada());

        $this->token($usuario->email, $nova->json('senha_temporaria'));
    }

    public function test_quem_esqueceu_a_senha_temporaria_vencida_recupera_pelo_link_do_email(): void
    {
        $usuario = $this->criarPelaApi();
        $this->travel(8)->days();

        $token = app('auth.password.broker')->createToken($usuario);
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/redefinir-senha', ['email' => $usuario->email, 'token' => $token, 'password' => 'MinhaSenhaPropria99', 'password_confirmation' => 'MinhaSenhaPropria99'])
            ->assertOk();

        $usuario->refresh();
        $this->assertFalse($usuario->must_change_password);
        $this->assertNull($usuario->senha_temporaria_expira_em);
        $this->token($usuario->email, 'MinhaSenhaPropria99');
    }

    public function test_trocar_a_senha_apaga_o_prazo(): void
    {
        $usuario = $this->criarPelaApi();
        $token = $this->token($usuario->email, $this->temporaria);

        $this->withToken($token)->putJson('/api/me/password', ['current_password' => $this->temporaria, 'password' => 'MinhaSenhaPropria99'])->assertOk();

        $this->assertNull($usuario->fresh()->senha_temporaria_expira_em);
        $this->assertFalse($usuario->fresh()->senhaTemporariaExpirada());
    }

    public function test_temporaria_sem_prazo_gravado_conta_como_vencida(): void
    {
        $gestor = $this->criarGestor();
        $gestor->forceFill(['must_change_password' => true, 'senha_temporaria_expira_em' => null])->save();

        $this->assertTrue($gestor->senhaTemporariaExpirada());
        $this->assertFalse($this->criarGestor()->senhaTemporariaExpirada());
    }

    public function test_conta_comum_e_o_administrador_criado_pelo_comando_nao_sao_afetados(): void
    {
        $gestor = $this->criarGestor();
        $token = $this->token($gestor->email, 'password');

        $this->withToken($token)->getJson('/api/convenios')->assertOk();
        $this->assertFalse($this->criarAdmin()->must_change_password);
    }
}
