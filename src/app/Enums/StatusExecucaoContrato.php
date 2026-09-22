<?php

namespace App\Enums;

enum StatusExecucaoContrato: string
{
    case NaoIniciado = 'nao_iniciado';
    case EmAndamento = 'em_andamento';
    case Paralisado = 'paralisado';
    case Concluido = 'concluido';

    public function label(): string
    {
        return match ($this) {
            self::NaoIniciado => 'Não Iniciado',
            self::EmAndamento => 'Em Andamento',
            self::Paralisado => 'Paralisado',
            self::Concluido => 'Concluído',
        };
    }
}
