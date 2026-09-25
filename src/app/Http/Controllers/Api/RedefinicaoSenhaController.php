<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EsqueciSenhaRequest;
use App\Http\Requests\Auth\RedefinirSenhaRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * "Esqueci minha senha": público, com limite de tentativas. Duas regras de segurança:
 *  - o pedido responde SEMPRE a mesma coisa, exista ou não o e-mail (não revela quem tem conta);
 *  - conta desativada (usuário ou prefeitura) não recebe link e não redefine senha.
 */
class RedefinicaoSenhaController extends Controller
{
    private const MENSAGEM_PEDIDO = 'Se este e-mail estiver cadastrado, enviamos um link para criar uma nova senha. Ele vale por 60 minutos.';

    private const MENSAGEM_LINK_INVALIDO = 'Este link é inválido ou expirou. Peça um novo em "Esqueci minha senha".';

    public function __construct(private readonly UserService $usuarios) {}

    public function solicitar(EsqueciSenhaRequest $request): JsonResponse
    {
        $usuario = $this->usuarioAtivo($request->validated('email'));

        if ($usuario) {
            // O broker também limita a um link por minuto por usuário (config/auth.php: throttle).
            Password::sendResetLink(['email' => $usuario->email]);
        }

        return response()->json(['message' => self::MENSAGEM_PEDIDO]);
    }

    public function redefinir(RedefinirSenhaRequest $request): JsonResponse
    {
        $dados = $request->validated();
        $usuario = $this->usuarioAtivo($dados['email']);

        $status = $usuario
            ? Password::reset(
                ['email' => $usuario->email, 'token' => $dados['token'], 'password' => $dados['password']],
                function (User $usuario, string $senha) {
                    $this->usuarios->redefinirPorLink($usuario, $senha);
                    event(new PasswordReset($usuario));
                    Log::info('Senha redefinida pelo link do e-mail', ['user_id' => $usuario->id]);
                },
            )
            : Password::INVALID_USER;

        if ($status !== Password::PASSWORD_RESET) {
            // Token errado, vencido, já usado ou e-mail inexistente: a mesma resposta para todos.
            throw ValidationException::withMessages(['token' => self::MENSAGEM_LINK_INVALIDO]);
        }

        return response()->json(['message' => 'Senha alterada. Entre com a nova senha.']);
    }

    private function usuarioAtivo(string $email): ?User
    {
        $usuario = User::query()->where('email', $email)->first();

        if (! $usuario || ! $usuario->active || ($usuario->tenant_id && ! $usuario->tenant->active)) {
            return null;
        }

        return $usuario;
    }
}
