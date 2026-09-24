<?php

namespace App\Http\Requests\Convenio;

use App\Enums\Secretaria;
use App\Enums\StatusConvenio;
use Illuminate\Foundation\Http\Attributes\StopOnFirstFailure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[StopOnFirstFailure]
class UpdateConvenioRequest extends FormRequest
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
        // tenant_id é imutável após a criação — não faz parte das regras,
        // logo um valor enviado no payload é simplesmente ignorado.
        return [
            'numero_convenio' => [
                'required', 'string', 'max:255',
                Rule::unique('convenios', 'numero_convenio')
                    ->where('tenant_id', $this->route('convenio')->tenant_id)
                    ->ignore($this->route('convenio')),
            ],
            'orgao_concedente' => ['required', 'string', 'max:255'],
            'objeto' => ['required', 'string'],
            'secretaria' => ['nullable', Rule::enum(Secretaria::class)],
            'valor_repasse' => ['required', 'numeric', 'min:0'],
            'valor_contrapartida' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::enum(StatusConvenio::class)],
            'data_assinatura' => ['nullable', 'date'],
            'data_vigencia_fim' => ['nullable', 'date', 'after_or_equal:data_assinatura'],
            'prazo_prestacao_contas' => ['nullable', 'date'],
        ];
    }
}
