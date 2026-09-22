<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ContratoVinculado */
class ContratoVinculadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'convenio_id' => $this->convenio_id,
            'numero_contrato' => $this->numero_contrato,
            'empresa_contratada' => $this->empresa_contratada,
            'valor_contratado' => (float) $this->valor_contratado,
            'status_execucao' => $this->status_execucao->value,
            'status_execucao_label' => $this->status_execucao->label(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
