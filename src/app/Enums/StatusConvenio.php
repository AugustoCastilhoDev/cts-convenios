<?php

namespace App\Enums;

enum StatusConvenio: string
{
    case Proposta = 'proposta';
    case EmAnalise = 'em_analise';
    case Aprovado = 'aprovado';
    case EmExecucao = 'em_execucao';
    case PrestacaoContas = 'prestacao_contas';
    case Finalizado = 'finalizado';

    public function label(): string
    {
        return match ($this) {
            self::Proposta => 'Proposta',
            self::EmAnalise => 'Em Análise',
            self::Aprovado => 'Aprovado',
            self::EmExecucao => 'Em Execução',
            self::PrestacaoContas => 'Prestação de Contas',
            self::Finalizado => 'Finalizado',
        };
    }

    /**
     * Cor de referência para o card no Kanban do front-end (Vue.js).
     */
    public function corKanban(): string
    {
        return match ($this) {
            self::Proposta => 'slate',
            self::EmAnalise => 'amber',
            self::Aprovado => 'blue',
            self::EmExecucao => 'indigo',
            self::PrestacaoContas => 'purple',
            self::Finalizado => 'green',
        };
    }
}
