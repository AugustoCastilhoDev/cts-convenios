<?php

namespace Tests\Feature\Convenio;

use App\Enums\StatusConvenio;
use App\Models\Convenio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class ConvenioAuthorizationTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    private function payload(array $sobrescreve = []): array
    {
        return array_merge([
            'numero_convenio' => '941235/2026',
            'orgao_concedente' => 'Ministério da Saúde',
            'objeto' => 'Aquisição de equipamentos',
            'valor_repasse' => 500000,
            'valor_contrapartida' => 50000,
            'status' => StatusConvenio::EmExecucao->value,
        ], $sobrescreve);
    }

    public function test_gestor_cria_convenio_no_proprio_tenant(): void
    {
        $gestor = $this->criarGestor();
        Sanctum::actingAs($gestor);

        $this->postJson('/api/convenios', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.tenant_id', $gestor->tenant_id);
    }

    public function test_fiscal_nao_pode_criar_convenio(): void
    {
        Sanctum::actingAs($this->criarFiscal());

        $this->postJson('/api/convenios', $this->payload())->assertForbidden();
    }

    public function test_administrador_interno_sem_tenant_id_no_payload_e_bloqueado_pelo_service(): void
    {
        Sanctum::actingAs($this->criarAdmin());

        // Policy libera (before() = true), mas o Service exige tenant_id
        // explícito quando quem cria não pertence a nenhuma prefeitura.
        $this->postJson('/api/convenios', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tenant_id');
    }

    public function test_gestor_nao_enxerga_convenio_de_outro_tenant(): void
    {
        $tenantB = $this->criarTenant();
        $convenioB = Convenio::forceCreate(array_merge($this->payload(), ['tenant_id' => $tenantB->id]));

        Sanctum::actingAs($this->criarGestor());

        // TenantScope filtra no binding da rota antes da Policy — por isso
        // 404 (não 403): o usuário não deve nem saber que o registro existe.
        $this->getJson("/api/convenios/{$convenioB->id}")->assertNotFound();
    }

    public function test_admin_enxerga_convenio_de_qualquer_tenant(): void
    {
        $tenant = $this->criarTenant();
        $convenio = Convenio::forceCreate(array_merge($this->payload(), ['tenant_id' => $tenant->id]));

        Sanctum::actingAs($this->criarAdmin());

        $this->getJson("/api/convenios/{$convenio->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $convenio->id);
    }

    public function test_index_so_lista_convenios_do_proprio_tenant(): void
    {
        $tenantA = $this->criarTenant();
        $tenantB = $this->criarTenant();

        Convenio::forceCreate(array_merge($this->payload(['numero_convenio' => 'A/2026']), ['tenant_id' => $tenantA->id]));
        Convenio::forceCreate(array_merge($this->payload(['numero_convenio' => 'B/2026']), ['tenant_id' => $tenantB->id]));

        Sanctum::actingAs($this->criarGestor($tenantA));

        $this->getJson('/api/convenios')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.numero_convenio', 'A/2026');
    }

    public function test_apenas_administrador_interno_pode_deletar(): void
    {
        $tenant = $this->criarTenant();
        $convenio = Convenio::forceCreate(array_merge($this->payload(), ['tenant_id' => $tenant->id]));

        Sanctum::actingAs($this->criarGestor($tenant));
        $this->deleteJson("/api/convenios/{$convenio->id}")->assertForbidden();

        Sanctum::actingAs($this->criarAdmin());
        $this->deleteJson("/api/convenios/{$convenio->id}")->assertNoContent();
    }

    public function test_fiscal_nao_pode_atualizar_convenio(): void
    {
        $tenant = $this->criarTenant();
        $convenio = Convenio::forceCreate(array_merge($this->payload(), ['tenant_id' => $tenant->id]));

        Sanctum::actingAs($this->criarFiscal($tenant));

        $this->putJson("/api/convenios/{$convenio->id}", $this->payload())->assertForbidden();
    }

    public function test_numero_convenio_duplicado_no_mesmo_tenant_e_rejeitado(): void
    {
        Sanctum::actingAs($this->criarGestor());

        $this->postJson('/api/convenios', $this->payload())->assertCreated();
        $this->postJson('/api/convenios', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('numero_convenio');
    }

    public function test_mesmo_numero_convenio_em_tenants_diferentes_e_permitido(): void
    {
        Sanctum::actingAs($this->criarGestor());
        $this->postJson('/api/convenios', $this->payload())->assertCreated();

        Sanctum::actingAs($this->criarGestor());
        $this->postJson('/api/convenios', $this->payload())->assertCreated();
    }
}
