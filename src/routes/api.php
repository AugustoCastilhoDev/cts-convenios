<?php

use App\Http\Controllers\Api\ArquivoConvenioController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContratoVinculadoController;
use App\Http\Controllers\Api\ConvenioController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/me', [AuthController::class, 'me']);

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
