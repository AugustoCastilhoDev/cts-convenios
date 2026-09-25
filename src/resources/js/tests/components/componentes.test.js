import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Campo from '../../components/Campo.vue';
import CobrancaSimuladaModal from '../../components/CobrancaSimuladaModal.vue';
import ModalBase from '../../components/ModalBase.vue';
import Paginacao from '../../components/Paginacao.vue';
import SeloAdimplencia from '../../components/SeloAdimplencia.vue';

describe('SeloAdimplencia', () => {
    const regular = { situacao: 'regular', prazos_vencidos: 0, convenios_afetados: 0, maior_atraso_dias: 0 };
    const risco = { situacao: 'risco', prazos_vencidos: 2, convenios_afetados: 1, maior_atraso_dias: 12 };

    it('município regular: verde e sem detalhes de atraso', () => {
        const selo = mount(SeloAdimplencia, { props: { regularidade: regular } });

        expect(selo.text()).toContain('Município Regular');
        expect(selo.find('[role="status"]').classes()).toContain('bg-green-50');
        expect(selo.text()).not.toContain('prazos vencidos');
    });

    it('em risco: vermelho, com a contagem e o maior atraso', () => {
        const selo = mount(SeloAdimplencia, { props: { regularidade: risco } });

        expect(selo.text()).toContain('Atenção: Risco de Inadimplência (CADIN)');
        expect(selo.text()).toContain('2 prazos vencidos');
        expect(selo.text()).toContain('maior atraso: 12 dias');
        expect(selo.find('[role="status"]').classes()).toContain('bg-red-50');
        expect(selo.find('a').attributes('href')).toBe('#prazos-criticos');
    });

    it('usa o singular para um prazo e um dia', () => {
        const selo = mount(SeloAdimplencia, { props: { regularidade: { ...risco, prazos_vencidos: 1, maior_atraso_dias: 1 } } });

        expect(selo.text()).toContain('1 prazo vencido');
        expect(selo.text()).toContain('maior atraso: 1 dia');
    });

    it('sempre lembra que não substitui a consulta oficial ao CADIN/CAUC', () => {
        const selo = mount(SeloAdimplencia, { props: { regularidade: regular } });

        expect(selo.text()).toContain('Não substitui a consulta oficial ao CADIN/CAUC');
    });
});

describe('Paginacao', () => {
    it('não aparece quando há uma página só', () => {
        expect(mount(Paginacao, { props: { meta: { current_page: 1, last_page: 1, total: 5 } } }).html()).toBe('<!--v-if-->');
        expect(mount(Paginacao, { props: { meta: null } }).html()).toBe('<!--v-if-->');
    });

    it('mostra a posição e emite a página pedida', async () => {
        const paginacao = mount(Paginacao, { props: { meta: { current_page: 2, last_page: 3, total: 55 } } });

        expect(paginacao.text()).toContain('Página 2 de 3');
        expect(paginacao.text()).toContain('55 registros');

        const [anterior, proxima] = paginacao.findAll('button');
        await anterior.trigger('click');
        await proxima.trigger('click');

        expect(paginacao.emitted('pagina')).toEqual([[1], [3]]);
    });

    it('desabilita "Anterior" na primeira e "Próxima" na última', () => {
        const primeira = mount(Paginacao, { props: { meta: { current_page: 1, last_page: 2, total: 30 } } });
        const ultima = mount(Paginacao, { props: { meta: { current_page: 2, last_page: 2, total: 30 } } });

        expect(primeira.findAll('button')[0].attributes('disabled')).toBeDefined();
        expect(primeira.findAll('button')[1].attributes('disabled')).toBeUndefined();
        expect(ultima.findAll('button')[1].attributes('disabled')).toBeDefined();
    });
});

describe('ModalBase', () => {
    it('é um diálogo acessível com título e conteúdo', () => {
        const modal = mount(ModalBase, { props: { titulo: 'Novo convênio' }, slots: { default: '<p>conteúdo</p>' } });

        expect(modal.find('[role="dialog"]').attributes('aria-modal')).toBe('true');
        expect(modal.find('[role="dialog"]').attributes('aria-label')).toBe('Novo convênio');
        expect(modal.text()).toContain('conteúdo');
    });

    it('fecha com Esc e ao clicar fora, mas não ao clicar dentro', async () => {
        const modal = mount(ModalBase, { props: { titulo: 'X' }, attachTo: document.body });

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await modal.find('[role="dialog"]').trigger('mousedown');
        expect(modal.emitted('fechar')).toHaveLength(1);

        await modal.trigger('mousedown');
        expect(modal.emitted('fechar')).toHaveLength(2);

        modal.unmount();
    });

    it('para de ouvir o teclado depois de desmontado', () => {
        const modal = mount(ModalBase, { props: { titulo: 'X' } });
        modal.unmount();

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        expect(modal.emitted('fechar')).toBeUndefined();
    });
});

describe('Campo', () => {
    it('liga o rótulo ao controle e mostra a ajuda', () => {
        const campo = mount(Campo, { props: { rotulo: 'E-mail', para: 'email', ajuda: 'Use o e-mail institucional' }, slots: { default: '<input id="email">' } });

        expect(campo.find('label').attributes('for')).toBe('email');
        expect(campo.text()).toContain('Use o e-mail institucional');
    });

    it('o erro de validação substitui a ajuda', () => {
        const campo = mount(Campo, { props: { rotulo: 'E-mail', para: 'email', ajuda: 'Dica', erro: 'E-mail inválido' } });

        expect(campo.text()).toContain('E-mail inválido');
        expect(campo.text()).not.toContain('Dica');
    });
});

describe('CobrancaSimuladaModal', () => {
    const prazo = { convenio_id: 'x', numero_convenio: '123/2026', tipo_prazo_label: 'Prestação de contas', data_prazo: '2026-09-01', dias: -5 };

    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('mostra a prévia da mensagem e deixa claro que nada é enviado', () => {
        const modal = mount(CobrancaSimuladaModal, { props: { prazo } });

        expect(modal.text()).toContain('nenhuma notificação é enviada');
        expect(modal.text()).toContain('123/2026');
        expect(modal.text()).toContain('venceu há 5 dias');
        expect(modal.text()).toContain('01/09/2026');
    });

    it('simular passa por "Simulando…" e termina confirmando que nada foi enviado', async () => {
        const modal = mount(CobrancaSimuladaModal, { props: { prazo } });
        const botao = modal.findAll('button').find((b) => b.text().includes('Simular disparo'));

        await botao.trigger('click');
        expect(modal.text()).toContain('Simulando…');
        expect(modal.find('button[disabled]').exists()).toBe(true);

        await vi.advanceTimersByTimeAsync(800);

        expect(modal.text()).toContain('Simulação concluída');
        expect(modal.text()).toContain('Nenhuma notificação foi enviada');
    });

    it('cancelar emite fechar', async () => {
        const modal = mount(CobrancaSimuladaModal, { props: { prazo } });

        await modal.findAll('button').find((b) => b.text() === 'Cancelar').trigger('click');

        expect(modal.emitted('fechar')).toHaveLength(1);
    });
});
