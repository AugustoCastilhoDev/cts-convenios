<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;

class UserService
{
    /**
     * tenant_id e role fora do #[Fillable] do User: são atribuídos aqui,
     * depois de validados pelo Form Request.
     */
    public function criar(array $dados): User
    {
        $usuario = new User(Arr::only($dados, ['name', 'email', 'password']));
        $usuario->forceFill([
            'tenant_id' => $dados['tenant_id'],
            'role' => $dados['role'],
            'active' => true,
        ])->save();

        return $usuario;
    }

    /**
     * Desativar a conta ou trocar a senha revoga todos os tokens emitidos:
     * o efeito é imediato, sem esperar o token expirar.
     */
    public function atualizar(User $usuario, array $dados): User
    {
        $usuario->fill(Arr::only($dados, ['name', 'email', 'password']));
        $usuario->forceFill(Arr::only($dados, ['role', 'active']));

        $revogarTokens = $usuario->isDirty('password')
            || ($usuario->isDirty('active') && ! $usuario->active);

        $usuario->save();

        if ($revogarTokens) {
            $usuario->tokens()->delete();
        }

        return $usuario;
    }
}
