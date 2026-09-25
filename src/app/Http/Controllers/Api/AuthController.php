<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AlterarSenhaRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

#[Middleware('auth:sanctum', except: ['login'])]
class AuthController extends Controller
{
    public function __construct(private readonly UserService $usuarios) {}

    /**
     * Emite um token de acesso pessoal (Sanctum) para uso na API/SPA.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::once($credentials)) {
            throw ValidationException::withMessages([
                'email' => __('As credenciais informadas não conferem.'),
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->active || ($user->tenant_id && ! $user->tenant->active)) {
            throw ValidationException::withMessages([
                'email' => __('Conta desativada. Entre em contato com o suporte.'),
            ]);
        }

        // Só depois de a senha conferir: quem não a conhece não descobre nada sobre a conta.
        if ($user->senhaTemporariaExpirada()) {
            throw ValidationException::withMessages([
                'email' => __('A senha temporária venceu. Peça a um administrador para gerar outra ou use "Esqueci minha senha".'),
            ]);
        }

        $token = $user->createToken($request->string('device_name')->toString());

        return response()->json([
            'user' => $user->load('tenant'),
            'token' => $token->plainTextToken,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('tenant'));
    }

    /**
     * Vale para qualquer perfil (inclusive o Administrador Interno, que não é
     * gerenciado por /api/users): exige a senha atual e revoga os outros tokens.
     */
    public function alterarSenha(AlterarSenhaRequest $request): JsonResponse
    {
        $this->usuarios->alterarSenha($request->user(), $request->validated('password'));

        return response()->json(['message' => 'Senha alterada. Os outros dispositivos foram desconectados.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessão encerrada.']);
    }
}
