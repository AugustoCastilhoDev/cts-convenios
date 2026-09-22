<?php

namespace Tests\Feature\ContratoVinculado;

use App\Enums\StatusConvenio;
use App\Enums\StatusExecucaoContrato;
use App\Models\Convenio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class ContratoVinculadoAuthorizationTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    private function criarConvenio(string $tenantId): Convenio
    {
        return Convenio::forceCreate([
            'tenant_id' => $tenantId,
            'numero_convenio' => fake()->unique()->numerify('######/2026'),
            'orgao_concedente' => 'Ministério da Saúde',
            'objeto' => 'Aquisição de equipamentos',
            'valor_repasse' => 500000,
            'valor_contrapartida' => 50000,
            'status' => StatusConvenio::EmExecucao,
        ]);
    }

    private function payloadContrato(array $sobrescreve = []): array
    {
        return array_merge([
            'numero_contrato' => '45/2026',
            'empresa_contratada' => 'Construtora Exemplo LTDA',
            'valor_contratado' => 300000,
            'status_execucao' => StatusExecucaoContrato::EmAndamento->value,
        ], $sobrescreve);
    }

    public function test_gestor_cria_contrato_e_saldo_do_convenio_e_recalculado(): void
    {
        $gestor = $this->criarGestor();
        $convenio = $this->criarConvenio($gestor->tenant_id);
        Sanctum::actingAs($gestor);

        $this->postJson("/api/convenios/{$convenio->id}/contratos", $this->payloadContrato())
            ->assertCreated();

        // 500.000 + 50.000 - 300.000 = 250.000
        $this->getJson("/api/convenios/{$convenio->id}")
            ->assertOk()
            ->assertJsonPath('data.saldo_disponivel', 250000);
    }

    public function test_fiscal_nao_pode_criar_contrato_mas_pode_listar(): void
    {
        $fiscal = $this->criarFiscal();
        $convenio = $this->criarConvenio($fiscal->tenant_id);
        Sanctum::actingAs($fiscal);

        $this->postJson("/api/convenios/{$convenio->id}/contratos", $this->payloadContrato())
            ->assertForbidden();

        $this->getJson("/api/convenios/{$convenio->id}/contratos")->assertOk();
    }

    public function test_gestor_nao_lanca_contrato_em_convenio_de_outro_tenant(): void
    {
        $tenantB = $this->criarTenant();
        $convenioB = $this->criarConvenio($tenantB->id);

        Sanctum::actingAs($this->criarGestor());

        // TenantScope já bloqueia no binding de {convenio}, antes da Policy.
        $this->postJson("/api/convenios/{$convenioB->id}/contratos", $this->payloadContrato())
            ->assertNotFound();
    }

    public function test_gestor_nao_atualiza_contrato_de_outro_convenio_na_url_errada(): void
    {
        $gestor = $this->criarGestor();
        $convenioA = $this->criarConvenio($gestor->tenant_id);
        $convenioC = $this->criarConvenio($gestor->tenant_id);

        Sanctum::actingAs($gestor);
        $contratoDoConvenioA = $this->postJson(
            "/api/convenios/{$convenioA->id}/contratos",
            $this->payloadContrato()
        )->json('data.id');

        // Mesmo tenant, mas contrato pertence a outro convênio na URL.
        $this->putJson(
            "/api/convenios/{$convenioC->id}/contratos/{$contratoDoConvenioA}",
            $this->payloadContrato(['numero_contrato' => 'ALTERADO'])
        )->assertNotFound();
    }
}
