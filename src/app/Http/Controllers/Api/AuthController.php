<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

#[Middleware('auth:sanctum', except: ['login'])]
class AuthController extends Controller
{
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

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessão encerrada.']);
    }
}
