<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

/**
 * Cadastro de municípios (tenants) é gestão da própria plataforma SaaS:
 * só o Administrador Interno cria, edita ou desativa uma prefeitura.
 * Usuários de uma prefeitura só podem consultar o próprio registro.
 */
class TenantPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdministradorInterno() ? true : null;
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return false;
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return false;
    }
}
