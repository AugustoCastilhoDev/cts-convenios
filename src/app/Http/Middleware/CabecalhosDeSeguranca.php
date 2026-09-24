<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos que fecham as brechas mais comuns de navegador: clickjacking,
 * sniffing de tipo de arquivo, vazamento de URL por referer, e (em HTTPS) a
 * volta para HTTP. A política de conteúdo só vale fora do desenvolvimento local,
 * pois o servidor do Vite (npm run dev) serve scripts de outra porta.
 */
class CabecalhosDeSeguranca
{
    public function handle(Request $request, Closure $next): Response
    {
        $resposta = $next($request);

        $resposta->headers->set('X-Content-Type-Options', 'nosniff');
        $resposta->headers->set('X-Frame-Options', 'DENY');
        $resposta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $resposta->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if ($request->isSecure()) {
            $resposta->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // No ambiente local o Laravel Boost injeta um script de depuração nas páginas, que a
        // política bloquearia; em produção ele não existe.
        if (! app()->environment('local') && ! file_exists(public_path('hot'))) {
            // 'unsafe-inline' só em estilos: o Vue aplica larguras das barras via style="...".
            $resposta->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data:",
                "font-src 'self' data:",
                "connect-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
            ]));
        }

        return $resposta;
    }
}
