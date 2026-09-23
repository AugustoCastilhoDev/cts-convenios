<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida o CNPJ pelos dois dígitos verificadores (aceita com ou sem máscara).
 */
class Cnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::valido((string) $value)) {
            $fail('O :attribute informado não é um CNPJ válido.');
        }
    }

    public static function valido(string $valor): bool
    {
        $cnpj = preg_replace('/\D/', '', $valor);

        // 14 dígitos e não pode ser uma sequência repetida (00000000000000, 11111111111111...).
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        foreach ([12, 13] as $posicao) {
            $pesos = $posicao === 12
                ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
                : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

            $soma = 0;
            foreach ($pesos as $indice => $peso) {
                $soma += (int) $cnpj[$indice] * $peso;
            }

            $resto = $soma % 11;
            $digito = $resto < 2 ? 0 : 11 - $resto;

            if ((int) $cnpj[$posicao] !== $digito) {
                return false;
            }
        }

        return true;
    }

    /**
     * 12345678000195 -> 12.345.678/0001-95 (formato guardado no banco, para a
     * unicidade não depender de como o CNPJ foi digitado).
     */
    public static function formatar(string $valor): string
    {
        $cnpj = preg_replace('/\D/', '', $valor);

        return strlen($cnpj) === 14
            ? sprintf('%s.%s.%s/%s-%s', substr($cnpj, 0, 2), substr($cnpj, 2, 3), substr($cnpj, 5, 3), substr($cnpj, 8, 4), substr($cnpj, 12, 2))
            : $valor;
    }
}
