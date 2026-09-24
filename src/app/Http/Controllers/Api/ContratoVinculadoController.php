<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContratoVinculado\StoreContratoVinculadoRequest;
use App\Http\Requests\ContratoVinculado\UpdateContratoVinculadoRequest;
use App\Http\Resources\ContratoVinculadoResource;
use App\Models\ContratoVinculado;
use App\Models\Convenio;
use App\Services\ContratoVinculadoService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth:sanctum')]
class ContratoVinculadoController extends Controller
{
    public function __construct(private readonly ContratoVinculadoService $contratos) {}

    /**
     * Reaproveita a ConvenioPolicy::view() — se o usuário pode ver o
     * convênio, pode listar os contratos vinculados a ele.
     */
    #[Authorize('view', 'convenio')]
    public function index(Convenio $convenio): AnonymousResourceCollection
    {
        return ContratoVinculadoResource::collection(
            $convenio->contratosVinculados()->paginate()
        );
    }

    /**
     * ContratoVinculadoPolicy::create() recebe o Convenio da rota e valida
     * o tenant — impede lançar contrato no convênio de outra prefeitura.
     */
    #[Authorize('create', [ContratoVinculado::class, 'convenio'])]
    public function store(StoreContratoVinculadoRequest $request, Convenio $convenio): ContratoVinculadoResource
    {
        return ContratoVinculadoResource::make(
            $this->contratos->criar($convenio, $request->validated())
        );
    }

    #[Authorize('view', 'contrato')]
    public function show(Convenio $convenio, ContratoVinculado $contrato): ContratoVinculadoResource
    {
        abort_unless($contrato->convenio_id === $convenio->id, 404);

        return ContratoVinculadoResource::make($contrato);
    }

    #[Authorize('update', 'contrato')]
    public function update(
        UpdateContratoVinculadoRequest $request,
        Convenio $convenio,
        ContratoVinculado $contrato
    ): ContratoVinculadoResource {
        abort_unless($contrato->convenio_id === $convenio->id, 404);

        return ContratoVinculadoResource::make(
            $this->contratos->atualizar($contrato, $request->validated())
        );
    }
}
