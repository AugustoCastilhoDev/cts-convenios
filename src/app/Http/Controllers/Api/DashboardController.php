<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Convenio;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

/**
 * Indicadores financeiros e de prazos: quem pode listar convênios (Gestor,
 * Fiscal, Administrador Interno) pode ver o painel consolidado.
 */
#[Middleware('auth:sanctum')]
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    #[Authorize('viewAny', Convenio::class)]
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->dashboard->resumo()]);
    }
}
