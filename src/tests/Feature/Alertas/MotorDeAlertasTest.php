<?php

namespace Tests\Feature\Alertas;

use App\Enums\StatusConvenio;
use App\Jobs\EnviarAlertaPrazo;
use App\Mail\AlertaPrazoConvenio;
use App\Models\AlertaPrazo;
use App\Models\Convenio;
use App\Services\AlertaPrazoService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class MotorDeAlertasTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Meio-dia UTC = 09:00 em Brasília: "hoje" é 2026-09-23 nos dois fusos.
        $this->travelTo('2026-09-23 12:00:00');
        config(['alertas.redirecionar_para' => null]);
    }

    private function hojeMais(int $dias): string
    {
        return CarbonImmutable::today('America/Sao_Paulo')->addDays($dias)->toDateString();
    }

    private function criarConvenio(string $tenantId, array $sobrescreve = []): Convenio
    {
        return Convenio::forceCreate(array_merge([
            'tenant_id' => $tenantId,
            'numero_convenio' => fake()->unique()->numerify('######/2026'),
            'orgao_concedente' => 'Ministério da Saúde',
            'objeto' => 'Aquisição de equipamentos',
            'valor_repasse' => 500000,
            'valor_contrapartida' => 50000,
            'status' => StatusConvenio::EmExecucao,
        ], $sobrescreve));
    }

    private function alertas(): AlertaPrazoService
    {
        return app(AlertaPrazoService::class);
    }

    /**
     * @return array<string, array{int, int|null}>
     */
    public static function marcosPorDias(): array
    {
        return [
            'muito longe' => [120, null],
            'logo depois do primeiro marco' => [91, null],
            'exato 90' => [90, 90],
            'entre 90 e 60' => [61, 90],
            'exato 60' => [60, 60],
            'atrasado do 60' => [45, 60],
            'exato 30' => [30, 30],
            'entre 30 e 15' => [16, 30],
            'exato 15' => [15, 15],
            'vence hoje' => [0, 15],
            'vencido ontem' => [-1, -1],
            'vencido no limite da janela' => [-30, -1],
            'vencido antigo' => [-31, null],
        ];
    }

    #[DataProvider('marcosPorDias')]
    public function test_marco_aplicavel_para_cada_quantidade_de_dias(int $dias, ?int $esperado): void
    {
        $this->assertSame($esperado, $this->alertas()->marcoAplicavel($dias));
    }

    public function test_registra_um_unico_alerta_por_marco_mesmo_rodando_varias_vezes(): void
    {
        Queue::fake();
        $convenio = $this->criarConvenio($this->criarTenant()->id, ['data_vigencia_fim' => $this->hojeMais(30)]);

        $primeira = $this->alertas()->processar();
        $segunda = $this->alertas()->processar();

        $this->assertSame(1, $primeira['criados']);
        $this->assertSame(0, $segunda['criados']);
        $this->assertSame(1, AlertaPrazo::count());

        $alerta = AlertaPrazo::firstOrFail();
        $this->assertSame($convenio->id, $alerta->convenio_id);
        $this->assertSame(30, $alerta->marco_dias);
        $this->assertSame($convenio->tenant_id, $alerta->tenant_id);
        Queue::assertPushed(EnviarAlertaPrazo::class);
    }

    public function test_alerta_atrasado_sai_no_marco_mais_proximo(): void
    {
        Queue::fake();
        $this->criarConvenio($this->criarTenant()->id, ['data_vigencia_fim' => $this->hojeMais(20)]);

        $this->alertas()->processar();

        $this->assertSame(30, AlertaPrazo::firstOrFail()->marco_dias);
    }

    public function test_prazo_vencido_recentemente_gera_aviso_unico_e_o_antigo_e_ignorado(): void
    {
        Queue::fake();
        $tenant = $this->criarTenant();
        $this->criarConvenio($tenant->id, ['data_vigencia_fim' => $this->hojeMais(-5)]);
        $this->criarConvenio($tenant->id, ['data_vigencia_fim' => $this->hojeMais(-40)]);

        $this->alertas()->processar();
        $this->alertas()->processar();

        $this->assertSame(1, AlertaPrazo::count());
        $this->assertSame(AlertaPrazoService::MARCO_VENCIDO, AlertaPrazo::firstOrFail()->marco_dias);
    }

    public function test_ignora_convenio_fora_da_janela_de_90_dias(): void
    {
        Queue::fake();
        $this->criarConvenio($this->criarTenant()->id, ['data_vigencia_fim' => $this->hojeMais(100)]);

        $this->assertSame(0, $this->alertas()->processar()['criados']);
    }

    public function test_respeita_o_status_de_cada_tipo_de_prazo(): void
    {
        Queue::fake();
        $tenantId = $this->criarTenant()->id;

        // Em prestação de contas a vigência já terminou de propósito: só vale o prazo de contas.
        $this->criarConvenio($tenantId, [
            'status' => StatusConvenio::PrestacaoContas,
            'data_vigencia_fim' => $this->hojeMais(10),
            'prazo_prestacao_contas' => $this->hojeMais(60),
        ]);
        // Finalizado nunca alerta.
        $this->criarConvenio($tenantId, [
            'status' => StatusConvenio::Finalizado,
            'data_vigencia_fim' => $this->hojeMais(10),
            'prazo_prestacao_contas' => $this->hojeMais(10),
        ]);

        $this->alertas()->processar();

        $alertas = AlertaPrazo::all();
        $this->assertCount(1, $alertas);
        $this->assertSame('prestacao_contas', $alertas[0]->tipo_prazo->value);
        $this->assertSame(60, $alertas[0]->marco_dias);
    }

    public function test_ignora_prefeitura_inativa(): void
    {
        Queue::fake();
        $tenant = $this->criarTenant(['active' => false]);
        $this->criarConvenio($tenant->id, ['data_vigencia_fim' => $this->hojeMais(30)]);

        $this->assertSame(0, $this->alertas()->processar()['criados']);
    }

    public function test_envia_apenas_para_gestores_e_fiscais_ativos_da_prefeitura_do_convenio(): void
    {
        Mail::fake();
        $gestor = $this->criarGestor();
        $fiscal = $this->criarFiscal($gestor->tenant);
        $inativo = $this->criarGestor($gestor->tenant);
        $inativo->forceFill(['active' => false])->save();
        $outraPrefeitura = $this->criarGestor();
        $admin = $this->criarAdmin();
        $this->criarConvenio($gestor->tenant_id, ['data_vigencia_fim' => $this->hojeMais(30)]);

        $this->alertas()->processar();

        Mail::assertSentCount(1);
        Mail::assertSent(AlertaPrazoConvenio::class, function (AlertaPrazoConvenio $mail) use ($gestor, $fiscal, $inativo, $outraPrefeitura, $admin) {
            return $mail->hasTo($gestor->email)
                && $mail->hasTo($fiscal->email)
                && ! $mail->hasTo($inativo->email)
                && ! $mail->hasTo($outraPrefeitura->email)
                && ! $mail->hasTo($admin->email);
        });

        $alerta = AlertaPrazo::firstOrFail();
        $this->assertNotNull($alerta->enviado_em);
        $this->assertSame(2, $alerta->destinatarios);
    }

    public function test_alerta_ja_enviado_nao_e_reenviado_nas_proximas_varreduras(): void
    {
        Mail::fake();
        $gestor = $this->criarGestor();
        $this->criarConvenio($gestor->tenant_id, ['data_vigencia_fim' => $this->hojeMais(30)]);

        $this->alertas()->processar();
        $this->alertas()->processar();
        $this->alertas()->processar();

        Mail::assertSentCount(1);
    }

    public function test_alerta_sem_destinatarios_fica_pendente_e_sai_quando_houver_alguem(): void
    {
        Mail::fake();
        $tenant = $this->criarTenant();
        $this->criarConvenio($tenant->id, ['data_vigencia_fim' => $this->hojeMais(30)]);

        $this->alertas()->processar();

        Mail::assertNothingSent();
        $this->assertNull(AlertaPrazo::firstOrFail()->enviado_em);

        $this->criarGestor($tenant);
        $this->alertas()->processar();

        Mail::assertSentCount(1);
        $this->assertNotNull(AlertaPrazo::firstOrFail()->enviado_em);
    }

    public function test_alerta_obsoleto_e_cancelado_em_vez_de_enviado(): void
    {
        Queue::fake();
        Mail::fake();
        $gestor = $this->criarGestor();
        $convenio = $this->criarConvenio($gestor->tenant_id, ['data_vigencia_fim' => $this->hojeMais(30)]);
        $this->alertas()->processar();

        // O prazo foi prorrogado antes de o job rodar.
        $convenio->forceFill(['data_vigencia_fim' => $this->hojeMais(200)])->save();
        (new EnviarAlertaPrazo(AlertaPrazo::firstOrFail()->id))->handle($this->alertas());

        Mail::assertNothingSent();
        $alerta = AlertaPrazo::firstOrFail();
        $this->assertNotNull($alerta->cancelado_em);
        $this->assertNull($alerta->enviado_em);
    }

    public function test_prazo_alterado_reinicia_a_regua_com_novos_alertas(): void
    {
        Queue::fake();
        $convenio = $this->criarConvenio($this->criarTenant()->id, ['data_vigencia_fim' => $this->hojeMais(30)]);
        $this->alertas()->processar();

        $convenio->forceFill(['data_vigencia_fim' => $this->hojeMais(60)])->save();
        $this->alertas()->processar();

        $this->assertSame(2, AlertaPrazo::count());
        $this->assertEqualsCanonicalizing([30, 60], AlertaPrazo::pluck('marco_dias')->all());
    }

    public function test_redirecionamento_envia_so_para_o_email_configurado_e_marca_como_teste(): void
    {
        Mail::fake();
        config(['alertas.redirecionar_para' => 'dev@exemplo.com.br']);
        $gestor = $this->criarGestor();
        $this->criarConvenio($gestor->tenant_id, ['data_vigencia_fim' => $this->hojeMais(30)]);

        $this->alertas()->processar();

        Mail::assertSent(AlertaPrazoConvenio::class, function (AlertaPrazoConvenio $mail) use ($gestor) {
            return $mail->hasTo('dev@exemplo.com.br')
                && ! $mail->hasTo($gestor->email)
                && str_starts_with($mail->envelope()->subject, '[TESTE]')
                && $mail->destinatariosReais === [$gestor->email];
        });
    }

    public function test_conteudo_do_email_de_alerta_e_de_prazo_vencido(): void
    {
        $tenant = $this->criarTenant();
        $convenio = $this->criarConvenio($tenant->id, ['numero_convenio' => '777/2026', 'data_vigencia_fim' => $this->hojeMais(30)]);
        $this->criarGestor($tenant);
        Queue::fake();
        $this->alertas()->processar();
        $alerta = AlertaPrazo::firstOrFail();

        $aviso = new AlertaPrazoConvenio($alerta, 30);
        $aviso->assertHasSubject('Convênio 777/2026: Fim da vigência em 30 dias');
        $aviso->assertSeeInHtml('Faltam 30 dias para o prazo');
        $aviso->assertSeeInHtml('23/10/2026');
        $aviso->assertSeeInText($tenant->razao_social);

        $vencido = new AlertaPrazoConvenio($alerta, -5);
        $vencido->assertHasSubject('URGENTE: prazo vencido — Convênio 777/2026');
        $vencido->assertSeeInHtml('CADIN');
    }

    public function test_comando_dry_run_lista_sem_gravar_e_o_comando_normal_registra(): void
    {
        Queue::fake();
        $this->criarConvenio($this->criarTenant()->id, ['numero_convenio' => '555/2026', 'data_vigencia_fim' => $this->hojeMais(15)]);

        $this->artisan('alertas:processar', ['--dry-run' => true])
            ->expectsOutputToContain('555/2026')
            ->expectsOutputToContain('1 alerta(s) seriam gerados')
            ->assertSuccessful();
        $this->assertSame(0, AlertaPrazo::count());

        $this->artisan('alertas:processar')
            ->expectsOutputToContain('Alertas novos: 1')
            ->assertSuccessful();
        $this->assertSame(1, AlertaPrazo::count());
    }

    public function test_varredura_esta_agendada_diariamente(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('alertas:processar')
            ->assertSuccessful();
    }

    public function test_historico_de_alertas_respeita_isolamento_e_papeis(): void
    {
        Queue::fake();
        $gestor = $this->criarGestor();
        $fiscal = $this->criarFiscal($gestor->tenant);
        $convenio = $this->criarConvenio($gestor->tenant_id, ['data_vigencia_fim' => $this->hojeMais(30)]);
        $this->alertas()->processar();

        Sanctum::actingAs($fiscal);
        $this->getJson("/api/convenios/{$convenio->id}/alertas")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.marco', 30)
            ->assertJsonPath('data.0.situacao', 'pendente')
            ->assertJsonPath('data.0.tipo_prazo', 'vigencia_fim');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->criarGestor());
        $this->getJson("/api/convenios/{$convenio->id}/alertas")->assertNotFound();
    }
}
