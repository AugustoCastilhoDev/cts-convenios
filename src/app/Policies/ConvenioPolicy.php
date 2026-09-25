<?php

namespace App\Policies;

use App\Models\Convenio;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class ConvenioPolicy
{
    use ChecksTenantOwnership;

    /**
     * Administrador Interno tem acesso irrestrito (gestão de servidores,
     * usuários e auditoria cross-tenant). Curto-circuita as demais regras.
     */
    public function before(User $user): ?bool
    {
        return $user->isAdministradorInterno() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->role->consultaConvenios();
    }

    public function view(User $user, Convenio $convenio): bool
    {
        return $this->viewAny($user) && $this->pertenceAoMesmoTenant($user, $convenio->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->role->editaConvenios();
    }

    public function update(User $user, Convenio $convenio): bool
    {
        return $this->create($user) && $this->pertenceAoMesmoTenant($user, $convenio->tenant_id);
    }

    /**
     * Nenhum papel de prefeitura apaga um convênio oficial — apenas o
     * Administrador Interno (via before()), em conformidade com a
     * rastreabilidade exigida pelo TCE.
     */
    public function delete(User $user, Convenio $convenio): bool
    {
        return false;
    }

    /**
     * Exportação de relatórios para auditorias de órgãos de controle —
     * disponível tanto para quem lança dados quanto para o fiscal.
     */
    public function export(User $user, Convenio $convenio): bool
    {
        return $this->view($user, $convenio);
    }
}
