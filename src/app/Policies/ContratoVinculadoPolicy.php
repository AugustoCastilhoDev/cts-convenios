<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ContratoVinculado;
use App\Models\Convenio;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class ContratoVinculadoPolicy
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

    public function view(User $user, ContratoVinculado $contrato): bool
    {
        return $this->viewAny($user) && $this->pertenceAoMesmoTenant($user, $contrato->tenant_id);
    }

    /**
     * Recebe o Convenio-pai (via rota aninhada /convenios/{convenio}/contratos)
     * para impedir que um Gestor lance um contrato no convênio de OUTRA
     * prefeitura só porque o papel bate — o tenant também precisa bater.
     */
    public function create(User $user, Convenio $convenio): bool
    {
        return $user->role === UserRole::GestorConvenios
            && $this->pertenceAoMesmoTenant($user, $convenio->tenant_id);
    }

    public function update(User $user, ContratoVinculado $contrato): bool
    {
        return $user->role === UserRole::GestorConvenios
            && $this->pertenceAoMesmoTenant($user, $contrato->tenant_id);
    }

    public function delete(User $user, ContratoVinculado $contrato): bool
    {
        return false;
    }
}
