<?php

namespace Tests\Feature\User;

use App\Enums\UserRole;
use App\Models\Convenio;
use App\Models\User;
use App\Services\AlertaPrazoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

/** O administrador da prefeitura gerencia as contas da PRÓPRIA prefeitura e nada além disso. */
class AdministradorPrefeituraTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    private function novo(array $sobrescreve = []): array
    {
        return array_merge([
            'name' => 'Servidor Novo',
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::GestorConvenios->value,
        ], $sobrescreve);
    }

    // ------------------------------------------------------------------ listar e criar

    public function test_lista_so_os_usuarios_da_propria_prefeitura_mesmo_pedindo_outra(): void
    {
        $admin = $this->criarAdminDaPrefeitura();
        $colega = $this->criarGestor($admin->tenant);
        $deOutraPrefeitura = $this->criarGestor();

        Sanctum::actingAs($admin);

        $emails = collect($this->getJson('/api/users')->assertOk()->json('data'))->pluck('email');
        $this->assertTrue($emails->contains($colega->email));
        $this->assertTrue($emails->contains($admin->email));
        $this->assertFalse($emails->contains($deOutraPrefeitura->email));

        $filtrado = collect($this->getJson('/api/users?tenant_id='.$deOutraPrefeitura->tenant_id)->json('data'))->pluck('email');
        $this->assertFalse($filtrado->contains($deOutraPrefeitura->email));
    }

    public function test_cria_usuarios_na_propria_prefeitura_ignorando_o_tenant_enviado(): void
    {
        $admin = $this->criarAdminDaPrefeitura();
        $outra = $this->criarTenant();
        Sanctum::actingAs($admin);

        foreach ([UserRole::GestorConvenios, UserRole::FiscalControleInterno, UserRole::AdministradorPrefeitura] as $papel) {
            $resposta = $this->postJson('/api/users', $this->novo(['role' => $papel->value, 'tenant_id' => $outra->id]))
                ->assertCreated()
                ->assertJsonPath('data.tenant_id', $admin->tenant_id)
                ->assertJsonPath('data.role', $papel->value);

            $this->assertNotEmpty($resposta->json('senha_temporaria'));
            $this->assertTrue(User::findOrFail($resposta->json('data.id'))->must_change_password);
        }
    }

    public function test_nao_cria_super_administrador(): void
    {
        Sanctum::actingAs($this->criarAdminDaPrefeitura());

        $this->postJson('/api/users', $this->novo(['role' => UserRole::AdministradorInterno->value]))
            ->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_gestor_e_fiscal_continuam_sem_gerenciar_usuarios(): void
    {
        $admin = $this->criarAdminDaPrefeitura();
        $alvo = $this->criarFiscal($admin->tenant);

        foreach ([$this->criarGestor($admin->tenant), $this->criarFiscal($admin->tenant)] as $usuario) {
            $this->app['auth']->forgetGuards();
            Sanctum::actingAs($usuario);

            $this->getJson('/api/users')->assertForbidden();
            $this->postJson('/api/users', $this->novo())->assertForbidden();
            $this->postJson("/api/users/{$alvo->id}/redefinir-senha")->assertForbidden();
        }
    }

    // ------------------------------------------------------------------ isolamento

    public function test_nao_ve_edita_nem_redefine_a_senha_de_outra_prefeitura(): void
    {
        $admin = $this->criarAdminDaPrefeitura();
        $estranho = $this->criarGestor();
        Sanctum::actingAs($admin);

        $this->getJson("/api/users/{$estranho->id}")->assertForbidden();
        $this->putJson("/api/users/{$estranho->id}", ['name' => 'Invadido'])->assertForbidden();
        $this->putJson("/api/users/{$estranho->id}", ['active' => false])->assertForbidden();
        $this->postJson("/api/users/{$estranho->id}/redefinir-senha")->assertForbidden();

        $estranho->refresh();
        $this->assertNotSame('Invadido', $estranho->name);
        $this->assertTrue($estranho->active);
        $this->assertTrue(password_verify('password', $estranho->password));
    }

    public function test_nao_toca_na_conta_de_um_super_administrador(): void
    {
        $super = $this->criarAdmin();
        Sanctum::actingAs($this->criarAdminDaPrefeitura());

        $this->getJson("/api/users/{$super->id}")->assertForbidden();
        $this->putJson("/api/users/{$super->id}", ['active' => false])->assertForbidden();
        $this->postJson("/api/users/{$super->id}/redefinir-senha")->assertForbidden();
        $this->assertTrue($super->fresh()->active);
    }

    public function test_nao_acessa_prefeituras_nem_pedidos_de_contato(): void
    {
        Sanctum::actingAs($this->criarAdminDaPrefeitura());

        $this->getJson('/api/tenants')->assertForbidden();
        $this->getJson('/api/contatos')->assertForbidden();
        $this->postJson('/api/tenants', ['razao_social' => 'X', 'cnpj' => '11.222.333/0001-81'])->assertForbidden();
    }

    // ------------------------------------------------------------------ editar, desativar, redefinir

    public function test_edita_desativa_e_a_pessoa_perde_o_acesso_na_hora(): void
    {
        $admin = $this->criarAdminDaPrefeitura();
        $colega = $this->criarGestor($admin->tenant);
        $colega->createToken('celular');
        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$colega->id}", ['name' => 'Nome Corrigido', 'role' => UserRole::FiscalControleInterno->value])
            ->assertOk()->assertJsonPath('data.role', 'fiscal_controle_interno');

        $this->putJson("/api/users/{$colega->id}", ['active' => false])->assertOk();

        $this->assertSame(0, $colega->tokens()->count());
        $this->assertFalse($colega->fresh()->active);
    }

    public function test_redefine_a_senha_de_um_colega_com_uma_temporaria_nova(): void
    {
        $admin = $this->criarAdminDaPrefeitura();
        $colega = $this->criarGestor($admin->tenant);
        $colega->createToken('celular');
        Sanctum::actingAs($admin);

        $resposta = $this->postJson("/api/users/{$colega->id}/redefinir-senha")->assertOk();

        $colega->refresh();
        $this->assertTrue(password_verify($resposta->json('senha_temporaria'), $colega->password));
        $this->assertTrue($colega->must_change_password);
        $this->assertSame(0, $colega->tokens()->count());
        $this->assertFalse(password_verify('password', $colega->password));
    }

    public function test_nao_redefine_a_propria_senha_por_esse_botao(): void
    {
        $admin = $this->criarAdminDaPrefeitura();
        Sanctum::actingAs($admin);

        $this->postJson("/api/users/{$admin->id}/redefinir-senha")->assertForbidden();
        $this->assertTrue(password_verify('password', $admin->fresh()->password));
    }

    public function test_nao_desativa_a_si_mesmo_nem_muda_o_proprio_papel(): void
    {
        $admin = $this->criarAdminDaPrefeitura();
        $this->criarAdminDaPrefeitura($admin->tenant); // existe outro, então o motivo é ser a própria conta
        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$admin->id}", ['active' => false])->assertUnprocessable()->assertJsonValidationErrors('active');
        $this->putJson("/api/users/{$admin->id}", ['role' => UserRole::GestorConvenios->value])->assertUnprocessable();

        $admin->refresh();
        $this->assertTrue($admin->active);
        $this->assertSame(UserRole::AdministradorPrefeitura, $admin->role);
    }

    public function test_um_administrador_pode_desativar_o_outro_e_a_prefeitura_mantem_quem_sobrou(): void
    {
        $admin = $this->criarAdminDaPrefeitura();
        $outroAdmin = $this->criarAdminDaPrefeitura($admin->tenant);
        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$outroAdmin->id}", ['active' => false])->assertOk();

        // Quem sobrou não consegue se desativar: a prefeitura nunca fica sem um administrador ativo.
        $this->putJson("/api/users/{$admin->id}", ['active' => false])->assertUnprocessable();
        $this->assertSame(1, User::where('tenant_id', $admin->tenant_id)->where('role', UserRole::AdministradorPrefeitura->value)->where('active', true)->count());
    }

    public function test_respostas_com_a_senha_temporaria_nao_podem_ser_guardadas_em_cache(): void
    {
        $admin = $this->criarAdminDaPrefeitura();
        $colega = $this->criarGestor($admin->tenant);
        Sanctum::actingAs($admin);

        $criada = $this->postJson('/api/users', $this->novo())->assertCreated();
        $redefinida = $this->postJson("/api/users/{$colega->id}/redefinir-senha")->assertOk();

        $this->assertStringContainsString('no-store', $criada->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $redefinida->headers->get('Cache-Control'));
    }

    public function test_o_super_administrador_pode_tudo_nas_contas_de_qualquer_prefeitura(): void
    {
        $admin = $this->criarAdminDaPrefeitura();
        Sanctum::actingAs($this->criarAdmin());

        $this->postJson("/api/users/{$admin->id}/redefinir-senha")->assertOk();
        $this->putJson("/api/users/{$admin->id}", ['active' => false])->assertOk();
    }

    // ------------------------------------------------------------------ trabalha como gestor

    public function test_trabalha_nos_convenios_como_um_gestor_mas_nao_exclui(): void
    {
        $admin = $this->criarAdminDaPrefeitura();
        Sanctum::actingAs($admin);

        $id = $this->postJson('/api/convenios', [
            'numero_convenio' => '777/2026',
            'orgao_concedente' => 'Ministério da Saúde',
            'objeto' => 'Equipamentos',
            'secretaria' => 'saude',
            'valor_repasse' => 1000,
            'valor_contrapartida' => 100,
            'status' => 'em_execucao',
        ])->assertCreated()->json('data.id');

        // A atualização de convênio exige o conjunto completo de campos, como o formulário envia.
        $this->putJson("/api/convenios/{$id}", [
            'numero_convenio' => '777/2026',
            'orgao_concedente' => 'Ministério da Saúde',
            'objeto' => 'Equipamentos e treinamento',
            'secretaria' => 'saude',
            'valor_repasse' => 1000,
            'valor_contrapartida' => 100,
            'status' => 'em_execucao',
        ])->assertOk();
        $this->postJson("/api/convenios/{$id}/contratos", [
            'numero_contrato' => 'CT-1', 'empresa_contratada' => 'Empresa X', 'valor_contratado' => 100, 'status_execucao' => 'nao_iniciado',
        ])->assertCreated();
        $this->getJson('/api/dashboard')->assertOk();
        $this->getJson('/api/notificacoes')->assertOk();

        // Exclusão continua só com o super administrador.
        $this->deleteJson("/api/convenios/{$id}")->assertForbidden();
    }

    public function test_recebe_os_alertas_de_prazo_da_prefeitura(): void
    {
        $admin = $this->criarAdminDaPrefeitura();
        $gestor = $this->criarGestor($admin->tenant);
        // tenant_id não é preenchível em massa: forceCreate, como os outros testes do motor de alertas.
        $convenio = Convenio::forceCreate([
            'tenant_id' => $admin->tenant_id,
            'numero_convenio' => '1/2026',
            'orgao_concedente' => 'Ministério da Saúde',
            'objeto' => 'Aquisição de equipamentos',
            'valor_repasse' => 1000,
            'valor_contrapartida' => 0,
            'status' => 'em_execucao',
        ]);

        $destinatarios = app(AlertaPrazoService::class)->destinatarios($convenio);

        $this->assertContains($admin->email, $destinatarios);
        $this->assertContains($gestor->email, $destinatarios);
    }
}
