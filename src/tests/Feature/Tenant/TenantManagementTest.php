<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class TenantManagementTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    private const CNPJ_VALIDO = '11.222.333/0001-81';

    public function test_admin_cadastra_prefeitura_e_o_cnpj_e_guardado_com_mascara(): void
    {
        Sanctum::actingAs($this->criarAdmin());

        $this->postJson('/api/tenants', ['razao_social' => 'Prefeitura de Nova Vila', 'cnpj' => '11222333000181'])
            ->assertCreated()
            ->assertJsonPath('data.razao_social', 'Prefeitura de Nova Vila')
            ->assertJsonPath('data.cnpj', self::CNPJ_VALIDO)
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.usuarios_count', 0);

        $this->assertDatabaseHas('tenants', ['cnpj' => self::CNPJ_VALIDO, 'active' => true]);
    }

    public function test_cnpj_invalido_ou_repetido_e_recusado(): void
    {
        $this->criarTenant(['cnpj' => self::CNPJ_VALIDO]);
        Sanctum::actingAs($this->criarAdmin());

        $this->postJson('/api/tenants', ['razao_social' => 'X', 'cnpj' => '11.222.333/0001-82'])
            ->assertUnprocessable()->assertJsonValidationErrors('cnpj');

        $this->postJson('/api/tenants', ['razao_social' => 'X', 'cnpj' => '00.000.000/0000-00'])
            ->assertUnprocessable()->assertJsonValidationErrors('cnpj');

        // Mesmo CNPJ digitado sem máscara é reconhecido como repetido.
        $this->postJson('/api/tenants', ['razao_social' => 'X', 'cnpj' => '11222333000181'])
            ->assertUnprocessable()->assertJsonValidationErrors('cnpj');
    }

    public function test_gestor_e_fiscal_nao_cadastram_nem_editam_prefeituras(): void
    {
        $gestor = $this->criarGestor();

        Sanctum::actingAs($gestor);
        $this->postJson('/api/tenants', ['razao_social' => 'X', 'cnpj' => self::CNPJ_VALIDO])->assertForbidden();
        $this->putJson("/api/tenants/{$gestor->tenant_id}", ['razao_social' => 'Renomeada'])->assertForbidden();
    }

    public function test_admin_edita_e_cadastro_antigo_com_cnpj_sem_digito_valido_continua_editavel(): void
    {
        // CNPJ fictício do seeder: não passa no dígito verificador, mas já existe.
        $tenant = $this->criarTenant(['cnpj' => '00.000.000/0001-00']);
        Sanctum::actingAs($this->criarAdmin());

        $this->putJson("/api/tenants/{$tenant->id}", ['razao_social' => 'Nome Novo', 'cnpj' => '00.000.000/0001-00'])
            ->assertOk()
            ->assertJsonPath('data.razao_social', 'Nome Novo');

        // Trocar o CNPJ passa a exigir um válido.
        $this->putJson("/api/tenants/{$tenant->id}", ['cnpj' => '12.345.678/0001-00'])
            ->assertUnprocessable()->assertJsonValidationErrors('cnpj');
    }

    public function test_desativar_prefeitura_barra_o_acesso_dos_usuarios_dela(): void
    {
        $gestor = $this->criarGestor();
        $admin = $this->criarAdmin();

        Sanctum::actingAs($admin);
        $this->putJson("/api/tenants/{$gestor->tenant_id}", ['active' => false])
            ->assertOk()->assertJsonPath('data.active', false);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($gestor->fresh());
        $this->getJson('/api/convenios')->assertForbidden();
    }

    public function test_listagem_completa_traz_inativas_e_contagens_e_a_padrao_so_as_ativas(): void
    {
        $ativa = $this->criarTenant(['razao_social' => 'Ativa']);
        $this->criarTenant(['razao_social' => 'Inativa', 'active' => false]);
        $this->criarGestor($ativa);
        $this->criarFiscal($ativa);

        Sanctum::actingAs($this->criarAdmin());

        $this->getJson('/api/tenants')->assertJsonCount(1, 'data');

        $resposta = $this->getJson('/api/tenants?todas=1')->assertOk()->assertJsonCount(2, 'data');
        $linhas = collect($resposta->json('data'))->keyBy('razao_social');
        $this->assertSame(2, $linhas['Ativa']['usuarios_count']);
        $this->assertFalse($linhas['Inativa']['active']);
        $this->assertSame(2, Tenant::count());
    }
}
