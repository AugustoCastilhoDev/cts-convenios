<?php

use Illuminate\Support\Facades\Route;

// Página pública (landing): HTML puro, rápido e indexável, sem o pacote do Vue.
Route::view('/', 'landing')->name('landing');

// O sistema (SPA Vue) mora em /app: qualquer caminho abaixo dele cai no mesmo shell,
// e o Vue Router decide a tela. A API continua em /api.
Route::view('/app/{any?}', 'app')->where('any', '.*')->name('app');

// Endereços antigos (antes do sistema ir para /app): quem guardou nos favoritos continua entrando.
Route::redirect('/login', '/app/login');
Route::get('/convenios/{any?}', fn (?string $any = null) => redirect('/app/convenios'.($any ? "/{$any}" : '')))->where('any', '.*');
Route::get('/admin/{any?}', fn (?string $any = null) => redirect('/app/admin'.($any ? "/{$any}" : '')))->where('any', '.*');
