<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\Attributes\StopOnFirstFailure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Só Gestor de Convênios e Fiscal de Controle Interno são criados por aqui.
 * Administrador Interno (cross-tenant) nunca nasce de payload de API: só
 * pelo comando `php artisan admin:criar`.
 */
#[StopOnFirstFailure]
class StoreUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'role' => ['required', Rule::in(self::papeisPermitidos())],
            'tenant_id' => ['required', 'uuid', Rule::exists('tenants', 'id')],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function papeisPermitidos(): array
    {
        return [UserRole::GestorConvenios->value, UserRole::FiscalControleInterno->value];
    }
}
