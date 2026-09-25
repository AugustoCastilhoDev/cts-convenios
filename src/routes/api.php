<?php

use App\Http\Controllers\Api\AlertaPrazoController;
use App\Http\Controllers\Api\ArquivoConvenioController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContatoController;
use App\Http\Controllers\Api\ContratoVinculadoController;
use App\Http\Controllers\Api\ConvenioController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DoisFatoresController;
use App\Http\Controllers\Api\NotificacaoController;
use App\Http\Controllers\Api\PedidoContatoController;
use App\Http\Controllers\Api\RedefinicaoSenhaController;
use App\Http\Controllers\Api\RelatorioConvenioController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
// 2º passo do login de quem tem 2FA: o desafio do passo 1 + o código do app.
Route::post('/login/2fa', [AuthController::class, 'loginDoisFatores'])->middleware('throttle:login-2fa');

// Formulário da landing page (público).
Route::post('/contato', [ContatoController::class, 'store'])->middleware('throttle:contato');
Route::post('/logout', [AuthController::class, 'logout']);

// "Esqueci minha senha" (público, com limite de tentativas).
Route::post('/esqueci-senha', [RedefinicaoSenhaController::class, 'solicitar'])->middleware('throttle:esqueci-senha');
Route::post('/redefinir-senha', [RedefinicaoSenhaController::class, 'redefinir'])->middleware('throttle:redefinir-senha');

// Tudo abaixo exige conta ativa (usuário e prefeitura): o middleware barra
// tokens ainda válidos de contas desativadas. Quem está com senha temporária só passa
// por /me e /me/password (senha.definitiva barra o resto).
Route::middleware(['auth:sanctum', 'conta.ativa'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me/password', [AuthController::class, 'alterarSenha']);
});

// Ativação e manejo do 2FA: liberados a quem ainda precisa ativar (dois-fatores fica de fora), mas só depois de a
// senha temporária ter sido trocada. Cada tentativa de senha aqui conta no limite "dois-fatores".
Route::middleware(['auth:sanctum', 'conta.ativa', 'senha.definitiva', 'throttle:dois-fatores'])->prefix('2fa')->group(function () {
    Route::post('/iniciar', [DoisFatoresController::class, 'iniciar']);
    Route::post('/confirmar', [DoisFatoresController::class, 'confirmar']);
    Route::post('/codigos-recuperacao', [DoisFatoresController::class, 'gerarCodigos']);
    Route::delete('/', [DoisFatoresController::class, 'desativar']);
});

Route::middleware(['auth:sanctum', 'conta.ativa', 'senha.definitiva', 'dois-fatores'])->group(function () {

    Route::get('/audits', [AuditController::class, 'index']);
    Route::get('/audits/exportar', [AuditController::class, 'exportar']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Sino de alertas. "lidas" antes de "{alerta}" para não ser lido como um id.
    Route::get('/notificacoes', [NotificacaoController::class, 'index']);
    Route::post('/notificacoes/lidas', [NotificacaoController::class, 'marcarTodasLidas']);
    Route::post('/notificacoes/{alerta}/lida', [NotificacaoController::class, 'marcarLida']);

    // Pedidos de demonstração da landing page (só Administrador Interno). "exportar" antes de "{contato}".
    Route::get('/contatos', [PedidoContatoController::class, 'index']);
    Route::get('/contatos/exportar', [PedidoContatoController::class, 'exportar']);
    Route::put('/contatos/{contato}', [PedidoContatoController::class, 'update']);
    Route::delete('/contatos/{contato}', [PedidoContatoController::class, 'destroy']);

    Route::get('/tenants', [TenantController::class, 'index']);
    Route::post('/tenants', [TenantController::class, 'store']);
    Route::put('/tenants/{tenant}', [TenantController::class, 'update']);

    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::post('/{user}/redefinir-senha', [UserController::class, 'redefinirSenha']);
        Route::post('/{user}/redefinir-2fa', [UserController::class, 'redefinirDoisFatores']);
    });

    Route::prefix('convenios')->group(function () {
        Route::get('/', [ConvenioController::class, 'index']);
        Route::post('/', [ConvenioController::class, 'store']);
        // Antes de /{convenio}, senão "exportar" seria lido como o id de um convênio.
        Route::get('/exportar', [RelatorioConvenioController::class, 'carteira']);
        Route::get('/{convenio}', [ConvenioController::class, 'show']);
        Route::put('/{convenio}', [ConvenioController::class, 'update']);
        Route::delete('/{convenio}', [ConvenioController::class, 'destroy']);

        Route::get('/{convenio}/contratos', [ContratoVinculadoController::class, 'index']);
        Route::post('/{convenio}/contratos', [ContratoVinculadoController::class, 'store']);
        Route::get('/{convenio}/contratos/{contrato}', [ContratoVinculadoController::class, 'show']);
        Route::put('/{convenio}/contratos/{contrato}', [ContratoVinculadoController::class, 'update']);

        Route::get('/{convenio}/ficha', [RelatorioConvenioController::class, 'ficha']);
        Route::get('/{convenio}/alertas', [AlertaPrazoController::class, 'index']);

        Route::get('/{convenio}/arquivos', [ArquivoConvenioController::class, 'index']);
        Route::post('/{convenio}/arquivos', [ArquivoConvenioController::class, 'store']);
        Route::get('/{convenio}/arquivos/{arquivo}/download', [ArquivoConvenioController::class, 'download']);
        Route::delete('/{convenio}/arquivos/{arquivo}', [ArquivoConvenioController::class, 'destroy']);
    });
});
