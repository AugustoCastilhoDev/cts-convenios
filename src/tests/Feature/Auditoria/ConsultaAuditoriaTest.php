<?php

namespace Tests\Feature\Auditoria;

use App\Enums\StatusConvenio;
use App\Models\Convenio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class ConsultaAuditoriaTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // O pacote não audita nada em console/PHPUnit por padrão.
        config(['audit.console' => true]);
    }

    private function convenio(string $tenantId): Convenio
    {
        return Convenio::forceCreate([
            'tenant_id' => $tenantId,
            'numero_convenio' => fake()->unique()->numerify('######/2026'),
            'orgao_concedente' => 'Ministério da Saúde',
            'objeto' => 'Aquisição de equipamentos',
            'valor_repasse' => 1000,
            'valor_contrapartida' => 200,
            'status' => StatusConvenio::Proposta,
        ]);
    }

    public function test_admin_ve_quem_alterou_o_que_em_um_convenio(): void
    {
        $gestor = $this->criarGestor();
        $convenio = $this->convenio($gestor->tenant_id);

        Sanctum::actingAs($gestor);
        $this->putJson("/api/convenios/{$convenio->id}", [
            'numero_convenio' => $convenio->numero_convenio,
            'orgao_concedente' => 'Ministério da Saúde',
            'objeto' => 'Aquisição de equipamentos',
            'valor_repasse' => 1000,
            'valor_contrapartida' => 200,
            'status' => 'aprovado',
        ])->assertOk();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->criarAdmin());

        $resposta = $this->getJson("/api/audits?tipo=convenio&registro_id={$convenio->id}&evento=updated")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tipo', 'convenio')
            ->assertJsonPath('data.0.usuario.email', $gestor->email)
            ->assertJsonPath('data.0.valores_antigos.status', 'proposta')
            ->assertJsonPath('data.0.valores_novos.status', 'aprovado');

        $this->assertNotNull($resposta->json('data.0.created_at'));
    }

    public function test_filtra_por_tipo_e_por_periodo(): void
    {
        $tenant = $this->criarTenant();
        $this->convenio($tenant->id);

        Sanctum::actingAs($this->criarAdmin());

        // O tenant e o convênio criados acima geraram eventos "created" de tipos diferentes.
        $this->getJson('/api/audits?tipo=prefeitura')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/audits?tipo=convenio')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/audits?tipo=convenio&de=2000-01-01&ate=2000-01-02')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_filtro_invalido_e_recusado(): void
    {
        Sanctum::actingAs($this->criarAdmin());

        $this->getJson('/api/audits?tipo=inexistente')->assertUnprocessable();
        $this->getJson('/api/audits?de=2026-02-01&ate=2026-01-01')->assertUnprocessable();
    }

    public function test_gestor_e_fiscal_nao_consultam_a_auditoria(): void
    {
        $gestor = $this->criarGestor();

        Sanctum::actingAs($gestor);
        $this->getJson('/api/audits')->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->criarFiscal($gestor->tenant));
        $this->getJson('/api/audits')->assertForbidden();
    }
}
