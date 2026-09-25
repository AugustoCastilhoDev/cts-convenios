<?php

namespace App\Policies;

use App\Models\User;

/**
 * A trilha de auditoria (quem mudou o quê, de onde) traz IP e navegador de quem agiu. O super
 * administrador consulta tudo; o administrador da prefeitura consulta e exporta só a da própria
 * prefeitura (o filtro está no AuditoriaService). Gestor e Fiscal não: o Fiscal vê o histórico de um
 * convênio na ficha em PDF.
 */
class AuditPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdministradorInterno() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->role->administraPrefeitura();
    }
}
