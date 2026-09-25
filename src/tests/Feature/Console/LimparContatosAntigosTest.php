<?php

namespace Tests\Feature\Console;

use App\Models\ContatoComercial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LimparContatosAntigosTest extends TestCase
{
    use RefreshDatabase;

    private function pedido(string $nome, $criadoEm): void
    {
        $contato = new ContatoComercial(['nome' => $nome, 'municipio' => 'Exemplópolis/SP', 'email' => 'a@b.gov.br']);
        $contato->forceFill(['aceite_em' => now(), 'created_at' => $criadoEm, 'updated_at' => $criadoEm])->save();
    }

    public function test_apaga_so_os_pedidos_alem_do_prazo_de_guarda(): void
    {
        config(['contato.retencao_meses' => 12]);
        $this->pedido('Velho', now()->subMonths(13));
        $this->pedido('Recente', now()->subMonths(11));

        $this->artisan('contatos:limpar')->expectsOutputToContain('1 pedido(s)')->assertSuccessful();

        $this->assertDatabaseMissing('contatos_comerciais', ['nome' => 'Velho']);
        $this->assertDatabaseHas('contatos_comerciais', ['nome' => 'Recente']);
    }

    public function test_prazo_zero_desliga_a_limpeza(): void
    {
        config(['contato.retencao_meses' => 0]);
        $this->pedido('Velho', now()->subYears(3));

        $this->artisan('contatos:limpar')->expectsOutputToContain('desligado')->assertSuccessful();

        $this->assertDatabaseCount('contatos_comerciais', 1);
    }

    public function test_opcao_meses_sobrescreve_o_prazo_configurado(): void
    {
        config(['contato.retencao_meses' => 12]);
        $this->pedido('Dois meses', now()->subMonths(2));

        $this->artisan('contatos:limpar --meses=1')->assertSuccessful();

        $this->assertDatabaseCount('contatos_comerciais', 0);
    }
}
