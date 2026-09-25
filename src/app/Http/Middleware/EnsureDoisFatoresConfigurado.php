<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Super administrador e administrador da prefeitura só usam o sistema depois de ativar o 2FA: enquanto
 * não ativam, a API só libera o perfil, a troca de senha, a ativação do 2FA e a saída (403 com o código
 * "2fa_obrigatorio", que a tela usa para levar a pessoa à ativação). Essas rotas ficam fora deste
 * middleware em routes/api.php.
 */
class EnsureDoisFatoresConfigurado
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->precisaConfigurarDoisFatores()) {
            return response()->json([
                'message' => 'Ative a verificação em duas etapas para continuar.',
                'codigo' => '2fa_obrigatorio',
            ], 403);
        }

        return $next($request);
    }
}
