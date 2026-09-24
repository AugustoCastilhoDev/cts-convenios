<?php

namespace Tests\Feature\Convenio;

use App\Enums\Secretaria;
use App\Enums\StatusConvenio;
use App\Models\ContratoVinculado;
use App\Models\Convenio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class SecretariaTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(array $sobrescreve = []): array
    {
        return array_merge([
            'numero_convenio' => fake()->unique()->numerify('######/2026'),
            'orgao_concedente' => 'Ministério da Saúde',
            'objeto' => 'Aquisição de equipamentos',
            'valor_repasse' => 1000,
            'valor_contrapartida' => 100,
            'status' => 'em_execucao',
        ], $sobrescreve);
    }

    private function convenio(string $tenantId, array $sobrescreve = []): Convenio
    {
        return Convenio::forceCreate(array_merge([
            'tenant_id' => $tenantId,
            'numero_convenio' => fake()->unique()->numerify('######/2026'),
            'orgao_concedente' => 'Ministério da Saúde',
            'objeto' => 'Aquisição de equipamentos',
            'valor_repasse' => 1000,
            'valor_contrapartida' => 100,
            'status' => StatusConvenio::EmExecucao,
        ], $sobrescreve));
    }

    private function contrato(Convenio $convenio, float $valor): void
    {
        ContratoVinculado::forceCreate([
            'tenant_id' => $convenio->tenant_id,
            'convenio_id' => $convenio->id,
            'numero_contrato' => fake()->unique()->numerify('CT-####'),
            'empresa_contratada' => 'Empresa Teste',
            'valor_contratado' => $valor,
            'status_execucao' => 'em_andamento',
        ]);
    }

    public function test_cria_e_atualiza_convenio_com_secretaria(): void
    {
        Sanctum::actingAs($this->criarGestor());

        $id = $this->postJson('/api/convenios', $this->payload(['secretaria' => 'saude']))
            ->assertCreated()
            ->assertJsonPath('data.secretaria', 'saude')
            ->assertJsonPath('data.secretaria_label', 'Saúde')
            ->json('data.id');

        $convenio = Convenio::findOrFail($id);
        $this->assertSame(Secretaria::Saude, $convenio->secretaria);

        $this->putJson("/api/convenios/{$id}", $this->payload([
            'numero_convenio' => $convenio->numero_convenio,
            'secretaria' => 'obras',
        ]))->assertOk()->assertJsonPath('data.secretaria', 'obras');
    }

    public function test_secretaria_e_opcional_na_api_mas_valores_invalidos_sao_recusados(): void
    {
        Sanctum::actingAs($this->criarGestor());

        $this->postJson('/api/convenios', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.secretaria', null)
            ->assertJsonPath('data.secretaria_label', null);

        $this->postJson('/api/convenios', $this->payload(['secretaria' => 'financas-secretas']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('secretaria');
    }

    public function test_resposta_traz_valor_contratado_e_percentual_comprometido(): void
    {
        $gestor = $this->criarGestor();
        // Valor total 1.100 (repasse 1.000 + contrapartida 100); contratado 550 = 50%.
        $convenio = $this->convenio($gestor->tenant_id, ['secretaria' => Secretaria::Obras]);
        $this->contrato($convenio, 300);
        $this->contrato($convenio, 250);

        Sanctum::actingAs($gestor);

        $this->getJson("/api/convenios/{$convenio->id}")
            ->assertOk()
            ->assertJsonPath('data.valor_contratado', 550)
            ->assertJsonPath('data.total_contratado', 550)
            ->assertJsonPath('data.percentual_comprometido', 50)
            ->assertJsonPath('data.saldo_disponivel', 550);

        $this->getJson('/api/convenios')
            ->assertJsonPath('data.0.valor_contratado', 550)
            ->assertJsonPath('data.0.percentual_comprometido', 50);
    }

    public function test_percentual_arredonda_com_uma_casa_passa_de_cem_e_nao_divide_por_zero(): void
    {
        $gestor = $this->criarGestor();
        $terco = $this->convenio($gestor->tenant_id, ['valor_repasse' => 900, 'valor_contrapartida' => 0]);
        $this->contrato($terco, 300); // 33,333...%

        $excedido = $this->convenio($gestor->tenant_id);
        $this->contrato($excedido, 1650); // 150%

        $zerado = $this->convenio($gestor->tenant_id, ['valor_repasse' => 0, 'valor_contrapartida' => 0]);

        Sanctum::actingAs($gestor);

        $this->getJson("/api/convenios/{$terco->id}")->assertJsonPath('data.percentual_comprometido', 33.3);
        $this->getJson("/api/convenios/{$excedido->id}")->assertJsonPath('data.percentual_comprometido', 150);
        $this->getJson("/api/convenios/{$zerado->id}")->assertJsonPath('data.percentual_comprometido', 0);
    }

    public function test_exportacao_inclui_a_secretaria(): void
    {
        $gestor = $this->criarGestor();
        $this->convenio($gestor->tenant_id, ['secretaria' => Secretaria::AssistenciaSocial]);

        Sanctum::actingAs($gestor);

        $csv = $this->get('/api/convenios/exportar?formato=csv')->streamedContent();

        $this->assertStringContainsString('Objeto;Secretaria;Etapa', $csv);
        $this->assertStringContainsString('"Assistência Social"', $csv);
    }
}
