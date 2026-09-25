import { describe, expect, it } from 'vitest';
import { eventos, rotuloDoCampo, tiposRegistro, tituloDoEvento, tituloDoTipo, valorLegivel } from '../../utils/auditoria';
import { SEM_SECRETARIA, infoSecretaria, secretarias } from '../../utils/secretaria';
import { statusContrato, statusConvenio } from '../../utils/status';

describe('status dos convênios', () => {
    it('segue a ordem do fluxo, da proposta à finalização', () => {
        expect(statusConvenio.map((s) => s.status)).toEqual([
            'proposta', 'em_analise', 'aprovado', 'em_execucao', 'prestacao_contas', 'finalizado',
        ]);
    });

    it('cada etapa tem título e cores completas (o Tailwind só enxerga strings inteiras)', () => {
        statusConvenio.forEach((s) => {
            expect(s.titulo).toBeTruthy();
            expect(s.barra).toMatch(/^bg-/);
            expect(s.topo).toMatch(/^border-t-/);
        });
    });

    it('lista as situações de execução de contrato', () => {
        expect(statusContrato.map((s) => s.valor)).toEqual(['nao_iniciado', 'em_andamento', 'paralisado', 'concluido']);
    });
});

describe('secretarias', () => {
    it('espelha o enum do servidor', () => {
        expect(secretarias.map((s) => s.valor)).toEqual(['saude', 'educacao', 'obras', 'administracao', 'assistencia_social', 'outra']);
    });

    it('infoSecretaria devolve o badge da secretaria', () => {
        expect(infoSecretaria('saude').titulo).toBe('Saúde');
    });

    it('convênio sem classificação (ou valor desconhecido) recebe o cinza neutro', () => {
        expect(infoSecretaria(null).titulo).toBe('Sem secretaria');
        expect(infoSecretaria('inexistente').titulo).toBe('Sem secretaria');
    });

    it('o filtro de não classificados usa o mesmo valor que o servidor entende', () => {
        expect(SEM_SECRETARIA).toBe('sem_secretaria');
    });
});

describe('auditoria', () => {
    it('traduz campos conhecidos e mantém os desconhecidos', () => {
        expect(rotuloDoCampo('valor_repasse')).toBe('Repasse');
        expect(rotuloDoCampo('campo_novo')).toBe('campo_novo');
    });

    it('mostra valores de forma legível', () => {
        expect(valorLegivel(null)).toBe('—');
        expect(valorLegivel('')).toBe('—');
        expect(valorLegivel(true)).toBe('sim');
        expect(valorLegivel(false)).toBe('não');
        expect(valorLegivel(1500)).toBe('1500');
        expect(valorLegivel({ a: 1 })).toBe('{"a":1}');
    });

    it('traduz eventos e tipos de registro', () => {
        expect(tituloDoEvento('updated')).toBe('Alterado');
        expect(tituloDoEvento('outro')).toBe('outro');
        expect(tituloDoTipo('contrato')).toBe('Contrato');
        expect(eventos).toHaveLength(4);
        expect(tiposRegistro.map((t) => t.valor)).toContain('prefeitura');
    });
});
