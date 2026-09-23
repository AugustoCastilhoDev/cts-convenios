<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\Attributes\StopOnFirstFailure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Atualização parcial: só valida o que vier no payload. tenant_id não é
 * editável — mover usuário entre prefeituras é criar outra conta.
 */
#[StopOnFirstFailure]
class UpdateUserRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes', 'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'password' => ['sometimes', 'required', 'string', Password::min(8)->letters()->numbers()],
            'role' => ['sometimes', 'required', Rule::in(StoreUserRequest::papeisPermitidos())],
            'active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
