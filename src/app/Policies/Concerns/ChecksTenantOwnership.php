<?php

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * Segunda camada de defesa (defense-in-depth) além do TenantScope global:
 * mesmo que uma query escape do scope automático, a Policy ainda barra o
 * acesso caso o registro pertença a outra prefeitura.
 */
trait ChecksTenantOwnership
{
    protected function pertenceAoMesmoTenant(User $user, string $tenantIdDoRegistro): bool
    {
        return $user->tenant_id !== null && $user->tenant_id === $tenantIdDoRegistro;
    }
}
