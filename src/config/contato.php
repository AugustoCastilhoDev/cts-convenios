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
    | Limite de envios do formulário público
    |--------------------------------------------------------------------------
    |
    | Pedidos por hora por endereço IP (toda tentativa conta, até as recusadas).
    | Em produção deixe o padrão; só os testes de ponta a ponta precisam de mais.
    |
    */

    'limite_por_hora' => (int) env('CONTATO_LIMITE_POR_HORA', 5),

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
