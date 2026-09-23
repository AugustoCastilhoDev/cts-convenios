<?php

namespace App\Enums;

enum TipoPrazo: string
{
    case VigenciaFim = 'vigencia_fim';
    case PrestacaoContas = 'prestacao_contas';

    public function label(): string
    {
        return match ($this) {
            self::VigenciaFim => 'Fim da vigência',
            self::PrestacaoContas => 'Prazo de prestação de contas',
        };
    }

    /**
     * Coluna de convenios que guarda a data deste prazo.
     */
    public function campo(): string
    {
        return match ($this) {
            self::VigenciaFim => 'data_vigencia_fim',
            self::PrestacaoContas => 'prazo_prestacao_contas',
        };
    }

    /**
     * Status em que o prazo ainda merece alerta. A vigência deixa de ser
     * relevante quando o convênio entra em prestação de contas (ela já
     * terminou de propósito); a prestação de contas vale até finalizar.
     *
     * @return array<int, StatusConvenio>
     */
    public function statusMonitorados(): array
    {
        return match ($this) {
            self::VigenciaFim => [
                StatusConvenio::Proposta,
                StatusConvenio::EmAnalise,
                StatusConvenio::Aprovado,
                StatusConvenio::EmExecucao,
            ],
            self::PrestacaoContas => [
                StatusConvenio::Proposta,
                StatusConvenio::EmAnalise,
                StatusConvenio::Aprovado,
                StatusConvenio::EmExecucao,
                StatusConvenio::PrestacaoContas,
            ],
        };
    }
}
