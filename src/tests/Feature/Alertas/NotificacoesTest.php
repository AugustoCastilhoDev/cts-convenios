<?php

namespace Tests\Feature\Alertas;

use App\Enums\StatusConvenio;
use App\Enums\TipoPrazo;
use App\Models\AlertaPrazo;
use App\Models\Convenio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class NotificacoesTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Meio-dia UTC = 09:00 em Brasília: "hoje" é 2026-09-23 nos dois fusos.
        $this->travelTo('2026-09-23 12:00:00');
    }

    private function convenio(string $tenantId, string $vigencia = '2026-10-08', array $sobrescreve = []): Convenio
    {
        return Convenio::forceCreate(array_merge([
            'tenant_id' => $tenantId,
            'numero_convenio' => fake()->unique()->numerify('######/2026'),
            'orgao_concedente' => 'Ministério da Saúde',
            'objeto' => 'Aquisição de equipamentos',
            'valor_repasse' => 1000,
            'valor_contrapartida' => 200,
            'status' => StatusConvenio::EmExecucao,
            'data_vigencia_fim' => $vigencia,
        ], $sobrescreve));
    }

    private function alerta(Convenio $convenio, int $marco, array $sobrescreve = []): AlertaPrazo
    {
        return AlertaPrazo::forceCreate(array_merge([
            'tenant_id' => $convenio->tenant_id,
            'convenio_id' => $convenio->id,
            'tipo_prazo' => TipoPrazo::VigenciaFim,
            'marco_dias' => $marco,
            'data_prazo' => $convenio->data_vigencia_fim,
        ], $sobrescreve));
    }

    public function test_lista_os_alertas_da_prefeitura_com_dias_de_hoje_e_conta_os_nao_lidos(): void
    {
        $gestor = $this->criarGestor();
        $convenio = $this->convenio($gestor->tenant_id, '2026-10-08');
        $this->alerta($convenio, 15);

        // De outra prefeitura: nunca aparece.
        $this->alerta($this->convenio($this->criarTenant()->id), 15);

        Sanctum::actingAs($gestor);

        $this->getJson('/api/notificacoes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.nao_lidas', 1)
            ->assertJsonPath('data.0.numero_convenio', $convenio->numero_convenio)
            ->assertJsonPath('data.0.tipo_prazo', 'vigencia_fim')
            ->assertJsonPath('data.0.dias', 15)
            ->assertJsonPath('data.0.lida', false);

        // Uma semana depois o mesmo alerta mostra 8 dias, não os 15 de quando foi gerado.
        $this->travelTo('2026-09-30 12:00:00');
        $this->getJson('/api/notificacoes')->assertJsonPath('data.0.dias', 8);
    }

    public function test_mostra_so_o_alerta_mais_recente_de_cada_prazo(): void
    {
        $gestor = $this->criarGestor();
        $convenio = $this->convenio($gestor->tenant_id, '2026-10-08');

        $this->travelTo('2026-09-10 12:00:00');
        $this->alerta($convenio, 30);
        $this->travelTo('2026-09-23 12:00:00');
        $recente = $this->alerta($convenio, 15);

        Sanctum::actingAs($gestor);

        $this->getJson('/api/notificacoes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $recente->id)
            ->assertJsonPath('meta.nao_lidas', 1);
    }

    public function test_ignora_alerta_cancelado_prazo_alterado_e_convenio_fora_dos_status_monitorados(): void
    {
        $gestor = $this->criarGestor();
        $t = $gestor->tenant_id;

        $cancelado = $this->convenio($t);
        $this->alerta($cancelado, 15, ['cancelado_em' => now()]);

        // Vigência foi prorrogada depois do alerta: o alerta antigo está desatualizado.
        $prorrogado = $this->convenio($t, '2026-12-31');
        $this->alerta($prorrogado, 15, ['data_prazo' => '2026-10-08']);

        // Vigência deixa de valer quando o convênio entra em prestação de contas.
        $emPrestacao = $this->convenio($t, '2026-10-08', ['status' => StatusConvenio::PrestacaoContas]);
        $this->alerta($emPrestacao, 15);

        $valido = $this->convenio($t);
        $this->alerta($valido, 15);

        Sanctum::actingAs($gestor);

        $this->getJson('/api/notificacoes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.convenio_id', $valido->id);
    }

    public function test_leitura_e_individual_e_marcar_uma_ou_todas_reduz_o_contador(): void
    {
        $gestor = $this->criarGestor();
        $fiscal = $this->criarFiscal($gestor->tenant);
        $a = $this->alerta($this->convenio($gestor->tenant_id), 15);
        $this->alerta($this->convenio($gestor->tenant_id, '2026-10-20'), 30);

        Sanctum::actingAs($gestor);
        $this->postJson("/api/notificacoes/{$a->id}/lida")->assertNoContent();
        $this->getJson('/api/notificacoes')->assertJsonPath('meta.nao_lidas', 1);

        // O Fiscal ainda não leu nenhum.
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($fiscal);
        $this->getJson('/api/notificacoes')->assertJsonPath('meta.nao_lidas', 2);

        $this->postJson('/api/notificacoes/lidas')->assertNoContent();
        $this->getJson('/api/notificacoes')
            ->assertJsonPath('meta.nao_lidas', 0)
            ->assertJsonCount(2, 'data');

        // Ler de novo não duplica nem falha.
        $this->postJson("/api/notificacoes/{$a->id}/lida")->assertNoContent();
        $this->assertDatabaseCount('alerta_prazo_leituras', 3);
    }

    public function test_nao_le_alerta_de_outra_prefeitura(): void
    {
        $gestor = $this->criarGestor();
        $alheio = $this->alerta($this->convenio($this->criarTenant()->id), 15);

        Sanctum::actingAs($gestor);

        $this->postJson("/api/notificacoes/{$alheio->id}/lida")->assertNotFound();
        $this->assertDatabaseCount('alerta_prazo_leituras', 0);
    }

    public function test_administrador_nao_tem_sino_e_visitante_nao_acessa(): void
    {
        $this->getJson('/api/notificacoes')->assertUnauthorized();

        Sanctum::actingAs($this->criarAdmin());

        $this->getJson('/api/notificacoes')->assertForbidden();
        $this->postJson('/api/notificacoes/lidas')->assertForbidden();
    }
}
