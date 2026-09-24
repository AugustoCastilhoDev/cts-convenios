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
        // total_contratado vem de withSum()/loadSum() no Controller — evita
        // reconsultar o banco por linha (N+1) que o accessor do Model faria.
        $contratado = (float) ($this->total_contratado ?? 0);
        $valorTotal = (float) $this->valor_repasse + (float) $this->valor_contrapartida;

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'numero_convenio' => $this->numero_convenio,
            'orgao_concedente' => $this->orgao_concedente,
            'objeto' => $this->objeto,
            'secretaria' => $this->secretaria?->value,
            'secretaria_label' => $this->secretaria?->label(),
            'valor_repasse' => (float) $this->valor_repasse,
            'valor_contrapartida' => (float) $this->valor_contrapartida,
            // Soma dos contratos vinculados. "total_contratado" é o nome antigo, mantido
            // porque as telas já em uso dependem dele.
            'valor_contratado' => $contratado,
            'total_contratado' => $contratado,
            // Quanto do valor total (repasse + contrapartida) já foi contratado, em % com
            // uma casa decimal. Pode passar de 100 quando os contratos excedem o valor.
            'percentual_comprometido' => $valorTotal > 0 ? round($contratado / $valorTotal * 100, 1) : 0.0,
            'saldo_disponivel' => $valorTotal - $contratado,
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
