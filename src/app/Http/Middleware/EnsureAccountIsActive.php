<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia tokens ainda válidos de usuários desativados ou de prefeituras
 * (tenants) inativas. Roda depois do auth:sanctum, então a checagem vale a
 * cada requisição — desativar uma conta tem efeito imediato.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->active || ($user->tenant_id && ! $user->tenant->active))) {
            return response()->json(['message' => 'Conta desativada. Entre em contato com o suporte.'], 403);
        }

        return $next($request);
    }
}
