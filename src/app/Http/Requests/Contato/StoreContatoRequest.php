<?php

namespace App\Http\Requests\Contato;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sem StopOnFirstFailure de propósito: num formulário público a pessoa precisa ver
 * todos os campos com problema de uma vez, não um por tentativa.
 *
 * Formulário público da landing page: sem autenticação, então a defesa é o
 * limite de envios por IP (throttle:contato), o campo-armadilha `website` e a
 * validação estrita abaixo.
 */
class StoreContatoRequest extends FormRequest
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
            'nome' => ['required', 'string', 'max:120'],
            'cargo' => ['nullable', 'string', 'max:120'],
            'municipio' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'telefone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9()+\-.\s]{8,}$/'],
            'mensagem' => ['nullable', 'string', 'max:2000'],
            'aceite' => ['accepted'],
            // Campo escondido na página: pessoas nunca o preenchem, robôs costumam preencher.
            'website' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'aceite.accepted' => 'Para enviar, confirme que podemos usar seus dados para retornar o contato.',
            'telefone.regex' => 'Informe um telefone válido, com DDD.',
        ];
    }
}
