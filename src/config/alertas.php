<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Régua de alertas (dias restantes até o prazo)
    |--------------------------------------------------------------------------
    |
    | Um alerta sai quando faltam N dias (ou menos, se o agendador ficou
    | parado) para um dos marcos abaixo. Cada marco é enviado uma única vez
    | por prazo. Depois do prazo, sai um único aviso de "prazo vencido".
    |
    */

    'marcos' => [90, 60, 30, 15],

    /*
    |--------------------------------------------------------------------------
    | Janela de prazo vencido
    |--------------------------------------------------------------------------
    |
    | Só avisa "vencido" se o prazo passou há no máximo N dias. Evita disparar
    | alertas antigos ao importar convênios legados já encerrados.
    |
    */

    'janela_vencido_dias' => 30,

    /*
    |--------------------------------------------------------------------------
    | Fuso e horário da varredura diária
    |--------------------------------------------------------------------------
    |
    | Os prazos são datas (sem hora) do calendário brasileiro: a contagem de
    | dias usa este fuso, independente do APP_TIMEZONE (UTC).
    |
    */

    'timezone' => 'America/Sao_Paulo',

    'horario' => '07:00',

    /*
    |--------------------------------------------------------------------------
    | Redirecionar todos os alertas para um único e-mail
    |--------------------------------------------------------------------------
    |
    | Use em desenvolvimento e homologação: os dados de teste têm e-mails
    | fictícios, e enviar para eles gera rejeições que prejudicam a reputação
    | do domínio remetente. Em produção, deixe vazio.
    |
    */

    'redirecionar_para' => env('ALERTAS_REDIRECIONAR_PARA'),

];
