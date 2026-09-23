<?php

namespace App\Http\Resources;

use App\Models\AlertaPrazo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Um item do sino. `dias` e `lida` vêm calculados pelo NotificacaoAlertaService.
 *
 * @mixin AlertaPrazo
 */
class NotificacaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'convenio_id' => $this->convenio_id,
            'numero_convenio' => $this->convenio->numero_convenio,
            'tipo_prazo' => $this->tipo_prazo->value,
            'tipo_prazo_label' => $this->tipo_prazo->label(),
            'data_prazo' => $this->data_prazo->toDateString(),
            'dias' => $this->dias,
            'lida' => (bool) $this->lida,
            'created_at' => $this->created_at,
        ];
    }
}
