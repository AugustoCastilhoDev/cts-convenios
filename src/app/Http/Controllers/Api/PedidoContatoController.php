<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PedidoContato\AtualizarPedidoContatoRequest;
use App\Http\Requests\PedidoContato\ConsultarPedidosContatoRequest;
use App\Http\Resources\PedidoContatoResource;
use App\Models\ContatoComercial;
use App\Services\PedidoContatoService;
use App\Support\Paginacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pedidos de demonstração vindos da landing page — só o Administrador
 * Interno (ContatoComercialPolicy). O envio público é o ContatoController.
 */
#[Middleware('auth:sanctum')]
class PedidoContatoController extends Controller
{
    public function __construct(private readonly PedidoContatoService $pedidos) {}

    #[Authorize('viewAny', ContatoComercial::class)]
    public function index(ConsultarPedidosContatoRequest $request): AnonymousResourceCollection
    {
        $pedidos = ContatoComercial::query()
            ->filtrar($request->validated())
            ->latest()
            ->paginate(Paginacao::porPagina($request, 20));

        return PedidoContatoResource::collection($pedidos);
    }

    #[Authorize('viewAny', ContatoComercial::class)]
    public function exportar(ConsultarPedidosContatoRequest $request): StreamedResponse
    {
        return $this->pedidos->exportarCsv($request->user(), $request->validated());
    }

    #[Authorize('update', 'contato')]
    public function update(AtualizarPedidoContatoRequest $request, ContatoComercial $contato): PedidoContatoResource
    {
        return PedidoContatoResource::make(
            $this->pedidos->marcarRespondido($contato, $request->boolean('respondido')),
        );
    }

    #[Authorize('delete', 'contato')]
    public function destroy(Request $request, ContatoComercial $contato): JsonResponse
    {
        $this->pedidos->excluir($contato, $request->user());

        return response()->json(status: 204);
    }
}
