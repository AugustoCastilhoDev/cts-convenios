<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\Attributes\StopOnFirstFailure;
use Illuminate\Foundation\Http\FormRequest;

#[StopOnFirstFailure]
class DesativarDoisFatoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Senha e um código atual (do app ou de recuperação): quem só tem o token ou só a senha não desliga o 2FA.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password'],
            'codigo' => ['required', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.current_password' => 'A senha não confere.',
        ];
    }
}
