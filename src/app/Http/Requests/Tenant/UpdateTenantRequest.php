<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant;
use App\Rules\Cnpj;
use Illuminate\Foundation\Http\Attributes\StopOnFirstFailure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[StopOnFirstFailure]
class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        // O dígito verificador só é exigido quando o CNPJ muda: cadastros antigos
        // (ex.: dados fictícios de demonstração) continuam editáveis.
        $cnpj = ['sometimes', 'required', 'string', Rule::unique('tenants', 'cnpj')->ignore($tenant)];
        if ($this->input('cnpj') !== $tenant->cnpj) {
            $cnpj[] = new Cnpj;
        }

        return [
            'razao_social' => ['sometimes', 'required', 'string', 'max:255'],
            'cnpj' => $cnpj,
            'active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
