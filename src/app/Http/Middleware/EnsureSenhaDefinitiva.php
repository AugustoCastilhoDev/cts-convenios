<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quem entrou com uma senha temporária (conta nova ou senha redefinida por um administrador)
 * só consegue ver o próprio perfil, trocar a senha e sair: todo o resto responde 403 com o
 * código "senha_temporaria", que a tela usa para levar a pessoa à troca. As rotas liberadas
 * (/me e /me/password) ficam fora deste middleware em routes/api.php.
 */
class EnsureSenhaDefinitiva
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password) {
            return response()->json([
                'message' => 'Defina uma nova senha para continuar.',
                'codigo' => 'senha_temporaria',
            ], 403);
        }

        return $next($request);
    }
}
