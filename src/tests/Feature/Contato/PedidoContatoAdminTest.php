<?php

namespace Tests\Feature\Contato;

use App\Models\ContatoComercial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CriaUsuariosDeTeste;
use Tests\TestCase;

class PedidoContatoAdminTest extends TestCase
{
    use CriaUsuariosDeTeste;
    use RefreshDatabase;

    private function pedido(array $dados = []): ContatoComercial
    {
        $contato = new ContatoComercial(array_merge([
            'nome' => 'Maria Souza',
            'municipio' => 'Exemplópolis/SP',
            'email' => 'maria@exemplopolis.sp.gov.br',
        ], array_diff_key($dados, ['created_at' => 1, 'respondido_em' => 1])));
        $contato->forceFill(['aceite_em' => now(), 'ip' => '203.0.113.7']);
        $contato->forceFill(array_intersect_key($dados, ['created_at' => 1, 'respondido_em' => 1]));
        $contato->save();

        return $contato;
    }

    public function test_admin_lista_os_pedidos_do_mais_novo_para_o_mais_antigo(): void
    {
        $this->pedido(['nome' => 'Antigo', 'created_at' => now()->subDays(5)]);
        $this->pedido(['nome' => 'Novo', 'created_at' => now()]);

        Sanctum::actingAs($this->criarAdmin());

        $this->getJson('/api/contatos')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nome', 'Novo')
            ->assertJsonPath('data.1.nome', 'Antigo')
            ->assertJsonPath('data.0.ip', '203.0.113.7')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_filtros_de_busca_situacao_e_periodo(): void
    {
        $this->pedido(['nome' => 'Ana Lima', 'municipio' => 'Alfa/MG', 'created_at' => now()->subDays(10)]);
        $this->pedido(['nome' => 'Bruno', 'municipio' => 'Beta/SP', 'respondido_em' => now()]);

        Sanctum::actingAs($this->criarAdmin());

        $this->getJson('/api/contatos?busca=ALFA')->assertJsonCount(1, 'data')->assertJsonPath('data.0.nome', 'Ana Lima');
        $this->getJson('/api/contatos?busca=bruno')->assertJsonCount(1, 'data');
        $this->getJson('/api/contatos?situacao=pendente')->assertJsonCount(1, 'data')->assertJsonPath('data.0.nome', 'Ana Lima');
        $this->getJson('/api/contatos?situacao=respondido')->assertJsonCount(1, 'data')->assertJsonPath('data.0.nome', 'Bruno');
        $this->getJson('/api/contatos?de='.now()->subDays(2)->toDateString())->assertJsonCount(1, 'data')->assertJsonPath('data.0.nome', 'Bruno');
        $this->getJson('/api/contatos?situacao=qualquer')->assertUnprocessable();
    }

    public function test_gestor_e_fiscal_nao_acessam_os_pedidos(): void
    {
        $contato = $this->pedido();
        $gestor = $this->criarGestor();
        $fiscal = $this->criarFiscal($gestor->tenant);

        foreach ([$gestor, $fiscal] as $usuario) {
            $this->app['auth']->forgetGuards();
            Sanctum::actingAs($usuario);

            $this->getJson('/api/contatos')->assertForbidden();
            $this->getJson('/api/contatos/exportar')->assertForbidden();
            $this->putJson("/api/contatos/{$contato->id}", ['respondido' => true])->assertForbidden();
            $this->deleteJson("/api/contatos/{$contato->id}")->assertForbidden();
        }

        $this->assertDatabaseCount('contatos_comerciais', 1);
    }

    public function test_sem_login_nao_acessa(): void
    {
        $this->getJson('/api/contatos')->assertUnauthorized();
    }

    public function test_admin_marca_e_desmarca_como_respondido(): void
    {
        $contato = $this->pedido();
        Sanctum::actingAs($this->criarAdmin());

        $this->putJson("/api/contatos/{$contato->id}", ['respondido' => true])
            ->assertOk()
            ->assertJsonPath('data.id', $contato->id);
        $this->assertNotNull($contato->fresh()->respondido_em);

        $this->putJson("/api/contatos/{$contato->id}", ['respondido' => false])->assertOk();
        $this->assertNull($contato->fresh()->respondido_em);

        $this->putJson("/api/contatos/{$contato->id}", [])->assertUnprocessable();
    }

    public function test_admin_exclui_definitivamente_um_pedido(): void
    {
        $contato = $this->pedido();
        Sanctum::actingAs($this->criarAdmin());

        $this->deleteJson("/api/contatos/{$contato->id}")->assertNoContent();

        $this->assertDatabaseMissing('contatos_comerciais', ['id' => $contato->id]);
    }

    public function test_exportacao_csv_respeita_os_filtros_e_neutraliza_formulas(): void
    {
        $this->pedido(['nome' => '=HYPERLINK("http://malicioso")', 'mensagem' => '@cmd']);
        $this->pedido(['nome' => 'Respondido', 'respondido_em' => now()]);

        Sanctum::actingAs($this->criarAdmin());

        $todos = $this->get('/api/contatos/exportar')->assertOk();
        $this->assertStringContainsString('text/csv', $todos->headers->get('Content-Type'));
        $csv = $todos->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'@cmd", $csv);
        $this->assertStringContainsString('Respondido', $csv);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->criarAdmin());
        $pendentes = $this->get('/api/contatos/exportar?situacao=pendente')->streamedContent();

        $this->assertStringContainsString('Pendente', $pendentes);
        $this->assertStringNotContainsString(';Respondido;', $pendentes);
    }
}
