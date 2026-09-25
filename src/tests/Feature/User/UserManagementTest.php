<?php

namespace Tests\Feature\User;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    private function payload(string $tenantId, array $sobrescreve = []): array
    {
        return array_merge([
            'name' => 'Servidor Novo',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'senha12345',
            'role' => UserRole::GestorConvenios->value,
            'tenant_id' => $tenantId,
        ], $sobrescreve);
    }

    public function test_admin_cria_usuario_da_prefeitura_com_papel_e_tenant_definidos_pelo_servidor(): void
    {
        $tenant = $this->criarTenant();
        Sanctum::actingAs($this->criarAdmin());

        $resposta = $this->postJson('/api/users', $this->payload($tenant->id))
            ->assertCreated()
            ->assertJsonPath('data.role', 'gestor_convenios')
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.active', true)
            ->assertJsonMissingPath('data.password');

        $usuario = User::findOrFail($resposta->json('data.id'));
        $this->assertTrue(password_verify('senha12345', $usuario->password));
    }

    public function test_gestor_e_fiscal_nao_gerenciam_usuarios(): void
    {
        $gestor = $this->criarGestor();
        $alvo = $this->criarFiscal($gestor->tenant);

        Sanctum::actingAs($gestor);
        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', $this->payload($gestor->tenant_id))->assertForbidden();
        $this->putJson("/api/users/{$alvo->id}", ['name' => 'X'])->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->criarFiscal());
        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_nao_e_possivel_criar_administrador_interno_pela_api(): void
    {
        $tenant = $this->criarTenant();
        Sanctum::actingAs($this->criarAdmin());

        $this->postJson('/api/users', $this->payload($tenant->id, [
            'role' => UserRole::AdministradorInterno->value,
        ]))->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_validacoes_de_criacao(): void
    {
        $tenant = $this->criarTenant();
        $existente = $this->criarGestor($tenant);
        Sanctum::actingAs($this->criarAdmin());

        $this->postJson('/api/users', $this->payload($tenant->id, ['email' => $existente->email]))
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->postJson('/api/users', $this->payload($tenant->id, ['password' => 'curta']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->postJson('/api/users', $this->payload((string) fake()->uuid()))
            ->assertUnprocessable()->assertJsonValidationErrors('tenant_id');
    }

    public function test_listagem_filtra_por_prefeitura_e_nao_inclui_administradores(): void
    {
        $tenantA = $this->criarTenant();
        $tenantB = $this->criarTenant();
        $this->criarGestor($tenantA);
        $this->criarFiscal($tenantA);
        $this->criarGestor($tenantB);
        Sanctum::actingAs($this->criarAdmin());

        $this->getJson('/api/users')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson("/api/users?tenant_id={$tenantA->id}")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/users?role=fiscal_controle_interno')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_conta_de_administrador_nao_e_visivel_nem_editavel_pela_api(): void
    {
        $outroAdmin = $this->criarAdmin();
        Sanctum::actingAs($this->criarAdmin());

        $this->getJson("/api/users/{$outroAdmin->id}")->assertNotFound();
        $this->putJson("/api/users/{$outroAdmin->id}", ['active' => false])->assertNotFound();
    }

    public function test_desativar_usuario_revoga_tokens_e_barra_o_acesso_imediatamente(): void
    {
        $gestor = $this->criarGestor();
        $token = $gestor->createToken('teste')->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/me')->assertOk();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->criarAdmin());
        $this->putJson("/api/users/{$gestor->id}", ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.active', false);

        $this->assertSame(0, $gestor->tokens()->count());
    }

    public function test_usuario_desativado_nao_consegue_logar(): void
    {
        $gestor = $this->criarGestor();
        $gestor->forceFill(['active' => false])->save();

        $this->postJson('/api/login', [
            'email' => $gestor->email,
            'password' => 'password',
            'device_name' => 'teste',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_usuario_de_prefeitura_inativa_nao_loga_e_token_existente_e_barrado(): void
    {
        $gestor = $this->criarGestor();
        $token = $gestor->createToken('teste')->plainTextToken;
        $gestor->tenant->update(['active' => false]);

        $this->postJson('/api/login', [
            'email' => $gestor->email,
            'password' => 'password',
            'device_name' => 'teste',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertForbidden();
    }

    public function test_trocar_a_senha_revoga_tokens_antigos(): void
    {
        $gestor = $this->criarGestor();
        $gestor->createToken('antigo');
        Sanctum::actingAs($this->criarAdmin());

        $this->putJson("/api/users/{$gestor->id}", ['password' => 'novaSenha123'])->assertOk();

        $this->assertSame(0, $gestor->tokens()->count());
        $this->assertTrue(password_verify('novaSenha123', $gestor->fresh()->password));
    }

    public function test_alteracoes_de_usuario_sao_auditadas_sem_vazar_a_senha(): void
    {
        // O pacote ignora o console por padrão, e o PHPUnit conta como console.
        config(['audit.console' => true]);

        $tenant = $this->criarTenant();
        $admin = $this->criarAdmin();
        Sanctum::actingAs($admin);

        $id = $this->postJson('/api/users', $this->payload($tenant->id))->json('data.id');

        $auditoria = User::findOrFail($id)->audits()->latest()->first();
        $this->assertNotNull($auditoria);
        $this->assertSame($admin->id, $auditoria->user_id);
        $this->assertSame('gestor_convenios', $auditoria->new_values['role']);
        $this->assertArrayNotHasKey('password', $auditoria->new_values);
    }
}
