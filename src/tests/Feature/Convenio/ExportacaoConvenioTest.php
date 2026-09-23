<?php

namespace Tests\Feature\Convenio;

use App\Enums\StatusConvenio;
use App\Models\ContratoVinculado;
use App\Models\Convenio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class ExportacaoConvenioTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

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
            'data_vigencia_fim' => '2026-12-31',
        ], $sobrescreve));
    }

    private function contrato(Convenio $convenio, float $valor): void
    {
        ContratoVinculado::forceCreate([
            'tenant_id' => $convenio->tenant_id,
            'convenio_id' => $convenio->id,
            'numero_contrato' => 'CT-001',
            'empresa_contratada' => 'Empresa Teste',
            'valor_contratado' => $valor,
            'status_execucao' => 'em_andamento',
        ]);
    }

    public function test_csv_traz_a_carteira_da_propria_prefeitura_no_formato_do_excel(): void
    {
        $fiscal = $this->criarFiscal();
        $convenio = $this->convenio($fiscal->tenant_id, ['numero_convenio' => '111/2026']);
        $this->contrato($convenio, 300.5);
        $this->convenio($this->criarTenant()->id, ['numero_convenio' => '999/2026']);

        Sanctum::actingAs($fiscal);

        $resposta = $this->get('/api/convenios/exportar?formato=csv')->assertOk();
        $resposta->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $resposta->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Número;"Órgão concedente";Objeto', $csv);
        $this->assertStringContainsString('111/2026;', $csv);
        $this->assertStringNotContainsString('999/2026', $csv);
        // Repasse 1000,00 e contrapartida 200,00 - contratado 300,50 = saldo 899,50 (vírgula decimal, sem milhar).
        $this->assertStringContainsString(';1000,00;200,00;300,50;899,50;', $csv);
        $this->assertStringContainsString(';31/12/2026;', $csv);
    }

    public function test_csv_neutraliza_texto_que_o_excel_executaria_como_formula(): void
    {
        $gestor = $this->criarGestor();
        $this->convenio($gestor->tenant_id, ['objeto' => '=HYPERLINK("http://mal.example")']);

        Sanctum::actingAs($gestor);

        $csv = $this->get('/api/convenios/exportar?formato=csv')->streamedContent();

        $this->assertStringContainsString("\"'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(';"=HYPERLINK', $csv);
    }

    public function test_exportacao_respeita_os_filtros_da_listagem(): void
    {
        $gestor = $this->criarGestor();
        $this->convenio($gestor->tenant_id, ['numero_convenio' => 'AAA/2026', 'status' => StatusConvenio::Aprovado]);
        $this->convenio($gestor->tenant_id, ['numero_convenio' => 'BBB/2026', 'status' => StatusConvenio::Finalizado]);

        Sanctum::actingAs($gestor);

        $csv = $this->get('/api/convenios/exportar?formato=csv&status=aprovado')->streamedContent();
        $this->assertStringContainsString('AAA/2026', $csv);
        $this->assertStringNotContainsString('BBB/2026', $csv);

        $csv = $this->get('/api/convenios/exportar?formato=csv&busca=BBB')->streamedContent();
        $this->assertStringContainsString('BBB/2026', $csv);
        $this->assertStringNotContainsString('AAA/2026', $csv);
    }

    public function test_pdf_da_carteira_e_gerado(): void
    {
        $fiscal = $this->criarFiscal();
        $this->convenio($fiscal->tenant_id);

        Sanctum::actingAs($fiscal);

        $resposta = $this->get('/api/convenios/exportar?formato=pdf')->assertOk();

        $resposta->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $resposta->getContent());
    }

    public function test_ficha_em_pdf_do_convenio_e_gerada_para_o_fiscal(): void
    {
        $fiscal = $this->criarFiscal();
        $convenio = $this->convenio($fiscal->tenant_id);
        $this->contrato($convenio, 100);

        Sanctum::actingAs($fiscal);

        $resposta = $this->get("/api/convenios/{$convenio->id}/ficha")->assertOk();

        $resposta->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $resposta->getContent());
    }

    public function test_nao_exporta_convenio_de_outra_prefeitura(): void
    {
        $fiscal = $this->criarFiscal();
        $alheio = $this->convenio($this->criarTenant()->id);

        Sanctum::actingAs($fiscal);

        $this->get("/api/convenios/{$alheio->id}/ficha")->assertNotFound();
    }

    public function test_formato_invalido_e_recusado_e_visitante_nao_exporta(): void
    {
        $this->getJson('/api/convenios/exportar?formato=csv')->assertUnauthorized();

        Sanctum::actingAs($this->criarFiscal());

        $this->getJson('/api/convenios/exportar?formato=docx')->assertUnprocessable();
        $this->getJson('/api/convenios/exportar')->assertUnprocessable();
    }

    public function test_exportacao_fica_registrada_no_log_com_quem_pediu(): void
    {
        Log::spy();
        $fiscal = $this->criarFiscal();
        $this->convenio($fiscal->tenant_id);

        Sanctum::actingAs($fiscal);
        $this->get('/api/convenios/exportar?formato=csv')->streamedContent();

        Log::shouldHaveReceived('info')->withArgs(fn ($mensagem, $contexto) => $mensagem === 'Relatório exportado'
            && $contexto['user_id'] === $fiscal->id
            && $contexto['relatorio'] === 'carteira_csv'
            && $contexto['registros'] === 1)->once();
    }
}
