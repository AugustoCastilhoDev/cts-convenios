<?php

namespace App\Http\Resources;

use App\Models\AlertaPrazo;
use App\Services\AlertaPrazoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AlertaPrazo */
class AlertaPrazoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'convenio_id' => $this->convenio_id,
            'tipo_prazo' => $this->tipo_prazo->value,
            'tipo_prazo_label' => $this->tipo_prazo->label(),
            'marco' => $this->marco_dias === AlertaPrazoService::MARCO_VENCIDO ? 'vencido' : $this->marco_dias,
            'data_prazo' => $this->data_prazo->toDateString(),
            'situacao' => match (true) {
                $this->enviado_em !== null => 'enviado',
                $this->cancelado_em !== null => 'cancelado',
                default => 'pendente',
            },
            'enviado_em' => $this->enviado_em,
            'destinatarios' => $this->destinatarios,
            'created_at' => $this->created_at,
        ];
    }
}
