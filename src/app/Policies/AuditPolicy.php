<?php

namespace App\Policies;

use App\Models\User;

/**
 * A trilha de auditoria (quem mudou o quê, de onde) cruza todas as
 * prefeituras e traz IP e navegador de quem agiu: só o Administrador
 * Interno consulta. O Fiscal vê o histórico de um convênio na ficha em PDF.
 */
class AuditPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdministradorInterno() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }
}
