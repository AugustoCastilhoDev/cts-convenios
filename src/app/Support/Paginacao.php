<?php

namespace App\Support;

use Illuminate\Http\Request;

class Paginacao
{
    /** Teto de registros por página: o Kanban pede 200; nada legítimo precisa de mais. */
    public const MAXIMO_POR_PAGINA = 200;

    /**
     * Lê `por_pagina` da requisição limitado entre 1 e o teto, para uma URL
     * como ?por_pagina=1000000 não carregar a tabela inteira na memória.
     */
    public static function porPagina(Request $request, int $padrao): int
    {
        return max(1, min($request->integer('por_pagina', $padrao), self::MAXIMO_POR_PAGINA));
    }
}
