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

];
