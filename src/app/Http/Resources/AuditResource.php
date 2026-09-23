<?php

namespace App\Http\Resources;

use App\Http\Controllers\Api\AuditController;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OwenIt\Auditing\Models\Audit;

/** @mixin Audit */
class AuditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tipo = array_search($this->auditable_type, AuditController::TIPOS, true);

        return [
            'id' => $this->id,
            'evento' => $this->event,
            'tipo' => $tipo === false ? class_basename($this->auditable_type) : $tipo,
            'registro_id' => $this->auditable_id,
            'usuario' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'valores_antigos' => $this->old_values,
            'valores_novos' => $this->new_values,
            'ip' => $this->ip_address,
            'created_at' => $this->created_at,
        ];
    }
}
