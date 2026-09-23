<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\ConsultarAuditoriaRequest;
use App\Http\Resources\AuditResource;
use App\Models\ArquivoConvenio;
use App\Models\ContratoVinculado;
use App\Models\Convenio;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use OwenIt\Auditing\Models\Audit;

/**
 * Consulta da trilha de auditoria (somente leitura, só Administrador
 * Interno — AuditPolicy).
 */
#[Middleware('auth:sanctum')]
class AuditController extends Controller
{
    /**
     * Nome amigável do tipo de registro -> classe gravada em auditable_type.
     *
     * @var array<string, class-string>
     */
    public const TIPOS = [
        'convenio' => Convenio::class,
        'contrato' => ContratoVinculado::class,
        'arquivo' => ArquivoConvenio::class,
        'prefeitura' => Tenant::class,
        'usuario' => User::class,
    ];

    #[Authorize('viewAny', Audit::class)]
    public function index(ConsultarAuditoriaRequest $request): AnonymousResourceCollection
    {
        $filtros = $request->validated();

        $auditorias = Audit::query()
            ->with('user')
            ->when($filtros['tipo'] ?? null, fn ($query, $tipo) => $query->where('auditable_type', self::TIPOS[$tipo]))
            ->when($filtros['registro_id'] ?? null, fn ($query, $id) => $query->where('auditable_id', $id))
            ->when($filtros['user_id'] ?? null, fn ($query, $id) => $query->where('user_id', $id))
            ->when($filtros['evento'] ?? null, fn ($query, $evento) => $query->where('event', $evento))
            ->when($filtros['de'] ?? null, fn ($query, $data) => $query->where('created_at', '>=', $data.' 00:00:00'))
            ->when($filtros['ate'] ?? null, fn ($query, $data) => $query->where('created_at', '<=', $data.' 23:59:59'))
            ->latest('created_at')
            ->latest('id')
            ->paginate($request->integer('por_pagina', 25));

        return AuditResource::collection($auditorias);
    }
}
