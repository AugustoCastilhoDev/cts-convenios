<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convenio\StoreConvenioRequest;
use App\Http\Requests\Convenio\UpdateConvenioRequest;
use App\Http\Resources\ConvenioResource;
use App\Models\Convenio;
use App\Services\ConvenioService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth:sanctum')]
class ConvenioController extends Controller
{
    public function __construct(private readonly ConvenioService $convenios) {}

    #[Authorize('viewAny', Convenio::class)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $convenios = Convenio::query()
            ->withSum('contratosVinculados as total_contratado', 'valor_contratado')
            ->filtrar($request->input('status'), $request->input('busca'))
            ->orderByDesc('data_vigencia_fim')
            ->paginate($request->integer('por_pagina', 15));

        return ConvenioResource::collection($convenios);
    }

    #[Authorize('create', Convenio::class)]
    public function store(StoreConvenioRequest $request): ConvenioResource
    {
        $convenio = $this->convenios->criar($request->user(), $request->validated());

        return ConvenioResource::make(
            $convenio->loadSum('contratosVinculados as total_contratado', 'valor_contratado')
        );
    }

    #[Authorize('view', 'convenio')]
    public function show(Convenio $convenio): ConvenioResource
    {
        return ConvenioResource::make(
            $convenio->loadSum('contratosVinculados as total_contratado', 'valor_contratado')
                ->load('contratosVinculados')
        );
    }

    #[Authorize('update', 'convenio')]
    public function update(UpdateConvenioRequest $request, Convenio $convenio): ConvenioResource
    {
        $convenio = $this->convenios->atualizar($convenio, $request->validated());

        return ConvenioResource::make(
            $convenio->loadSum('contratosVinculados as total_contratado', 'valor_contratado')
        );
    }

    /**
     * Nenhum papel de prefeitura chega aqui (ConvenioPolicy::delete() nega
     * geral) — só o Administrador Interno passa, via before() da Policy.
     */
    #[Authorize('delete', 'convenio')]
    public function destroy(Convenio $convenio): Response
    {
        $this->convenios->remover($convenio);

        return response()->noContent();
    }
}
