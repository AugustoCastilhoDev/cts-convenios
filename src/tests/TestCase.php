<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // As páginas (landing e shell do sistema) usam @vite. No CI e em máquinas novas o
        // front-end ainda não foi compilado (public/build não existe) e os testes não devem
        // depender disso: o Vite é trocado por um substituto que não lê o manifesto.
        $this->withoutVite();
    }
}
