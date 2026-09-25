<?php

namespace App\Support;

/**
 * Gera a senha temporária de uma conta nova ou redefinida: aleatória (nada que dê para adivinhar
 * a partir do nome ou do e-mail da pessoa), fácil de ditar e de digitar, e mostrada uma única vez
 * a quem criou a conta. A pessoa é obrigada a trocá-la no primeiro acesso.
 */
class SenhaTemporaria
{
    // Sem os caracteres que se confundem (0/O, 1/l/I).
    private const MAIUSCULAS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const MINUSCULAS = 'abcdefghijkmnpqrstuvwxyz';

    private const NUMEROS = '23456789';

    /** Formato XXXX-XXXX-XXXX: 12 caracteres em três blocos. Cumpre a regra de senha do sistema (10+, letras e números). */
    public static function gerar(): string
    {
        $todos = self::MAIUSCULAS.self::MINUSCULAS.self::NUMEROS;

        do {
            $caracteres = '';

            for ($i = 0; $i < 12; $i++) {
                $caracteres .= $todos[random_int(0, strlen($todos) - 1)];
            }
            // Garante pelo menos uma maiúscula, uma minúscula e um número.
        } while (
            ! preg_match('/[A-Z]/', $caracteres)
            || ! preg_match('/[a-z]/', $caracteres)
            || ! preg_match('/[2-9]/', $caracteres)
        );

        return implode('-', str_split($caracteres, 4));
    }
}
