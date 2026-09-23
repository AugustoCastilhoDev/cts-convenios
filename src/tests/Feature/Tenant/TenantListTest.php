<?php

namespace Tests\Feature\Tenant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class TenantListTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    public function test_admin_lista_prefeituras_ativas_em_ordem_alfabetica(): void
    {
        $this->criarTenant(['razao_social' => 'Prefeitura B']);
        $this->criarTenant(['razao_social' => 'Prefeitura A']);
        $this->criarTenant(['razao_social' => 'Prefeitura Inativa', 'active' => false]);

        Sanctum::actingAs($this->criarAdmin());

        $this->getJson('/api/tenants')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.razao_social', 'Prefeitura A')
            ->assertJsonPath('data.1.razao_social', 'Prefeitura B');
    }

    public function test_gestor_e_fiscal_nao_listam_prefeituras(): void
    {
        $gestor = $this->criarGestor();
        $fiscal = $this->criarFiscal($gestor->tenant);

        Sanctum::actingAs($gestor);
        $this->getJson('/api/tenants')->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($fiscal);
        $this->getJson('/api/tenants')->assertForbidden();
    }
}
