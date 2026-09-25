<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Destino dos pedidos de contato da landing page
    |--------------------------------------------------------------------------
    |
    | E-mail que recebe um aviso a cada pedido. Vazio = o pedido só é gravado no
    | banco (tabela contatos_comerciais), sem e-mail.
    |
    */

    'destino' => env('CONTATO_DESTINO'),

    /*
    |--------------------------------------------------------------------------
    | Prazo de guarda dos pedidos de contato
    |--------------------------------------------------------------------------
    |
    | Em meses. É o prazo prometido na Política de Privacidade; o comando
    | `contatos:limpar` (diário) apaga o que passar dele. 0 desliga a limpeza.
    |
    */

    'retencao_meses' => (int) env('CONTATO_RETENCAO_MESES', 12),

];
