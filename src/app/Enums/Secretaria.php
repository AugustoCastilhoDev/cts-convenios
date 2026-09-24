<?php

namespace App\Enums;

/**
 * Secretaria municipal responsável pelo convênio. A lista cobre as pastas mais
 * comuns; para incluir outra, acrescente um case aqui, o rótulo abaixo e a cor
 * em resources/js/utils/secretaria.js (o banco guarda o valor como texto, então
 * não exige migration).
 */
enum Secretaria: string
{
    case Saude = 'saude';
    case Educacao = 'educacao';
    case Obras = 'obras';
    case Administracao = 'administracao';
    case AssistenciaSocial = 'assistencia_social';
    case Outra = 'outra';

    public function label(): string
    {
        return match ($this) {
            self::Saude => 'Saúde',
            self::Educacao => 'Educação',
            self::Obras => 'Obras',
            self::Administracao => 'Administração',
            self::AssistenciaSocial => 'Assistência Social',
            self::Outra => 'Outra',
        };
    }
}
