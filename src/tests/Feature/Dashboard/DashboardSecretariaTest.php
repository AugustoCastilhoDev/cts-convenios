<?php

namespace Tests\Feature\Dashboard;

use App\Enums\Secretaria;
use App\Enums\StatusConvenio;
use App\Models\ContratoVinculado;
use App\Models\Convenio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class DashboardSecretariaTest extends TestCase
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
            'orgao_concedente' => 'Ministério',
            'objeto' => 'Objeto',
            'valor_repasse' => 1000,
            'valor_contrapartida' => 0,
            'status' => StatusConvenio::EmExecucao,
        ], $sobrescreve));
    }

    private function contrato(Convenio $convenio, float $valor): void
    {
        ContratoVinculado::forceCreate([
            'tenant_id' => $convenio->tenant_id,
            'convenio_id' => $convenio->id,
            'numero_contrato' => fake()->unique()->numerify('CT-####'),
            'empresa_contratada' => 'Empresa',
            'valor_contratado' => $valor,
            'status_execucao' => 'em_andamento',
        ]);
    }

    public function test_filtro_recalcula_cartoes_etapas_prazos_e_contratos_so_da_secretaria(): void
    {
        $gestor = $this->criarGestor();
        $t = $gestor->tenant_id;

        $saude = $this->convenio($t, ['secretaria' => Secretaria::Saude, 'valor_repasse' => 2000, 'data_vigencia_fim' => '2026-10-01']);
        $this->contrato($saude, 500);
        $obras = $this->convenio($t, ['secretaria' => Secretaria::Obras, 'valor_repasse' => 700, 'status' => StatusConvenio::Aprovado, 'data_vigencia_fim' => '2026-10-10']);
        $this->contrato($obras, 300);
        $this->convenio($t, ['valor_repasse' => 50]); // sem secretaria

        Sanctum::actingAs($gestor);

        // Sem filtro: os três.
        $this->getJson('/api/dashboard')
            ->assertJsonPath('data.resumo.convenios_ativos', 3)
            ->assertJsonPath('data.resumo.valor_total', 2750)
            ->assertJsonPath('data.resumo.total_contratado', 800);

        $saudeJson = $this->getJson('/api/dashboard?secretaria=saude')->assertOk();
        $saudeJson->assertJsonPath('data.secretaria', 'saude')
            ->assertJsonPath('data.resumo.convenios_ativos', 1)
            ->assertJsonPath('data.resumo.valor_total', 2000)
            ->assertJsonPath('data.resumo.total_contratado', 500)
            ->assertJsonPath('data.resumo.saldo_disponivel', 1500)
            ->assertJsonPath('data.resumo.percentual_contratado', 25);

        $etapas = collect($saudeJson->json('data.por_etapa'))->keyBy('status');
        $this->assertSame(1, $etapas['em_execucao']['quantidade']);
        $this->assertSame(0, $etapas['aprovado']['quantidade']);

        $this->assertSame([$saude->id], array_column($saudeJson->json('data.prazos_criticos'), 'convenio_id'));
        $this->assertSame(1, array_sum(array_column($saudeJson->json('data.contratos_por_execucao'), 'quantidade')));
    }

    public function test_sem_secretaria_traz_so_os_convenios_nao_classificados(): void
    {
        $gestor = $this->criarGestor();
        $this->convenio($gestor->tenant_id, ['secretaria' => Secretaria::Saude]);
        $this->convenio($gestor->tenant_id, ['valor_repasse' => 50]);

        Sanctum::actingAs($gestor);

        $this->getJson('/api/dashboard?secretaria=sem_secretaria')
            ->assertOk()
            ->assertJsonPath('data.resumo.convenios_ativos', 1)
            ->assertJsonPath('data.resumo.valor_total', 50);
    }

    public function test_secretaria_invalida_e_recusada(): void
    {
        Sanctum::actingAs($this->criarGestor());

        $this->getJson('/api/dashboard?secretaria=inexistente')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('secretaria');
    }

    public function test_regularidade_e_risco_com_prazo_vencido_e_nao_muda_com_o_filtro(): void
    {
        $gestor = $this->criarGestor();
        $t = $gestor->tenant_id;

        // Vencido há 10 dias, em Obras.
        $this->convenio($t, ['secretaria' => Secretaria::Obras, 'data_vigencia_fim' => '2026-09-13']);
        $this->convenio($t, ['secretaria' => Secretaria::Saude, 'data_vigencia_fim' => '2026-12-31']);

        Sanctum::actingAs($gestor);

        $esperado = ['situacao' => 'risco', 'prazos_vencidos' => 1, 'convenios_afetados' => 1, 'maior_atraso_dias' => 10];

        $this->getJson('/api/dashboard')->assertJsonPath('data.regularidade', $esperado);
        // Filtrando por Saúde (que está em dia) o município continua em risco.
        $this->getJson('/api/dashboard?secretaria=saude')->assertJsonPath('data.regularidade', $esperado);
    }

    public function test_regularidade_e_regular_quando_tudo_esta_em_dia_e_ignora_o_que_nao_e_monitorado(): void
    {
        $gestor = $this->criarGestor();
        $t = $gestor->tenant_id;

        $this->convenio($t, ['data_vigencia_fim' => '2026-12-31', 'prazo_prestacao_contas' => '2027-01-31']);
        // Vencidos, mas fora do monitoramento: finalizado, e vigência de quem já está em prestação de contas.
        $this->convenio($t, ['status' => StatusConvenio::Finalizado, 'data_vigencia_fim' => '2026-01-01', 'prazo_prestacao_contas' => '2026-02-01']);
        $this->convenio($t, ['status' => StatusConvenio::PrestacaoContas, 'data_vigencia_fim' => '2026-08-01', 'prazo_prestacao_contas' => '2026-11-01']);

        Sanctum::actingAs($gestor);

        $this->getJson('/api/dashboard')
            ->assertJsonPath('data.regularidade.situacao', 'regular')
            ->assertJsonPath('data.regularidade.prazos_vencidos', 0)
            ->assertJsonPath('data.regularidade.maior_atraso_dias', 0);
    }

    public function test_prestacao_de_contas_vencida_tambem_e_risco(): void
    {
        $gestor = $this->criarGestor();
        $this->convenio($gestor->tenant_id, [
            'status' => StatusConvenio::PrestacaoContas,
            'data_vigencia_fim' => '2026-06-01',
            'prazo_prestacao_contas' => '2026-09-20',
        ]);

        Sanctum::actingAs($gestor);

        $this->getJson('/api/dashboard')
            ->assertJsonPath('data.regularidade.situacao', 'risco')
            ->assertJsonPath('data.regularidade.maior_atraso_dias', 3);
    }
}
