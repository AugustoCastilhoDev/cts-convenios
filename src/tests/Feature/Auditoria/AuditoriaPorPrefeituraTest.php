<?php

namespace Tests\Feature\Auditoria;

use App\Enums\StatusConvenio;
use App\Models\Convenio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OwenIt\Auditing\Models\Audit;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

/** Cada prefeitura consulta e exporta só a própria auditoria; o super administrador vê todas. */
class AuditoriaPorPrefeituraTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // O pacote não audita nada em console/PHPUnit por padrão.
        config(['audit.console' => true]);
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
            'status' => StatusConvenio::Proposta,
        ], $sobrescreve));
    }

    public function test_cada_registro_de_auditoria_guarda_a_prefeitura_de_origem(): void
    {
        $tenant = $this->criarTenant();
        $convenio = $this->convenio($tenant->id);
        $gestor = $this->criarGestor($tenant);

        $this->assertSame($tenant->id, $convenio->audits()->first()->tenant_id);
        $this->assertSame($tenant->id, $gestor->audits()->first()->tenant_id);
        // O registro da própria prefeitura pertence a ela mesma.
        $tenant->update(['razao_social' => 'Prefeitura Renomeada']);
        $this->assertSame($tenant->id, $tenant->audits()->latest('id')->first()->tenant_id);
        // O super administrador não pertence a nenhuma prefeitura.
        $this->assertNull($this->criarAdmin()->audits()->first()?->tenant_id);
    }

    public function test_administrador_da_prefeitura_ve_so_a_auditoria_da_propria_prefeitura(): void
    {
        $admin = $this->criarAdminDaPrefeitura();
        $meu = $this->convenio($admin->tenant_id);
        $deOutra = $this->convenio($this->criarTenant()->id);

        Sanctum::actingAs($admin);

        $ids = collect($this->getJson('/api/audits?tipo=convenio')->assertOk()->json('data'))->pluck('registro_id');

        $this->assertTrue($ids->contains($meu->id));
        $this->assertFalse($ids->contains($deOutra->id));

        // Pedir o registro de outra prefeitura diretamente não devolve nada.
        $this->getJson("/api/audits?registro_id={$deOutra->id}")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_o_super_administrador_ve_a_auditoria_de_todas_as_prefeituras(): void
    {
        $a = $this->convenio($this->criarTenant()->id);
        $b = $this->convenio($this->criarTenant()->id);

        Sanctum::actingAs($this->criarAdmin());

        $ids = collect($this->getJson('/api/audits?tipo=convenio&por_pagina=100')->json('data'))->pluck('registro_id');
        $this->assertTrue($ids->contains($a->id));
        $this->assertTrue($ids->contains($b->id));
    }

    public function test_gestor_e_fiscal_nao_consultam_nem_exportam_a_auditoria(): void
    {
        $tenant = $this->criarTenant();

        foreach ([$this->criarGestor($tenant), $this->criarFiscal($tenant)] as $usuario) {
            $this->app['auth']->forgetGuards();
            Sanctum::actingAs($usuario);

            $this->getJson('/api/audits')->assertForbidden();
            $this->getJson('/api/audits/exportar')->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/audits/exportar')->assertUnauthorized();
    }

    public function test_exportacao_traz_so_a_propria_prefeitura_com_rotulos_legiveis(): void
    {
        $admin = $this->criarAdminDaPrefeitura();
        $this->convenio($admin->tenant_id, ['numero_convenio' => 'MEU-001/2026']);
        $this->convenio($this->criarTenant()->id, ['numero_convenio' => 'ESTRANHO-999/2026']);

        Sanctum::actingAs($admin);

        $resposta = $this->get('/api/audits/exportar')->assertOk();
        $this->assertStringContainsString('text/csv', $resposta->headers->get('Content-Type'));
        $csv = $resposta->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Prefeitura;Usuário;"E-mail do usuário";Evento;"Tipo de registro"', $csv);
        $this->assertStringContainsString($admin->tenant->razao_social, $csv);
        $this->assertStringNotContainsString('Prefeitura: '.$admin->tenant_id, $csv);
        $this->assertStringContainsString('MEU-001/2026', $csv);
        $this->assertStringContainsString('Criado', $csv);
        $this->assertStringContainsString('Número: MEU-001/2026', $csv);
        $this->assertStringNotContainsString('ESTRANHO-999/2026', $csv);
    }

    public function test_exportacao_respeita_os_filtros_da_tela(): void
    {
        $admin = $this->criarAdminDaPrefeitura();
        $this->convenio($admin->tenant_id, ['numero_convenio' => 'CONVENIO-A/2026']);

        Sanctum::actingAs($admin);

        $soUsuarios = $this->get('/api/audits/exportar?tipo=usuario')->streamedContent();
        $this->assertStringNotContainsString('CONVENIO-A/2026', $soUsuarios);

        $todos = $this->get('/api/audits/exportar')->streamedContent();
        $this->assertStringContainsString('CONVENIO-A/2026', $todos);

        $this->getJson('/api/audits/exportar?tipo=inexistente')->assertUnprocessable();
    }

    public function test_exportacao_neutraliza_formulas_digitadas_por_usuarios(): void
    {
        $admin = $this->criarAdminDaPrefeitura();
        $this->convenio($admin->tenant_id, ['objeto' => '=HYPERLINK("http://malicioso")']);

        Sanctum::actingAs($admin);

        $csv = $this->get('/api/audits/exportar?tipo=convenio')->streamedContent();

        // O texto da coluna "Alterações" começa com o rótulo do campo, então a fórmula fica no meio do texto e
        // a célula não começa com "="; o nome do usuário, que poderia, é neutralizado.
        $this->assertDoesNotMatchRegularExpression('/;=HYPERLINK/', $csv);
        $linhas = array_filter(explode("\n", $csv), fn ($linha) => str_contains($linha, 'Objeto:'));
        $this->assertNotEmpty($linhas);
    }

    public function test_a_exportacao_vem_do_mesmo_filtro_de_prefeitura_que_a_tela(): void
    {
        $admin = $this->criarAdminDaPrefeitura();
        $this->convenio($admin->tenant_id);
        $this->convenio($this->criarTenant()->id);

        Sanctum::actingAs($admin);

        $naTela = $this->getJson('/api/audits?por_pagina=200')->json('meta.total');
        $linhas = count(array_filter(explode("\n", $this->get('/api/audits/exportar')->streamedContent()))) - 1; // menos o cabeçalho

        $this->assertSame($naTela, $linhas);
        $this->assertSame(Audit::where('tenant_id', $admin->tenant_id)->count(), $naTela);
    }
}
