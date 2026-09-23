<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

/**
 * Gestão de prefeituras — exclusiva do Administrador Interno (TenantPolicy).
 * Não há exclusão: uma prefeitura sai de operação sendo desativada, o que
 * preserva o histórico exigido pela auditoria.
 */
#[Middleware('auth:sanctum')]
class TenantController extends Controller
{
    /**
     * Por padrão só as ativas (é o que alimenta seletores); `?todas=1` traz
     * também as desativadas, com contagens, para a tela de administração.
     */
    #[Authorize('viewAny', Tenant::class)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $prefeituras = Tenant::query()
            ->when(! $request->boolean('todas'), fn ($query) => $query->where('active', true))
            ->withCount(['usuarios', 'convenios'])
            ->orderBy('razao_social')
            ->get();

        return TenantResource::collection($prefeituras);
    }

    #[Authorize('create', Tenant::class)]
    public function store(StoreTenantRequest $request): TenantResource
    {
        $tenant = new Tenant($request->validated());
        $tenant->active = true;
        $tenant->save();

        return TenantResource::make($tenant->loadCount(['usuarios', 'convenios']));
    }

    #[Authorize('update', 'tenant')]
    public function update(UpdateTenantRequest $request, Tenant $tenant): TenantResource
    {
        $tenant->update($request->validated());

        return TenantResource::make($tenant->loadCount(['usuarios', 'convenios']));
    }
}
