<?php

namespace App\Http\Requests\Audit;

use App\Http\Controllers\Api\AuditController;
use Illuminate\Foundation\Http\Attributes\StopOnFirstFailure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Quem pode consultar a auditoria" é a AuditPolicy (só Administrador
 * Interno); aqui só validamos os filtros.
 */
#[StopOnFirstFailure]
class ConsultarAuditoriaRequest extends FormRequest
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
            'tipo' => ['nullable', Rule::in(array_keys(AuditController::TIPOS))],
            'registro_id' => ['nullable', 'string', 'max:64'],
            'user_id' => ['nullable', 'integer'],
            'evento' => ['nullable', Rule::in(['created', 'updated', 'deleted', 'restored'])],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
        ];
    }
}
