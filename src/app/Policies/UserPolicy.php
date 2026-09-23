<?php

namespace App\Policies;

use App\Models\User;

/**
 * Gestão de usuários é atribuição exclusiva do Administrador Interno
 * (equipe da plataforma): Gestor e Fiscal não criam nem alteram contas,
 * nem as da própria prefeitura.
 */
class UserPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdministradorInterno() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, User $alvo): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $alvo): bool
    {
        return false;
    }
}
