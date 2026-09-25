<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\Attributes\StopOnFirstFailure;
use Illuminate\Foundation\Http\FormRequest;

#[StopOnFirstFailure]
class DesafioDoisFatoresRequest extends FormRequest
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
            'desafio' => ['required', 'string', 'size:48'],
            'codigo' => ['required', 'string', 'max:32'],
        ];
    }
}
