<?php

namespace Tests\Feature\ArquivoConvenio;

use App\Enums\StatusConvenio;
use App\Enums\TipoDocumentoConvenio;
use App\Models\ArquivoConvenio;
use App\Models\Convenio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class ArquivoConvenioTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Nunca grava no storage real durante os testes.
        Storage::fake('local');
    }

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

    private function payload(array $sobrescreve = []): array
    {
        return array_merge([
            'tipo_documento' => TipoDocumentoConvenio::TermoAssinatura->value,
            'arquivo' => UploadedFile::fake()->create('termo.pdf', 200, 'application/pdf'),
        ], $sobrescreve);
    }

    public function test_gestor_envia_arquivo_e_ele_e_gravado_em_disco_privado(): void
    {
        $gestor = $this->criarGestor();
        $convenio = $this->criarConvenio($gestor->tenant_id);
        Sanctum::actingAs($gestor);

        $resposta = $this->postJson("/api/convenios/{$convenio->id}/arquivos", $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.nome_original', 'termo.pdf')
            ->assertJsonPath('data.tipo_documento', 'termo_assinatura')
            ->assertJsonPath('data.enviado_por', $gestor->id)
            ->assertJsonMissingPath('data.file_path');

        $registro = ArquivoConvenio::findOrFail($resposta->json('data.id'));

        $this->assertSame($convenio->tenant_id, $registro->tenant_id);
        $this->assertSame($convenio->id, $registro->convenio_id);
        Storage::disk('local')->assertExists($registro->file_path);
        // O nome enviado pelo cliente não vira caminho em disco.
        $this->assertStringNotContainsString('termo', $registro->file_path);
    }

    public function test_fiscal_nao_envia_mas_lista_e_baixa(): void
    {
        $gestor = $this->criarGestor();
        $fiscal = $this->criarFiscal($gestor->tenant);
        $convenio = $this->criarConvenio($gestor->tenant_id);

        Sanctum::actingAs($gestor);
        $id = $this->postJson("/api/convenios/{$convenio->id}/arquivos", $this->payload())
            ->json('data.id');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($fiscal);

        $this->postJson("/api/convenios/{$convenio->id}/arquivos", $this->payload())->assertForbidden();
        $this->getJson("/api/convenios/{$convenio->id}/arquivos")
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->get("/api/convenios/{$convenio->id}/arquivos/{$id}/download")
            ->assertOk()
            ->assertDownload('termo.pdf');
    }

    public function test_usuario_de_outro_tenant_nao_enxerga_nem_baixa_nem_envia(): void
    {
        $gestorA = $this->criarGestor();
        $convenioA = $this->criarConvenio($gestorA->tenant_id);
        Sanctum::actingAs($gestorA);
        $id = $this->postJson("/api/convenios/{$convenioA->id}/arquivos", $this->payload())
            ->json('data.id');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->criarGestor());

        // TenantScope barra no route-model-binding: 404, nunca 403.
        $this->getJson("/api/convenios/{$convenioA->id}/arquivos")->assertNotFound();
        $this->getJson("/api/convenios/{$convenioA->id}/arquivos/{$id}/download")->assertNotFound();
        $this->postJson("/api/convenios/{$convenioA->id}/arquivos", $this->payload())->assertNotFound();
    }

    public function test_download_com_convenio_errado_na_url_retorna_404(): void
    {
        $gestor = $this->criarGestor();
        $convenioA = $this->criarConvenio($gestor->tenant_id);
        $convenioB = $this->criarConvenio($gestor->tenant_id);
        Sanctum::actingAs($gestor);

        $id = $this->postJson("/api/convenios/{$convenioA->id}/arquivos", $this->payload())
            ->json('data.id');

        $this->getJson("/api/convenios/{$convenioB->id}/arquivos/{$id}/download")->assertNotFound();
    }

    public function test_rejeita_tipo_de_arquivo_nao_permitido(): void
    {
        $gestor = $this->criarGestor();
        $convenio = $this->criarConvenio($gestor->tenant_id);
        Sanctum::actingAs($gestor);

        $this->postJson("/api/convenios/{$convenio->id}/arquivos", $this->payload([
            'arquivo' => UploadedFile::fake()->create('malicioso.php', 10, 'application/x-php'),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('arquivo');
    }

    public function test_rejeita_arquivo_acima_de_20mb(): void
    {
        $gestor = $this->criarGestor();
        $convenio = $this->criarConvenio($gestor->tenant_id);
        Sanctum::actingAs($gestor);

        $this->postJson("/api/convenios/{$convenio->id}/arquivos", $this->payload([
            'arquivo' => UploadedFile::fake()->create('grande.pdf', 20481, 'application/pdf'),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('arquivo');
    }

    public function test_rejeita_tipo_documento_invalido_e_arquivo_ausente(): void
    {
        $gestor = $this->criarGestor();
        $convenio = $this->criarConvenio($gestor->tenant_id);
        Sanctum::actingAs($gestor);

        $this->postJson("/api/convenios/{$convenio->id}/arquivos", [
            'tipo_documento' => 'inexistente',
        ])->assertUnprocessable()->assertJsonValidationErrors('tipo_documento');

        $this->postJson("/api/convenios/{$convenio->id}/arquivos", [
            'tipo_documento' => 'nota_fiscal',
        ])->assertUnprocessable()->assertJsonValidationErrors('arquivo');
    }

    public function test_gestor_nao_remove_mas_admin_remove_preservando_o_arquivo_fisico(): void
    {
        $gestor = $this->criarGestor();
        $convenio = $this->criarConvenio($gestor->tenant_id);
        Sanctum::actingAs($gestor);

        $id = $this->postJson("/api/convenios/{$convenio->id}/arquivos", $this->payload())
            ->json('data.id');
        $caminho = ArquivoConvenio::findOrFail($id)->file_path;

        $this->deleteJson("/api/convenios/{$convenio->id}/arquivos/{$id}")->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->criarAdmin());

        $this->deleteJson("/api/convenios/{$convenio->id}/arquivos/{$id}")->assertNoContent();

        $this->assertSoftDeleted('arquivos_convenio', ['id' => $id]);
        Storage::disk('local')->assertExists($caminho);
    }

    public function test_admin_enviando_em_convenio_de_outro_tenant_herda_o_tenant_do_convenio(): void
    {
        $tenant = $this->criarTenant();
        $convenio = $this->criarConvenio($tenant->id);
        Sanctum::actingAs($this->criarAdmin());

        $id = $this->postJson("/api/convenios/{$convenio->id}/arquivos", $this->payload())
            ->assertCreated()
            ->json('data.id');

        $this->assertSame($tenant->id, ArquivoConvenio::findOrFail($id)->tenant_id);
    }
}
