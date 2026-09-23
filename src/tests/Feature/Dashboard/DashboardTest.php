<?php

namespace Tests\Feature\Dashboard;

use App\Enums\StatusConvenio;
use App\Enums\StatusExecucaoContrato;
use App\Models\ContratoVinculado;
use App\Models\Convenio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Meio-dia UTC = 09:00 em Brasília: "hoje" é 2026-09-23 nos dois fusos.
        $this->travelTo('2026-09-23 12:00:00');
    }

    private function convenio(string $tenantId, array $sobrescreve = []): Convenio
    {
        return Convenio::forceCreate(array_merge([
            'tenant_id' => $tenantId,
            'numero_convenio' => fake()->unique()->numerify('######/2026'),
            'orgao_concedente' => 'Ministério da Saúde',
            'objeto' => 'Aquisição de equipamentos',
            'valor_repasse' => 1000,
            'valor_contrapartida' => 200,
            'status' => StatusConvenio::EmExecucao,
        ], $sobrescreve));
    }

    private function contrato(Convenio $convenio, float $valor, StatusExecucaoContrato $status = StatusExecucaoContrato::EmAndamento): ContratoVinculado
    {
        return ContratoVinculado::forceCreate([
            'tenant_id' => $convenio->tenant_id,
            'convenio_id' => $convenio->id,
            'numero_contrato' => fake()->unique()->numerify('CT-####'),
            'empresa_contratada' => 'Empresa Teste',
            'valor_contratado' => $valor,
            'status_execucao' => $status,
        ]);
    }

    public function test_totais_consideram_apenas_a_carteira_ativa_da_propria_prefeitura(): void
    {
        $gestor = $this->criarGestor();
        $ativo = $this->convenio($gestor->tenant_id);
        $this->contrato($ativo, 300);

        // Finalizado e de outra prefeitura ficam de fora dos totais.
        $finalizado = $this->convenio($gestor->tenant_id, ['status' => StatusConvenio::Finalizado]);
        $this->contrato($finalizado, 999);
        $outro = $this->convenio($this->criarTenant()->id);
        $this->contrato($outro, 888);

        Sanctum::actingAs($gestor);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.resumo.convenios_ativos', 1)
            ->assertJsonPath('data.resumo.valor_total', 1200)
            ->assertJsonPath('data.resumo.total_contratado', 300)
            ->assertJsonPath('data.resumo.saldo_disponivel', 900)
            ->assertJsonPath('data.resumo.percentual_contratado', 25)
            ->assertJsonPath('data.resumo.convenios_com_excesso_contratado', 0);
    }

    public function test_conta_convenios_com_contratos_acima_do_valor_disponivel(): void
    {
        $gestor = $this->criarGestor();
        $this->contrato($this->convenio($gestor->tenant_id), 1500);
        $this->convenio($gestor->tenant_id);

        Sanctum::actingAs($gestor);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.resumo.convenios_com_excesso_contratado', 1)
            ->assertJsonPath('data.resumo.saldo_disponivel', 900);
    }

    public function test_por_etapa_lista_todas_as_etapas_inclusive_as_vazias(): void
    {
        $gestor = $this->criarGestor();
        $this->convenio($gestor->tenant_id, ['status' => StatusConvenio::Aprovado]);
        $this->convenio($gestor->tenant_id, ['status' => StatusConvenio::Aprovado, 'valor_repasse' => 800, 'valor_contrapartida' => 0]);

        Sanctum::actingAs($gestor);

        $resposta = $this->getJson('/api/dashboard')->assertOk();

        $resposta->assertJsonCount(count(StatusConvenio::cases()), 'data.por_etapa');
        $etapas = collect($resposta->json('data.por_etapa'))->keyBy('status');
        $this->assertSame(2, $etapas['aprovado']['quantidade']);
        $this->assertEquals(2000, $etapas['aprovado']['valor_total']);
        $this->assertSame(0, $etapas['finalizado']['quantidade']);
        $this->assertSame('proposta', $resposta->json('data.por_etapa.0.status'));
    }

    public function test_prazos_criticos_seguem_as_regras_do_motor_de_alertas_e_ordenam_por_urgencia(): void
    {
        $gestor = $this->criarGestor();
        $t = $gestor->tenant_id;

        $vencido = $this->convenio($t, ['data_vigencia_fim' => '2026-09-13']);          // -10 dias
        $proximo = $this->convenio($t, ['data_vigencia_fim' => '2026-10-08']);          // 15 dias
        $prestacao = $this->convenio($t, [
            'status' => StatusConvenio::PrestacaoContas,
            'data_vigencia_fim' => '2026-09-25',                                         // vigência não é monitorada aqui
            'prazo_prestacao_contas' => '2026-09-30',                                    // 7 dias
        ]);
        $this->convenio($t, ['data_vigencia_fim' => '2027-06-01']);                     // longe: fora da janela
        $this->convenio($t, ['status' => StatusConvenio::Finalizado, 'data_vigencia_fim' => '2026-09-01']);
        $this->convenio($this->criarTenant()->id, ['data_vigencia_fim' => '2026-09-10']); // outra prefeitura

        Sanctum::actingAs($gestor);

        $prazos = $this->getJson('/api/dashboard')->assertOk()->json('data.prazos_criticos');

        $this->assertSame(
            [$vencido->id, $prestacao->id, $proximo->id],
            array_column($prazos, 'convenio_id')
        );
        $this->assertSame([-10, 7, 15], array_column($prazos, 'dias'));
        $this->assertSame('prestacao_contas', $prazos[1]['tipo_prazo']);
    }

    public function test_administrador_ve_o_consolidado_de_todas_as_prefeituras(): void
    {
        $this->convenio($this->criarTenant()->id);
        $this->convenio($this->criarTenant()->id);

        Sanctum::actingAs($this->criarAdmin());

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.resumo.convenios_ativos', 2);
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }
}
