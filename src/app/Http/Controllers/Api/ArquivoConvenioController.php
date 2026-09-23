<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ArquivoConvenio\StoreArquivoConvenioRequest;
use App\Http\Resources\ArquivoConvenioResource;
use App\Models\ArquivoConvenio;
use App\Models\Convenio;
use App\Services\ArquivoConvenioService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Middleware('auth:sanctum')]
class ArquivoConvenioController extends Controller
{
    public function __construct(private readonly ArquivoConvenioService $arquivos) {}

    /**
     * Reaproveita a ConvenioPolicy::view() — quem vê o convênio, lista
     * seus arquivos.
     */
    #[Authorize('view', 'convenio')]
    public function index(Convenio $convenio): AnonymousResourceCollection
    {
        return ArquivoConvenioResource::collection(
            $convenio->arquivos()->latest()->paginate()
        );
    }

    #[Authorize('create', [ArquivoConvenio::class, 'convenio'])]
    public function store(StoreArquivoConvenioRequest $request, Convenio $convenio): ArquivoConvenioResource
    {
        return ArquivoConvenioResource::make(
            $this->arquivos->armazenar(
                $convenio,
                $request->user(),
                $request->file('arquivo'),
                $request->validated('tipo_documento'),
            )
        );
    }

    #[Authorize('view', 'arquivo')]
    public function download(Convenio $convenio, ArquivoConvenio $arquivo): StreamedResponse
    {
        abort_unless($arquivo->convenio_id === $convenio->id, 404);

        return $this->arquivos->baixar($arquivo);
    }

    #[Authorize('delete', 'arquivo')]
    public function destroy(Convenio $convenio, ArquivoConvenio $arquivo): Response
    {
        abort_unless($arquivo->convenio_id === $convenio->id, 404);

        $this->arquivos->remover($arquivo);

        return response()->noContent();
    }
}
