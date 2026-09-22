<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ArquivoConvenio;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class ArquivoConvenioPolicy
{
    use ChecksTenantOwnership;

    public function before(User $user): ?bool
    {
        return $user->isAdministradorInterno() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::GestorConvenios, UserRole::FiscalControleInterno], true);
    }

    public function view(User $user, ArquivoConvenio $arquivo): bool
    {
        return $this->viewAny($user) && $this->pertenceAoMesmoTenant($user, $arquivo->tenant_id);
    }

    /**
     * Upload de documentos (Termo de Assinatura, Extrato, Nota Fiscal) é
     * atribuição de quem lança o processo.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::GestorConvenios;
    }

    public function delete(User $user, ArquivoConvenio $arquivo): bool
    {
        return false;
    }
}
