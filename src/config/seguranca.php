<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validade da senha temporária
    |--------------------------------------------------------------------------
    |
    | Em dias. A senha que o sistema gera ao criar uma conta (ou em "Redefinir
    | senha") só vale até este prazo: depois, o login é recusado e um
    | administrador precisa gerar outra. Ela passou pelas mãos de outra pessoa,
    | então não deve ficar valendo indefinidamente.
    |
    */

    'senha_temporaria_dias' => max(1, (int) env('SENHA_TEMPORARIA_VALIDADE_DIAS', 7)),

    /*
    |--------------------------------------------------------------------------
    | 2FA obrigatório para os administradores
    |--------------------------------------------------------------------------
    |
    | Super administrador e administrador da prefeitura só usam o sistema depois
    | de ativar a verificação em duas etapas (app autenticador). Para os demais
    | perfis ela é opcional. Só desligue em desenvolvimento e nos testes de
    | ponta a ponta (o .env.example já vem com false); em produção fica ligado.
    |
    */

    'dois_fatores_obrigatorio' => filter_var(env('DOIS_FATORES_OBRIGATORIO', true), FILTER_VALIDATE_BOOLEAN),

];
