<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AlterarSenhaRequest;
use App\Http\Requests\Auth\DesafioDoisFatoresRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\DoisFatoresService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

#[Middleware('auth:sanctum', except: ['login', 'loginDoisFatores'])]
class AuthController extends Controller
{
    public function __construct(
        private readonly UserService $usuarios,
        private readonly DoisFatoresService $doisFatores,
    ) {}

    /**
     * Confere e-mail e senha. Sem 2FA, emite o token de acesso pessoal (Sanctum) para a SPA/API. Com 2FA
     * ativo NÃO emite token: devolve um desafio curto que só o código do app (POST /login/2fa) completa.
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

        $this->garantirContaAtiva($user);

        // Só depois de a senha conferir: quem não a conhece não descobre nada sobre a conta.
        if ($user->senhaTemporariaExpirada()) {
            throw ValidationException::withMessages([
                'email' => __('A senha temporária venceu. Peça a um administrador para gerar outra ou use "Esqueci minha senha".'),
            ]);
        }

        $dispositivo = $request->string('device_name')->toString();

        if ($user->doisFatoresAtivo()) {
            return response()->json([
                'dois_fatores' => true,
                'desafio' => $this->doisFatores->criarDesafio($user, $dispositivo),
            ]);
        }

        return $this->emitirToken($user, $dispositivo);
    }

    /**
     * 2º passo do login de quem tem 2FA: o desafio recebido no passo 1 + um código do app (ou de
     * recuperação). Cinco tentativas por desafio e dez erros por pessoa a cada 10 minutos.
     */
    public function loginDoisFatores(DesafioDoisFatoresRequest $request): JsonResponse
    {
        ['usuario' => $user, 'dispositivo' => $dispositivo] = $this->doisFatores->resolverDesafio($request->validated('desafio'));

        $limite = '2fa:'.$user->id;

        if (RateLimiter::tooManyAttempts($limite, 10)) {
            abort(429, 'Muitas tentativas de verificação. Aguarde alguns minutos e tente novamente.');
        }

        if (! $this->doisFatores->verificar($user, $request->validated('codigo'))) {
            RateLimiter::hit($limite, 600);

            throw ValidationException::withMessages(['codigo' => __('Código inválido ou vencido.')]);
        }

        RateLimiter::clear($limite);
        $this->doisFatores->encerrarDesafio($request->validated('desafio'));
        // A conta pode ter sido desativada nos minutos entre a senha e o código.
        $this->garantirContaAtiva($user);

        return $this->emitirToken($user, $dispositivo);
    }

    private function garantirContaAtiva(User $user): void
    {
        if (! $user->active || ($user->tenant_id && ! $user->tenant->active)) {
            throw ValidationException::withMessages([
                'email' => __('Conta desativada. Entre em contato com o suporte.'),
            ]);
        }
    }

    private function emitirToken(User $user, string $dispositivo): JsonResponse
    {
        $token = $user->createToken($dispositivo);

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
