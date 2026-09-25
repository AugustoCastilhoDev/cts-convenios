<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Códigos de recuperação em texto: mostrados uma única vez (o servidor guarda só o hash deles).
 *
 * @property array<int, string> $resource
 */
class CodigosDeRecuperacaoResource extends JsonResource
{
    /** @return array{codigos: array<int, string>} */
    public function toArray(Request $request): array
    {
        return ['codigos' => array_values($this->resource)];
    }
}
