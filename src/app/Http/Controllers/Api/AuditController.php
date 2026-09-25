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
use App\Services\AuditoriaService;
use App\Support\Paginacao;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use OwenIt\Auditing\Models\Audit;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Consulta e exportação da trilha de auditoria (somente leitura — AuditPolicy): o super administrador
 * vê tudo; o administrador da prefeitura, só a própria prefeitura (AuditoriaService).
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

    public function __construct(private readonly AuditoriaService $auditoria) {}

    #[Authorize('viewAny', Audit::class)]
    public function index(ConsultarAuditoriaRequest $request): AnonymousResourceCollection
    {
        $auditorias = $this->auditoria
            ->consultar($request->validated(), $request->user())
            ->paginate(Paginacao::porPagina($request, 25));

        return AuditResource::collection($auditorias);
    }

    #[Authorize('viewAny', Audit::class)]
    public function exportar(ConsultarAuditoriaRequest $request): StreamedResponse
    {
        return $this->auditoria->exportarCsv($request->validated(), $request->user());
    }
}
