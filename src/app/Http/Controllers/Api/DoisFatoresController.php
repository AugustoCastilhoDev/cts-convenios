<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmarDoisFatoresRequest;
use App\Http\Requests\Auth\ConfirmarSenhaRequest;
use App\Http\Requests\Auth\DesativarDoisFatoresRequest;
use App\Http\Resources\AtivacaoDoisFatoresResource;
use App\Http\Resources\CodigosDeRecuperacaoResource;
use App\Services\DoisFatoresService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Validation\ValidationException;

/**
 * Verificação em duas etapas da própria pessoa logada. Tudo o que entrega um segredo ou códigos vai com
 * Cache-Control: no-store (navegador e proxy não guardam) e só depois de a senha ser digitada de novo.
 */
#[Middleware('auth:sanctum')]
class DoisFatoresController extends Controller
{
    public function __construct(
        private readonly DoisFatoresService $doisFatores,
        private readonly UserService $usuarios,
    ) {}

    /** 1º passo: gera o segredo (QR Code e chave para digitar). O 2FA só passa a valer na confirmação. */
    public function iniciar(ConfirmarSenhaRequest $request): JsonResponse
    {
        $ativacao = $this->doisFatores->iniciar($request->user());

        return $this->semCache(AtivacaoDoisFatoresResource::make($ativacao));
    }

    /** 2º passo: o primeiro código do app confirma a ativação e entrega os códigos de recuperação (uma vez). */
    public function confirmar(ConfirmarDoisFatoresRequest $request): JsonResponse
    {
        $codigos = $this->doisFatores->confirmar($request->user(), $request->validated('codigo'));
        // Quem ativou agora vale mais do que qualquer outra sessão aberta com a senha antiga.
        $this->usuarios->revogarOutrosTokens($request->user());

        return $this->semCache(CodigosDeRecuperacaoResource::make($codigos));
    }

    /** Novos códigos de recuperação (os antigos deixam de valer). Só com o 2FA ativo. */
    public function gerarCodigos(ConfirmarSenhaRequest $request): JsonResponse
    {
        abort_unless($request->user()->doisFatoresAtivo(), 422, 'A verificação em duas etapas não está ativa.');

        return $this->semCache(CodigosDeRecuperacaoResource::make($this->doisFatores->gerarCodigosDeRecuperacao($request->user())));
    }

    /** Desligar exige senha e um código atual, e não é permitido a quem o perfil obriga a usar 2FA. */
    public function desativar(DesativarDoisFatoresRequest $request): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario->exigeDoisFatores()) {
            throw ValidationException::withMessages(['dois_fatores' => 'A verificação em duas etapas é obrigatória para o seu perfil.']);
        }

        if (! $this->doisFatores->verificar($usuario, $request->validated('codigo'))) {
            throw ValidationException::withMessages(['codigo' => 'Código inválido ou vencido.']);
        }

        $this->doisFatores->desativar($usuario);
        $this->usuarios->revogarOutrosTokens($usuario);

        return response()->json(['message' => 'Verificação em duas etapas desativada.']);
    }

    private function semCache(JsonResource $recurso): JsonResponse
    {
        return $recurso->response()->header('Cache-Control', 'no-store');
    }
}
