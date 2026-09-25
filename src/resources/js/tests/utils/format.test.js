import { describe, expect, it } from 'vitest';
import {
    corDoPrazo,
    faixaDoPrazo,
    formatarData,
    formatarDataHora,
    formatarMoeda,
    formatarPercentual,
    formatarTamanho,
    situacaoPrazo,
    textoDoPrazo,
} from '../../utils/format';

// O Intl usa espaço não separável entre "R$" e o número.
const semNbsp = (texto) => texto.replace(/ /g, ' ');

describe('formatarMoeda', () => {
    it('formata em reais no padrão brasileiro', () => {
        expect(semNbsp(formatarMoeda(1234.5))).toBe('R$ 1.234,50');
    });

    it('trata vazio como zero', () => {
        expect(semNbsp(formatarMoeda(null))).toBe('R$ 0,00');
        expect(semNbsp(formatarMoeda(undefined))).toBe('R$ 0,00');
    });

    it('mostra saldo negativo', () => {
        expect(semNbsp(formatarMoeda(-250))).toContain('-');
    });
});

describe('formatarData', () => {
    it('converte YYYY-MM-DD sem deslocar o dia por fuso', () => {
        expect(formatarData('2026-12-31')).toBe('31/12/2026');
        expect(formatarData('2026-01-01')).toBe('01/01/2026');
    });

    it('aceita data com hora (só o dia conta)', () => {
        expect(formatarData('2026-03-05T23:59:00.000000Z')).toBe('05/03/2026');
    });

    it('devolve traço quando não há data', () => {
        expect(formatarData(null)).toBe('—');
        expect(formatarData('')).toBe('—');
    });
});

describe('formatarDataHora', () => {
    it('devolve traço sem valor', () => {
        expect(formatarDataHora(null)).toBe('—');
    });

    it('formata um instante em pt-BR', () => {
        expect(formatarDataHora('2026-09-25T14:30:00')).toMatch(/25\/09\/2026/);
    });
});

describe('situacaoPrazo (régua 90/60/30/15)', () => {
    it('não mostra nada sem prazo', () => {
        expect(situacaoPrazo(null)).toBeNull();
        expect(situacaoPrazo(undefined)).toBeNull();
    });

    it('vencido: vermelho com os dias de atraso', () => {
        expect(situacaoPrazo(-3)).toEqual({ texto: 'Vencido há 3 d', classes: 'bg-red-100 text-red-800' });
    });

    it.each([
        [0, 'Vence hoje', 'red'],
        [15, '15 d para vencer', 'red'],
        [16, '16 d para vencer', 'orange'],
        [30, '30 d para vencer', 'orange'],
        [31, '31 d para vencer', 'amber'],
        [90, '90 d para vencer', 'amber'],
        [91, '91 d para vencer', 'slate'],
    ])('%i dias -> "%s" (%s)', (dias, texto, cor) => {
        const situacao = situacaoPrazo(dias);

        expect(situacao.texto).toBe(texto);
        expect(situacao.classes).toContain(`bg-${cor}-100`);
    });
});

describe('faixaDoPrazo', () => {
    it('convênio finalizado ou sem prazo fica neutro', () => {
        expect(faixaDoPrazo(5, 'finalizado')).toBe('border-l-slate-300');
        expect(faixaDoPrazo(null, 'em_execucao')).toBe('border-l-slate-300');
    });

    it.each([
        [-10, 'border-l-red-500'],
        [15, 'border-l-red-500'],
        [16, 'border-l-orange-500'],
        [30, 'border-l-orange-500'],
        [31, 'border-l-amber-400'],
        [90, 'border-l-amber-400'],
        [91, 'border-l-emerald-500'],
    ])('%i dias -> %s', (dias, classe) => {
        expect(faixaDoPrazo(dias, 'em_execucao')).toBe(classe);
    });
});

describe('textoDoPrazo e corDoPrazo (sino de alertas)', () => {
    it.each([
        [-1, 'venceu há 1 dia'],
        [-3, 'venceu há 3 dias'],
        [0, 'vence hoje'],
        [1, 'vence amanhã'],
        [15, 'vence em 15 dias'],
    ])('%i -> "%s"', (dias, texto) => {
        expect(textoDoPrazo(dias)).toBe(texto);
    });

    it('a cor segue a mesma régua', () => {
        expect(corDoPrazo(-2)).toBe('bg-red-500');
        expect(corDoPrazo(15)).toBe('bg-red-500');
        expect(corDoPrazo(30)).toBe('bg-orange-500');
        expect(corDoPrazo(60)).toBe('bg-amber-400');
    });
});

describe('formatarTamanho', () => {
    it('escolhe a unidade certa', () => {
        expect(formatarTamanho(512)).toBe('512 B');
        expect(formatarTamanho(2048)).toBe('2 KB');
        expect(formatarTamanho(1.5 * 1024 * 1024)).toBe('1,5 MB');
    });
});

describe('formatarPercentual', () => {
    it('usa vírgula e no máximo uma casa', () => {
        expect(formatarPercentual(50)).toBe('50%');
        expect(formatarPercentual(33.333)).toBe('33,3%');
        expect(formatarPercentual(0)).toBe('0%');
        expect(formatarPercentual(null)).toBe('0%');
    });
});
