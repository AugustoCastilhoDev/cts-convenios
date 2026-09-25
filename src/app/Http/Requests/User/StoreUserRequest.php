<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\Attributes\StopOnFirstFailure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cria Gestor, Fiscal ou Administrador da Prefeitura. Super administrador (cross-tenant) nunca nasce
 * de payload de API: só pelo comando `php artisan admin:criar`. Não há campo de senha: o sistema gera
 * uma temporária. Quem é administrador da prefeitura só cria na própria prefeitura, mesmo que envie
 * outro tenant_id.
 */
#[StopOnFirstFailure]
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $autor = $this->user();

        if ($autor && ! $autor->isAdministradorInterno()) {
            $this->merge(['tenant_id' => $autor->tenant_id]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(self::papeisPermitidos())],
            'tenant_id' => ['required', 'uuid', Rule::exists('tenants', 'id')],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function papeisPermitidos(): array
    {
        return [
            UserRole::GestorConvenios->value,
            UserRole::FiscalControleInterno->value,
            UserRole::AdministradorPrefeitura->value,
        ];
    }
}
