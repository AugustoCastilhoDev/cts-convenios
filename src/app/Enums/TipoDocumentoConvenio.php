<?php

namespace App\Enums;

enum TipoDocumentoConvenio: string
{
    case TermoAssinatura = 'termo_assinatura';
    case Extrato = 'extrato';
    case NotaFiscal = 'nota_fiscal';
    case Outro = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::TermoAssinatura => 'Termo de Assinatura',
            self::Extrato => 'Extrato',
            self::NotaFiscal => 'Nota Fiscal',
            self::Outro => 'Outro',
        };
    }
}
