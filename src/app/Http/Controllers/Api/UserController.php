<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Support\Paginacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

/**
 * Contas de Super Administrador não são gerenciadas por esta API (nem listadas, nem editáveis):
 * existem só via `php artisan admin:criar`. O super administrador gerencia as contas de todas as
 * prefeituras; o administrador da prefeitura, só as da própria (UserPolicy + escopo abaixo).
 * Senhas nunca são digitadas por quem gerencia: o sistema gera uma temporária e a mostra uma vez.
 */
#[Middleware('auth:sanctum')]
class UserController extends Controller
{
    public function __construct(private readonly UserService $usuarios) {}

    #[Authorize('viewAny', User::class)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $autor = $request->user();

        $usuarios = User::query()
            ->with('tenant')
            ->where('role', '!=', UserRole::AdministradorInterno)
            // Administrador da prefeitura: sempre a própria prefeitura, qualquer que seja o filtro enviado.
            ->when(! $autor->isAdministradorInterno(), fn ($query) => $query->where('tenant_id', $autor->tenant_id))
            ->when($autor->isAdministradorInterno() && $request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', $request->string('tenant_id')))
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->when($request->filled('busca'), fn ($query) => $query->where(
                fn ($query) => $query
                    ->whereLike('name', '%'.$request->string('busca').'%')
                    ->orWhereLike('email', '%'.$request->string('busca').'%')
            ))
            ->orderBy('name')
            ->paginate(Paginacao::porPagina($request, 15));

        return UserResource::collection($usuarios);
    }

    #[Authorize('create', User::class)]
    public function store(StoreUserRequest $request): JsonResponse
    {
        [$usuario, $senha] = $this->usuarios->criar($request->validated());

        return UserResource::make($usuario->load('tenant'))
            ->additional(['senha_temporaria' => $senha])
            ->response()
            ->setStatusCode(201)
            // A resposta traz a senha temporária: nenhum cache (navegador, proxy) pode guardá-la.
            ->header('Cache-Control', 'no-store');
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
            $this->usuarios->atualizar($user, $request->validated(), $request->user())->load('tenant')
        );
    }

    /** "Redefinir senha": gera outra temporária (mostrada só nesta resposta) e derruba as sessões da pessoa. */
    #[Authorize('redefinirSenha', 'user')]
    public function redefinirSenha(Request $request, User $user): JsonResponse
    {
        abort_if($user->isAdministradorInterno(), 404);

        $senha = $this->usuarios->redefinirParaTemporaria($user, $request->user());

        return UserResource::make($user->load('tenant'))
            ->additional(['senha_temporaria' => $senha])
            ->response()
            ->header('Cache-Control', 'no-store');
    }
}
