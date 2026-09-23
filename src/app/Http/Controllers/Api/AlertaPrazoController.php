<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AlertaPrazoResource;
use App\Models\Convenio;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

/**
 * Histórico dos alertas de um convênio (somente leitura): quem pode ver o
 * convênio vê os alertas gerados para ele — útil em prestações ao TCE.
 */
#[Middleware('auth:sanctum')]
class AlertaPrazoController extends Controller
{
    #[Authorize('view', 'convenio')]
    public function index(Convenio $convenio): AnonymousResourceCollection
    {
        return AlertaPrazoResource::collection(
            $convenio->alertas()->latest()->paginate()
        );
    }
}
