<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convenio\ExportarConveniosRequest;
use App\Models\Convenio;
use App\Services\RelatorioConvenioService;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exportações para o Fiscal de Controle Interno (e para o Gestor): quem pode
 * ver os convênios pode exportá-los, sempre dentro da própria prefeitura.
 */
#[Middleware('auth:sanctum')]
class RelatorioConvenioController extends Controller
{
    public function __construct(private readonly RelatorioConvenioService $relatorios) {}

    #[Authorize('viewAny', Convenio::class)]
    public function carteira(ExportarConveniosRequest $request): Response
    {
        $status = $request->validated('status');
        $busca = $request->validated('busca');

        return $request->validated('formato') === 'csv'
            ? $this->relatorios->carteiraCsv($request->user(), $status, $busca)
            : $this->relatorios->carteiraPdf($request->user(), $status, $busca);
    }

    #[Authorize('export', 'convenio')]
    public function ficha(Request $request, Convenio $convenio): Response
    {
        return $this->relatorios->fichaPdf($request->user(), $convenio);
    }
}
