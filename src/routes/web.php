<?php

use Illuminate\Support\Facades\Route;

// SPA Vue: qualquer rota que não seja da API cai no mesmo shell; quem decide
// a tela é o Vue Router.
Route::view('/{any?}', 'app')->where('any', '^(?!api/).*$');
