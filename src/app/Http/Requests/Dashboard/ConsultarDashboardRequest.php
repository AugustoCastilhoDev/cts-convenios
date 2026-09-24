<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\Secretaria;
use App\Models\Convenio;
use Illuminate\Foundation\Http\Attributes\StopOnFirstFailure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Quem pode ver o painel" é a ConvenioPolicy (#[Authorize] no controller);
 * aqui só validamos o filtro de secretaria.
 */
#[StopOnFirstFailure]
class ConsultarDashboardRequest extends FormRequest
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
            'secretaria' => ['nullable', Rule::in([...array_column(Secretaria::cases(), 'value'), Convenio::SEM_SECRETARIA])],
        ];
    }
}
