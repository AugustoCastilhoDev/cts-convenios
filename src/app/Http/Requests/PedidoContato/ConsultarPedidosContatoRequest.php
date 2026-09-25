<?php

namespace App\Http\Requests\PedidoContato;

use Illuminate\Foundation\Http\Attributes\StopOnFirstFailure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Quem pode ver os pedidos" é a ContatoComercialPolicy; aqui só os filtros.
 * Serve à listagem e à exportação (mesmos filtros).
 */
#[StopOnFirstFailure]
class ConsultarPedidosContatoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'busca' => ['nullable', 'string', 'max:120'],
            'situacao' => ['nullable', Rule::in(['pendente', 'respondido'])],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
        ];
    }
}
