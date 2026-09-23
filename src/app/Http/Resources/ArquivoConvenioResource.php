<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * file_path (caminho interno em disco) nunca é exposto: o cliente baixa
 * via download_url, que passa pela Policy.
 *
 * @mixin \App\Models\ArquivoConvenio
 */
class ArquivoConvenioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'convenio_id' => $this->convenio_id,
            'tipo_documento' => $this->tipo_documento->value,
            'tipo_documento_label' => $this->tipo_documento->label(),
            'nome_original' => $this->nome_original,
            'mime_type' => $this->mime_type,
            'tamanho_bytes' => $this->tamanho_bytes,
            'enviado_por' => $this->enviado_por,
            'download_url' => url("/api/convenios/{$this->convenio_id}/arquivos/{$this->id}/download"),
            'created_at' => $this->created_at,
        ];
    }
}
