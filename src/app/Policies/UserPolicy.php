<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

/**
 * Quem gerencia contas: o super administrador (todas as prefeituras) e o administrador da prefeitura
 * (só as contas da própria prefeitura, e nunca a de um super administrador). Gestor e Fiscal não
 * criam nem alteram contas, nem as da própria prefeitura.
 */
class UserPolicy
{
    use ChecksTenantOwnership;

    public function before(User $user): ?bool
    {
        return $user->isAdministradorInterno() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->role->administraPrefeitura();
    }

    public function view(User $user, User $alvo): bool
    {
        return $this->gerenciaConta($user, $alvo);
    }

    public function create(User $user): bool
    {
        return $user->role->administraPrefeitura();
    }

    public function update(User $user, User $alvo): bool
    {
        return $this->gerenciaConta($user, $alvo);
    }

    /** Botão "Redefinir senha": as contas dos outros. Quem esquece a própria senha usa o link do e-mail. */
    public function redefinirSenha(User $user, User $alvo): bool
    {
        return $this->gerenciaConta($user, $alvo) && ! $alvo->is($user);
    }

    private function gerenciaConta(User $user, User $alvo): bool
    {
        return $user->role->administraPrefeitura()
            && ! $alvo->isAdministradorInterno()
            && $this->pertenceAoMesmoTenant($user, (string) $alvo->tenant_id);
    }
}
