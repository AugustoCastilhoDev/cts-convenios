<?php

namespace App\Http\Requests\ContratoVinculado;

use App\Enums\StatusExecucaoContrato;
use Illuminate\Foundation\Http\Attributes\StopOnFirstFailure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * convenio_id e tenant_id não entram nas regras de propósito: vêm da rota
 * aninhada (/convenios/{convenio}/contratos) e são atribuídos pelo
 * ContratoVinculadoService, nunca de payload do cliente.
 */
#[StopOnFirstFailure]
class StoreContratoVinculadoRequest extends FormRequest
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
            'numero_contrato' => ['required', 'string', 'max:255'],
            'empresa_contratada' => ['required', 'string', 'max:255'],
            'valor_contratado' => ['required', 'numeric', 'min:0'],
            'status_execucao' => ['required', Rule::enum(StatusExecucaoContrato::class)],
        ];
    }
}
