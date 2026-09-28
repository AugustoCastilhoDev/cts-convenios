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

    // Foro dos Termos de Uso (revisado com o texto jurídico em 2026-09-28).
    'foro' => env('EMPRESA_FORO', 'Comarca de Leopoldina, Estado de Minas Gerais'),

    // Canal geral e canal do encarregado de dados (LGPD, art. 41). Domínio "portalctsconvenios.com.br"
    // decidido em 2026-09-28; os e-mails só existem depois que o domínio for registrado e o
    // roteamento de e-mail (Cloudflare) configurado — ver ROADMAP.md.
    'email_contato' => env('EMPRESA_EMAIL_CONTATO', 'contato@portalctsconvenios.com.br'),
    'encarregado_nome' => env('EMPRESA_ENCARREGADO_NOME', 'Augusto Corrêa Castilho'),
    'encarregado_email' => env('EMPRESA_ENCARREGADO_EMAIL', 'dpo@portalctsconvenios.com.br'),

    // Onde o sistema roda (operador de hospedagem citado na política de privacidade).
    'hospedagem' => env('EMPRESA_HOSPEDAGEM', 'Hostinger (Hostinger International Ltd.)'),

    // false enquanto o texto não passou por revisão jurídica: as páginas mostram um aviso de minuta.
    'texto_revisado' => (bool) env('TEXTO_JURIDICO_REVISADO', false),

];
