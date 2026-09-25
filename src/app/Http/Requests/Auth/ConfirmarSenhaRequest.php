<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\Attributes\StopOnFirstFailure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ações sensíveis (ativar o 2FA, gerar novos códigos, redefinir o 2FA de alguém) pedem a senha de quem
 * está logado de novo: um token roubado, sozinho, não basta.
 */
#[StopOnFirstFailure]
class ConfirmarSenhaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password'],
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
