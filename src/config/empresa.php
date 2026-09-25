<?php

/*
|--------------------------------------------------------------------------
| Dados da empresa (páginas de Privacidade e Termos, rodapé da landing)
|--------------------------------------------------------------------------
|
| Tudo vem do .env.production. Campo vazio aparece nas páginas públicas como
| "[a preencher]" em destaque, para não passar despercebido antes do lançamento.
|
*/

return [

    // Dados públicos do cartão CNPJ (emitido em 12/08/2026); o .env só precisa sobrescrever se mudarem.
    'razao_social' => env('EMPRESA_RAZAO_SOCIAL', 'Castilho Tech Soluções Digitais Ltda'),
    'nome_fantasia' => env('EMPRESA_NOME_FANTASIA', 'Castilho Soluções Digitais'),
    'cnpj' => env('EMPRESA_CNPJ', '68.552.491/0001-17'),
    'endereco' => env('EMPRESA_ENDERECO', 'Rua Bernardino José Fidelis, 142, Três Cruzes, Leopoldina/MG, CEP 36.700-488'),
    'foro' => env('EMPRESA_FORO'),

    // Canal geral e canal do encarregado de dados (LGPD, art. 41).
    'email_contato' => env('EMPRESA_EMAIL_CONTATO'),
    'encarregado_nome' => env('EMPRESA_ENCARREGADO_NOME'),
    'encarregado_email' => env('EMPRESA_ENCARREGADO_EMAIL'),

    // Onde o sistema roda (operador de hospedagem citado na política de privacidade).
    'hospedagem' => env('EMPRESA_HOSPEDAGEM'),

    // false enquanto o texto não passou por revisão jurídica: as páginas mostram um aviso de minuta.
    'texto_revisado' => (bool) env('TEXTO_JURIDICO_REVISADO', false),

];
