<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AlertaPrazo;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

/**
 * O sino de alertas é para quem trabalha os prazos de uma prefeitura (Gestor e
 * Fiscal). Sem before() de propósito: o Administrador Interno não pertence a
 * nenhuma prefeitura, então não tem "seus" alertas para ler.
 */
class AlertaPrazoPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::GestorConvenios, UserRole::FiscalControleInterno], true);
    }

    public function view(User $user, AlertaPrazo $alerta): bool
    {
        return $this->viewAny($user) && $this->pertenceAoMesmoTenant($user, $alerta->tenant_id);
    }
}
