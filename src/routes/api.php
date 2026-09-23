<?php

use App\Http\Controllers\Api\ArquivoConvenioController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContratoVinculadoController;
use App\Http\Controllers\Api\ConvenioController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

// Tudo abaixo exige conta ativa (usuário e prefeitura): o middleware barra
// tokens ainda válidos de contas desativadas.
Route::middleware(['auth:sanctum', 'conta.ativa'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);

    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
    });

    Route::prefix('convenios')->group(function () {
        Route::get('/', [ConvenioController::class, 'index']);
        Route::post('/', [ConvenioController::class, 'store']);
        Route::get('/{convenio}', [ConvenioController::class, 'show']);
        Route::put('/{convenio}', [ConvenioController::class, 'update']);
        Route::delete('/{convenio}', [ConvenioController::class, 'destroy']);

        Route::get('/{convenio}/contratos', [ContratoVinculadoController::class, 'index']);
        Route::post('/{convenio}/contratos', [ContratoVinculadoController::class, 'store']);
        Route::get('/{convenio}/contratos/{contrato}', [ContratoVinculadoController::class, 'show']);
        Route::put('/{convenio}/contratos/{contrato}', [ContratoVinculadoController::class, 'update']);

        Route::get('/{convenio}/arquivos', [ArquivoConvenioController::class, 'index']);
        Route::post('/{convenio}/arquivos', [ArquivoConvenioController::class, 'store']);
        Route::get('/{convenio}/arquivos/{arquivo}/download', [ArquivoConvenioController::class, 'download']);
        Route::delete('/{convenio}/arquivos/{arquivo}', [ArquivoConvenioController::class, 'destroy']);
    });
});
