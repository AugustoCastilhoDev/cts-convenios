<?php

namespace Tests\Feature\Contato;

use Tests\TestCase;

class PaginasLegaisTest extends TestCase
{
    public function test_privacidade_e_termos_sao_publicos_e_indexaveis(): void
    {
        $this->get('/privacidade')
            ->assertOk()
            ->assertSee('Política de Privacidade')
            ->assertSee('rel="canonical"', false)
            ->assertDontSee('noindex', false);

        $this->get('/termos')
            ->assertOk()
            ->assertSee('Termos de Uso')
            ->assertDontSee('noindex', false);
    }

    public function test_paginas_mostram_a_identificacao_da_empresa(): void
    {
        $this->get('/privacidade')
            ->assertSee('Castilho Tech Soluções Digitais Ltda')
            ->assertSee('68.552.491/0001-17')
            ->assertSee('Leopoldina/MG');
    }

    public function test_dado_nao_informado_aparece_destacado_como_a_preencher(): void
    {
        config(['empresa.encarregado_email' => null]);

        $this->get('/privacidade')->assertSee('encarregado email: a preencher');

        config(['empresa.encarregado_email' => 'privacidade@exemplo.com.br']);

        $this->get('/privacidade')
            ->assertSee('privacidade@exemplo.com.br')
            ->assertDontSee('encarregado email: a preencher');
    }

    public function test_aviso_de_minuta_some_so_depois_da_revisao_juridica(): void
    {
        config(['empresa.texto_revisado' => false]);
        $this->get('/termos')->assertSee('Minuta em revisão');

        config(['empresa.texto_revisado' => true]);
        $this->get('/termos')->assertDontSee('Minuta em revisão');
    }

    public function test_a_landing_liga_o_aceite_e_o_rodape_aos_documentos(): void
    {
        $this->get('/')
            ->assertSee('href="/privacidade"', false)
            ->assertSee('href="/termos"', false)
            ->assertSee('68.552.491/0001-17');
    }
}
