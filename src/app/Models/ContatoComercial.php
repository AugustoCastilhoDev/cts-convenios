<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Pedido de contato vindo da landing page. `aceite_em` e `ip` são definidos
 * pelo ContatoComercialService, nunca pelo payload.
 */
#[Table(name: 'contatos_comerciais')]
#[Fillable(['nome', 'cargo', 'municipio', 'email', 'telefone', 'mensagem'])]
class ContatoComercial extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'aceite_em' => 'datetime',
        ];
    }
}
