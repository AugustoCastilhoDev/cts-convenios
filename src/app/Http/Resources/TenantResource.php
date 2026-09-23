<?php

namespace App\Http\Resources;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tenant */
class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'razao_social' => $this->razao_social,
            'cnpj' => $this->cnpj,
            'active' => $this->active,
            'usuarios_count' => $this->whenCounted('usuarios'),
            'convenios_count' => $this->whenCounted('convenios'),
        ];
    }
}
