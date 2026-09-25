<?php

use App\Http\Middleware\CabecalhosDeSeguranca;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureDoisFatoresConfigurado;
use App\Http\Middleware\EnsureSenhaDefinitiva;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'conta.ativa' => EnsureAccountIsActive::class,
            'senha.definitiva' => EnsureSenhaDefinitiva::class,
            'dois-fatores' => EnsureDoisFatoresConfigurado::class,
        ]);

        // Atrás do proxy HTTPS (Caddy) o IP e o esquema reais vêm nos cabeçalhos X-Forwarded-*.
        // Sem isto a auditoria gravaria o IP do proxy e as URLs sairiam como http://.
        // Em produção o web-server só é alcançável pelo proxy; ajuste TRUSTED_PROXIES se isso mudar.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));

        $middleware->append(CabecalhosDeSeguranca::class);
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
