<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tokens de acesso expirados (12 h) só ocupam espaço na tabela: limpa os vencidos há mais de um dia.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Motor de Alertas: varredura diária dos prazos dos convênios. Idempotente:
// rodar mais de uma vez no dia não duplica alertas.
// Guarda dos pedidos de contato da landing page (prazo da Política de Privacidade).
Schedule::command('contatos:limpar')->dailyAt('03:00')->timezone(config('alertas.timezone'))->onOneServer();

Schedule::command('alertas:processar')
    ->dailyAt(config('alertas.horario'))
    ->timezone(config('alertas.timezone'))
    ->withoutOverlapping()
    ->onOneServer();
