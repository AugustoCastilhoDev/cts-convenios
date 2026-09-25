<?php

namespace App\Policies;

use App\Models\ArquivoConvenio;
use App\Models\Convenio;
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
        return $user->role->consultaConvenios();
    }

    public function view(User $user, ArquivoConvenio $arquivo): bool
    {
        return $this->viewAny($user) && $this->pertenceAoMesmoTenant($user, $arquivo->tenant_id);
    }

    /**
     * Upload de documentos (Termo de Assinatura, Extrato, Nota Fiscal) é
     * atribuição de quem lança o processo. Recebe o Convenio-pai da rota
     * para exigir que o tenant também bata (mesmo padrão do
     * ContratoVinculadoPolicy::create()).
     */
    public function create(User $user, Convenio $convenio): bool
    {
        return $user->role->editaConvenios()
            && $this->pertenceAoMesmoTenant($user, $convenio->tenant_id);
    }

    public function delete(User $user, ArquivoConvenio $arquivo): bool
    {
        return false;
    }
}
