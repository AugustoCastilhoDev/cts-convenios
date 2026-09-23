<?php

namespace App\Http\Requests\Convenio;

use App\Enums\StatusConvenio;
use Illuminate\Foundation\Http\Attributes\StopOnFirstFailure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Quem pode exportar" é decidido pela ConvenioPolicy (#[Authorize] no
 * controller); aqui só validamos o formato e os filtros — os mesmos da
 * listagem, para o relatório espelhar o que a tela mostra.
 */
#[StopOnFirstFailure]
class ExportarConveniosRequest extends FormRequest
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
            'formato' => ['required', Rule::in(['csv', 'pdf'])],
            'status' => ['nullable', Rule::enum(StatusConvenio::class)],
            'busca' => ['nullable', 'string', 'max:255'],
        ];
    }
}
