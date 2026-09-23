<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

/**
 * Consulta de prefeituras para o Administrador Interno (ex.: escolher em qual
 * município criar um convênio). O cadastro em si continua fora desta API.
 */
#[Middleware('auth:sanctum')]
class TenantController extends Controller
{
    #[Authorize('viewAny', Tenant::class)]
    public function index(): AnonymousResourceCollection
    {
        return TenantResource::collection(
            Tenant::query()->where('active', true)->orderBy('razao_social')->get()
        );
    }
}
