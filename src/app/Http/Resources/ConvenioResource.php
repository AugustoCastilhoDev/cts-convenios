<?php

namespace App\Http\Resources;

use App\Models\Convenio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Convenio */
class ConvenioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'numero_convenio' => $this->numero_convenio,
            'orgao_concedente' => $this->orgao_concedente,
            'objeto' => $this->objeto,
            'valor_repasse' => (float) $this->valor_repasse,
            'valor_contrapartida' => (float) $this->valor_contrapartida,
            // total_contratado vem de withSum()/loadSum() no Controller — evita
            // reconsultar o banco por linha (N+1) que o accessor do Model faria.
            'total_contratado' => (float) ($this->total_contratado ?? 0),
            'saldo_disponivel' => (float) $this->valor_repasse + (float) $this->valor_contrapartida
                - (float) ($this->total_contratado ?? 0),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_cor_kanban' => $this->status->corKanban(),
            'data_assinatura' => $this->data_assinatura?->toDateString(),
            'data_vigencia_fim' => $this->data_vigencia_fim?->toDateString(),
            'dias_para_vencimento' => $this->dias_para_vencimento,
            'prazo_prestacao_contas' => $this->prazo_prestacao_contas?->toDateString(),
            'contratos_vinculados' => ContratoVinculadoResource::collection($this->whenLoaded('contratosVinculados')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
