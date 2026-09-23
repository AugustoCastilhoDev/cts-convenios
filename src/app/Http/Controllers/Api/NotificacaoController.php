<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificacaoResource;
use App\Models\AlertaPrazo;
use App\Services\NotificacaoAlertaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

/**
 * Sino de alertas: lista os alertas de prazo vigentes da prefeitura do usuário
 * e registra o que ele já leu. Somente Gestor e Fiscal (AlertaPrazoPolicy).
 */
#[Middleware('auth:sanctum')]
class NotificacaoController extends Controller
{
    public function __construct(private readonly NotificacaoAlertaService $notificacoes) {}

    #[Authorize('viewAny', AlertaPrazo::class)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $resultado = $this->notificacoes->listar($request->user());

        return NotificacaoResource::collection($resultado['itens'])
            ->additional(['meta' => ['nao_lidas' => $resultado['nao_lidas']]]);
    }

    #[Authorize('view', 'alerta')]
    public function marcarLida(Request $request, AlertaPrazo $alerta): Response
    {
        $this->notificacoes->marcarLida($request->user(), $alerta);

        return response()->noContent();
    }

    #[Authorize('viewAny', AlertaPrazo::class)]
    public function marcarTodasLidas(Request $request): Response
    {
        $this->notificacoes->marcarTodasLidas($request->user());

        return response()->noContent();
    }
}
