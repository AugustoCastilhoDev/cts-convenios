<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * O que a tela precisa para configurar o app autenticador: o segredo para digitar e o endereço otpauth://
 * que vira QR Code. Só existe na resposta da ativação (a API nunca o devolve de novo).
 *
 * @property array{segredo: string, url: string} $resource
 */
class AtivacaoDoisFatoresResource extends JsonResource
{
    /** @return array{segredo: string, url: string} */
    public function toArray(Request $request): array
    {
        return ['segredo' => $this->resource['segredo'], 'url' => $this->resource['url']];
    }
}
