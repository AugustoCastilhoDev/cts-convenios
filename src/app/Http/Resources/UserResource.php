<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'tenant_id' => $this->tenant_id,
            'tenant' => $this->whenLoaded('tenant', fn () => [
                'id' => $this->tenant->id,
                'razao_social' => $this->tenant->razao_social,
            ]),
            'active' => $this->active,
            // Ainda usa a senha temporária: a lista de usuários mostra quem não criou a própria senha.
            'must_change_password' => $this->must_change_password,
            'senha_temporaria_expira_em' => $this->must_change_password ? $this->senha_temporaria_expira_em : null,
            'senha_temporaria_expirada' => $this->senhaTemporariaExpirada(),
            // Só o indicador: o segredo e os códigos nunca saem do servidor depois da ativação.
            'two_factor_ativo' => $this->doisFatoresAtivo(),
            'two_factor_obrigatorio' => $this->exigeDoisFatores(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
