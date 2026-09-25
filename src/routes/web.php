<?php

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Estas páginas são "sem estado": quem autentica é o token da API, então não há sessão, nem
// cookie, nem token CSRF. Sem isto, cada visita gravaria uma sessão no banco e enviaria dois
// cookies — desperdício, e ainda obrigaria a landing page a ter aviso de cookies.
$semEstado = [
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
];

// Página pública (landing): HTML puro, rápido e indexável, sem o pacote do Vue.
Route::view('/', 'landing')->name('landing')->withoutMiddleware($semEstado);

// Documentos legais: também públicos, indexáveis e sem estado.
Route::view('/privacidade', 'privacidade')->name('privacidade')->withoutMiddleware($semEstado);
Route::view('/termos', 'termos')->name('termos')->withoutMiddleware($semEstado);

// O sistema (SPA Vue) mora em /app: qualquer caminho abaixo dele cai no mesmo shell,
// e o Vue Router decide a tela. A API continua em /api.
Route::view('/app/{any?}', 'app')->where('any', '.*')->name('app')->withoutMiddleware($semEstado);

// Endereços antigos (antes do sistema ir para /app): quem guardou nos favoritos continua entrando.
Route::redirect('/login', '/app/login');
Route::get('/convenios/{any?}', fn (?string $any = null) => redirect('/app/convenios'.($any ? "/{$any}" : '')))->where('any', '.*');
Route::get('/admin/{any?}', fn (?string $any = null) => redirect('/app/admin'.($any ? "/{$any}" : '')))->where('any', '.*');
