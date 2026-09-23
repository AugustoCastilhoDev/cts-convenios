<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

/**
 * Contas de Administrador Interno não são gerenciadas por esta API (nem
 * listadas, nem editáveis): existem só via `php artisan admin:criar`.
 * Isso impede escalar privilégio ou desativar a equipe da plataforma.
 */
#[Middleware('auth:sanctum')]
class UserController extends Controller
{
    public function __construct(private readonly UserService $usuarios) {}

    #[Authorize('viewAny', User::class)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $usuarios = User::query()
            ->with('tenant')
            ->where('role', '!=', UserRole::AdministradorInterno)
            ->when($request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', $request->string('tenant_id')))
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->when($request->filled('busca'), fn ($query) => $query->where(
                fn ($query) => $query
                    ->whereLike('name', '%'.$request->string('busca').'%')
                    ->orWhereLike('email', '%'.$request->string('busca').'%')
            ))
            ->orderBy('name')
            ->paginate($request->integer('por_pagina', 15));

        return UserResource::collection($usuarios);
    }

    #[Authorize('create', User::class)]
    public function store(StoreUserRequest $request): UserResource
    {
        return UserResource::make(
            $this->usuarios->criar($request->validated())->load('tenant')
        );
    }

    #[Authorize('view', 'user')]
    public function show(User $user): UserResource
    {
        abort_if($user->isAdministradorInterno(), 404);

        return UserResource::make($user->load('tenant'));
    }

    #[Authorize('update', 'user')]
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        abort_if($user->isAdministradorInterno(), 404);

        return UserResource::make(
            $this->usuarios->atualizar($user, $request->validated())->load('tenant')
        );
    }
}
