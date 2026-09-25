<?php

namespace App\Policies;

use App\Models\User;

/**
 * Os pedidos de contato pertencem à plataforma (são de quem ainda nem é
 * cliente) e trazem dados pessoais de visitantes: só o Administrador
 * Interno os vê, marca como respondidos ou exclui.
 */
class ContatoComercialPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdministradorInterno() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function update(User $user): bool
    {
        return false;
    }

    public function delete(User $user): bool
    {
        return false;
    }
}
