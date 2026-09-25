<?php

namespace App\Http\Resources;

use App\Models\ContatoComercial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContatoComercial */
class PedidoContatoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'cargo' => $this->cargo,
            'municipio' => $this->municipio,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'mensagem' => $this->mensagem,
            'aceite_em' => $this->aceite_em,
            'ip' => $this->ip,
            'respondido_em' => $this->respondido_em,
            'created_at' => $this->created_at,
        ];
    }
}
