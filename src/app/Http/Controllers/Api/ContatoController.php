<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contato\StoreContatoRequest;
use App\Services\ContatoComercialService;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint público (sem login) do formulário da landing page.
 */
class ContatoController extends Controller
{
    public function __construct(private readonly ContatoComercialService $contatos) {}

    public function store(StoreContatoRequest $request): JsonResponse
    {
        // Robô preencheu o campo escondido: responde como se tivesse dado certo, sem gravar nada.
        if ($request->filled('website')) {
            return response()->json(['message' => 'Recebemos seu contato.'], 201);
        }

        $this->contatos->registrar($request->safe()->only(['nome', 'cargo', 'municipio', 'email', 'telefone', 'mensagem']), $request->ip());

        return response()->json(['message' => 'Recebemos seu contato. Retornaremos em breve.'], 201);
    }
}
