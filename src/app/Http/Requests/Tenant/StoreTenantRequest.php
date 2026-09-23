<?php

namespace App\Http\Requests\Tenant;

use App\Rules\Cnpj;
use Illuminate\Foundation\Http\Attributes\StopOnFirstFailure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Quem pode cadastrar prefeituras" é a TenantPolicy (só o Administrador
 * Interno), aplicada pelo #[Authorize] do controller.
 */
#[StopOnFirstFailure]
class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Guarda sempre com máscara, para a unicidade não depender da digitação. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('cnpj'))) {
            $this->merge(['cnpj' => Cnpj::formatar(trim($this->input('cnpj')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'razao_social' => ['required', 'string', 'max:255'],
            'cnpj' => ['required', 'string', new Cnpj, Rule::unique('tenants', 'cnpj')],
        ];
    }
}
