import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import ConfirmarSenhaModal from '../../components/ConfirmarSenhaModal.vue';
import SenhaTemporariaModal from '../../components/SenhaTemporariaModal.vue';
import UsuarioFormModal from '../../components/UsuarioFormModal.vue';
import { api, ApiError } from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import UsuariosView from '../../views/admin/UsuariosView.vue';

vi.mock('../../services/api', async (importOriginal) => {
    const original = await importOriginal();

    return { ...original, api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() } };
});

const SENHA = 'K7mQ-x3Rp-9Tw2';

const usuarios = [
    { id: 1, name: 'Ana Admin', email: 'ana@prefeitura.gov.br', role: 'administrador_prefeitura', role_label: 'Administrador da Prefeitura', active: true, must_change_password: false, tenant: { razao_social: 'Prefeitura X' } },
    { id: 2, name: 'Bruno Gestor', email: 'bruno@prefeitura.gov.br', role: 'gestor_convenios', role_label: 'Gestor de Convênios', active: true, must_change_password: true, tenant: { razao_social: 'Prefeitura X' } },
];

function entrarComo(role, id = 1) {
    const auth = useAuthStore();
    auth.user = { id, name: 'Ana Admin', role };

    return auth;
}

async function abrirTela() {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/', component: { template: '<div />' } }] });
    router.push('/');
    await router.isReady();
    const tela = mount(UsuariosView, { global: { plugins: [router] } });
    await flushPromises();

    return tela;
}

beforeEach(() => {
    localStorage.clear();
    setActivePinia(createPinia());
    vi.clearAllMocks();
    api.get.mockImplementation(async (caminho) => (caminho === '/tenants'
        ? { data: [{ id: 'a', razao_social: 'Prefeitura X', active: true }] }
        : { data: usuarios, meta: { current_page: 1, last_page: 1, total: 2 } }));
});

afterEach(() => vi.restoreAllMocks());

describe('permissões por papel (administrador da prefeitura)', () => {
    it('administrador da prefeitura edita como gestor, gerencia usuários, mas não exclui', () => {
        const auth = entrarComo('administrador_prefeitura');

        expect(auth.isAdminPrefeitura).toBe(true);
        expect(auth.isAdmin).toBe(false);
        expect(auth.podeEditar).toBe(true);
        expect(auth.podeGerenciarUsuarios).toBe(true);
        expect(auth.temSino).toBe(true);
        expect(auth.podeExcluir).toBe(false);
    });

    it('super administrador gerencia usuários e exclui; gestor e fiscal não gerenciam', () => {
        expect(entrarComo('administrador_interno').podeGerenciarUsuarios).toBe(true);
        expect(useAuthStore().podeExcluir).toBe(true);
        expect(entrarComo('gestor_convenios').podeGerenciarUsuarios).toBe(false);
        expect(entrarComo('fiscal_controle_interno').podeGerenciarUsuarios).toBe(false);
    });
});

describe('SenhaTemporariaModal', () => {
    const props = { titulo: 'Usuário criado', nome: 'Bruno Gestor', email: 'bruno@prefeitura.gov.br', senha: SENHA };

    it('informa até quando a senha vale, quando o servidor diz', () => {
        const modal = mount(SenhaTemporariaModal, { props: { ...props, expiraEm: '2026-10-03T12:00:00Z' } });

        expect(modal.text()).toMatch(/A senha vale até \d{2}\/10\/2026/);
        expect(mount(SenhaTemporariaModal, { props }).text()).not.toContain('A senha vale até');
    });

    it('mostra a senha, para quem é, e o aviso de que só aparece uma vez', () => {
        const modal = mount(SenhaTemporariaModal, { props });

        expect(modal.find('output').text()).toBe(SENHA);
        expect(modal.text()).toContain('Bruno Gestor');
        expect(modal.text()).toContain('bruno@prefeitura.gov.br');
        expect(modal.text()).toContain('só agora');
        expect(modal.text()).toContain('primeiro acesso');
    });

    it('copia a senha para a área de transferência', async () => {
        const escrever = vi.fn().mockResolvedValue();
        Object.defineProperty(navigator, 'clipboard', { value: { writeText: escrever }, configurable: true });
        const modal = mount(SenhaTemporariaModal, { props });

        await modal.findAll('button').find((b) => b.text() === 'Copiar').trigger('click');
        await flushPromises();

        expect(escrever).toHaveBeenCalledWith(SENHA);
        expect(modal.text()).toContain('Copiada!');
    });

    it('sem permissão de copiar, avisa e deixa a senha visível', async () => {
        Object.defineProperty(navigator, 'clipboard', { value: { writeText: vi.fn().mockRejectedValue(new Error('negado')) }, configurable: true });
        const modal = mount(SenhaTemporariaModal, { props });

        await modal.findAll('button').find((b) => b.text() === 'Copiar').trigger('click');
        await flushPromises();

        expect(modal.find('[role="alert"]').text()).toContain('Não foi possível copiar');
        expect(modal.find('output').text()).toBe(SENHA);
    });

    it('só fecha pelo botão: Esc e clique fora não perdem a senha', async () => {
        const modal = mount(SenhaTemporariaModal, { props, attachTo: document.body });

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await modal.trigger('mousedown');
        expect(modal.emitted('fechar')).toBeUndefined();

        await modal.findAll('button').find((b) => b.text().includes('fechar')).trigger('click');
        expect(modal.emitted('fechar')).toHaveLength(1);

        modal.unmount();
    });
});

describe('UsuarioFormModal', () => {
    it('não tem campo de senha e avisa que uma temporária será gerada', () => {
        entrarComo('administrador_prefeitura');
        const modal = mount(UsuarioFormModal, { props: { usuario: null } });

        expect(modal.find('input[type="password"]').exists()).toBe(false);
        expect(modal.text()).toContain('senha temporária');
    });

    it('administrador da prefeitura não escolhe prefeitura e não envia tenant_id', async () => {
        entrarComo('administrador_prefeitura');
        api.post.mockResolvedValue({ data: usuarios[1], senha_temporaria: SENHA });
        const modal = mount(UsuarioFormModal, { props: { usuario: null } });

        expect(modal.find('#usuario-prefeitura').exists()).toBe(false);

        await modal.find('#usuario-nome').setValue('Carla Nova');
        await modal.find('#usuario-email').setValue('carla@prefeitura.gov.br');
        await modal.find('form').trigger('submit');
        await flushPromises();

        expect(api.post).toHaveBeenCalledWith('/users', { name: 'Carla Nova', email: 'carla@prefeitura.gov.br', role: 'gestor_convenios' });
        expect(modal.emitted('salvo')[0][0]).toEqual({ usuario: usuarios[1], senha: SENHA });
    });

    it('super administrador escolhe a prefeitura', async () => {
        entrarComo('administrador_interno', 99);
        api.post.mockResolvedValue({ data: usuarios[1], senha_temporaria: SENHA });
        const modal = mount(UsuarioFormModal, { props: { usuario: null, prefeituras: [{ id: 'a', razao_social: 'Prefeitura X', active: true }] } });

        await modal.find('#usuario-prefeitura').setValue('a');
        await modal.find('#usuario-nome').setValue('Carla Nova');
        await modal.find('#usuario-email').setValue('carla@prefeitura.gov.br');
        await modal.find('form').trigger('submit');
        await flushPromises();

        expect(api.post).toHaveBeenCalledWith('/users', expect.objectContaining({ tenant_id: 'a' }));
    });

    it('oferece o papel de administrador da prefeitura', () => {
        entrarComo('administrador_prefeitura');
        const modal = mount(UsuarioFormModal, { props: { usuario: null } });

        expect(modal.find('#usuario-papel').text()).toContain('Administrador da Prefeitura');
    });

    it('na própria conta, papel e situação ficam travados e não são enviados', async () => {
        entrarComo('administrador_prefeitura', 1);
        api.put.mockResolvedValue({ data: usuarios[0] });
        const modal = mount(UsuarioFormModal, { props: { usuario: usuarios[0] } });

        expect(modal.find('#usuario-papel').attributes('disabled')).toBeDefined();
        expect(modal.find('input[type="checkbox"]').attributes('disabled')).toBeDefined();

        await modal.find('form').trigger('submit');
        await flushPromises();

        expect(api.put).toHaveBeenCalledWith('/users/1', { name: 'Ana Admin', email: 'ana@prefeitura.gov.br' });
    });

    it('mostra a recusa do servidor no alto do formulário', async () => {
        entrarComo('administrador_prefeitura', 5);
        api.put.mockRejectedValue(new ApiError(422, { message: 'x', errors: { active: ['Você não pode desativar a própria conta.'] } }));
        const modal = mount(UsuarioFormModal, { props: { usuario: usuarios[1] } });

        await modal.find('form').trigger('submit');
        await flushPromises();

        expect(modal.find('[role="alert"]').text()).toContain('Você não pode desativar a própria conta.');
    });
});

describe('tela de usuários', () => {
    it('administrador da prefeitura: sem filtro/coluna de prefeitura e sem buscar a lista de prefeituras', async () => {
        entrarComo('administrador_prefeitura');
        const tela = await abrirTela();

        expect(api.get).not.toHaveBeenCalledWith('/tenants', expect.anything());
        expect(tela.find('select[aria-label="Filtrar por prefeitura"]').exists()).toBe(false);
        expect(tela.findAll('th').map((th) => th.text())).not.toContain('Prefeitura');
        expect(tela.text()).toContain('Bruno Gestor');
    });

    it('super administrador vê o filtro e a coluna de prefeitura', async () => {
        entrarComo('administrador_interno', 99);
        const tela = await abrirTela();

        expect(api.get).toHaveBeenCalledWith('/tenants', { todas: 1 });
        expect(tela.find('select[aria-label="Filtrar por prefeitura"]').exists()).toBe(true);
        expect(tela.findAll('th').map((th) => th.text())).toContain('Prefeitura');
    });

    it('não oferece "Redefinir senha" na própria linha e marca quem ainda usa senha temporária', async () => {
        entrarComo('administrador_prefeitura', 1);
        const tela = await abrirTela();
        const linhas = tela.findAll('tbody tr');

        expect(linhas[0].text()).not.toContain('Redefinir senha');
        expect(linhas[0].text()).toContain('você');
        expect(linhas[1].text()).toContain('Redefinir senha');
        expect(linhas[1].text()).toContain('senha temporária');
    });

    it('senha temporária vencida aparece em vermelho e a redefinição informa até quando a nova vale', async () => {
        entrarComo('administrador_prefeitura', 1);
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        const vencida = { ...usuarios[1], senha_temporaria_expirada: true, senha_temporaria_expira_em: '2026-09-20T12:00:00Z' };
        api.get.mockImplementation((caminho) => Promise.resolve(caminho === '/users' ? { data: [usuarios[0], vencida], meta: { current_page: 1, last_page: 1 } } : { data: [] }));
        api.post.mockResolvedValue({ data: usuarios[1], senha_temporaria: SENHA, senha_temporaria_expira_em: '2026-10-03T12:00:00Z' });
        const tela = await abrirTela();

        const selo = tela.findAll('tbody tr')[1].find('span.bg-red-100');
        expect(selo.text()).toBe('senha temporária vencida');

        await tela.findAll('tbody tr')[1].findAll('button').find((b) => b.text() === 'Redefinir senha').trigger('click');
        await flushPromises();

        expect(tela.text()).toContain('A senha vale até');
    });

    describe('2FA', () => {
        const comDoisFatores = [
            { ...usuarios[0], two_factor_ativo: true, two_factor_obrigatorio: true },
            { ...usuarios[1], two_factor_ativo: true, two_factor_obrigatorio: false },
            { id: 3, name: 'Carla Colega', email: 'carla@prefeitura.gov.br', role: 'administrador_prefeitura', role_label: 'Administrador da Prefeitura', active: true, must_change_password: false, two_factor_ativo: true, two_factor_obrigatorio: true, tenant: { razao_social: 'Prefeitura X' } },
            { id: 4, name: 'Davi Fiscal', email: 'davi@prefeitura.gov.br', role: 'fiscal_controle_interno', role_label: 'Fiscal de Controle Interno', active: true, must_change_password: false, two_factor_ativo: false, two_factor_obrigatorio: false, tenant: { razao_social: 'Prefeitura X' } },
            { id: 5, name: 'Eva Nova', email: 'eva@prefeitura.gov.br', role: 'administrador_prefeitura', role_label: 'Administrador da Prefeitura', active: true, must_change_password: false, two_factor_ativo: false, two_factor_obrigatorio: true, tenant: { razao_social: 'Prefeitura X' } },
        ];

        const linhaDe = (tela, nome) => tela.findAll('tbody tr').find((tr) => tr.text().includes(nome));
        const botoes = (linha) => linha.findAll('button').map((b) => b.text());

        beforeEach(() => {
            api.get.mockImplementation(async (caminho) => (caminho === '/tenants'
                ? { data: [{ id: 'a', razao_social: 'Prefeitura X', active: true }] }
                : { data: comDoisFatores, meta: { current_page: 1, last_page: 1, total: 5 } }));
        });

        it('marca quem tem 2FA e quem ainda não ativou o obrigatório', async () => {
            entrarComo('administrador_prefeitura', 1);
            const tela = await abrirTela();

            expect(linhaDe(tela, 'Bruno Gestor').text()).toContain('2FA');
            expect(linhaDe(tela, 'Bruno Gestor').text()).not.toContain('2FA pendente');
            expect(linhaDe(tela, 'Eva Nova').text()).toContain('2FA pendente');
            // Fiscal sem 2FA não é obrigado: nada a marcar.
            expect(linhaDe(tela, 'Davi Fiscal').text()).not.toContain('2FA');
        });

        it('administrador da prefeitura redefine o de gestor e fiscal, nunca o próprio nem o de outro administrador', async () => {
            entrarComo('administrador_prefeitura', 1);
            const tela = await abrirTela();

            expect(botoes(linhaDe(tela, 'Bruno Gestor'))).toContain('Redefinir 2FA');
            expect(botoes(linhaDe(tela, 'Ana Admin'))).not.toContain('Redefinir 2FA');
            expect(botoes(linhaDe(tela, 'Carla Colega'))).not.toContain('Redefinir 2FA');
            // Sem 2FA ativo não há o que redefinir.
            expect(botoes(linhaDe(tela, 'Davi Fiscal'))).not.toContain('Redefinir 2FA');
        });

        it('super administrador também redefine o de administradores de prefeitura', async () => {
            entrarComo('administrador_interno', 99);
            const tela = await abrirTela();

            expect(botoes(linhaDe(tela, 'Carla Colega'))).toContain('Redefinir 2FA');
            expect(botoes(linhaDe(tela, 'Ana Admin'))).toContain('Redefinir 2FA');
        });

        it('pede a senha de quem redefine, redefine e avisa', async () => {
            entrarComo('administrador_prefeitura', 1);
            api.post.mockResolvedValue({ data: { ...comDoisFatores[1], two_factor_ativo: false } });
            const tela = await abrirTela();

            await linhaDe(tela, 'Bruno Gestor').findAll('button').find((b) => b.text() === 'Redefinir 2FA').trigger('click');
            const modal = tela.findComponent(ConfirmarSenhaModal);
            expect(modal.text()).toContain('Redefinir 2FA de Bruno Gestor');
            expect(modal.find('#confirmar-codigo').exists()).toBe(false);

            await modal.find('#confirmar-senha').setValue('MinhaSenha123');
            await modal.find('form').trigger('submit');
            await flushPromises();

            expect(api.post).toHaveBeenCalledWith('/users/2/redefinir-2fa', { password: 'MinhaSenha123' });
            expect(tela.findComponent(ConfirmarSenhaModal).exists()).toBe(false);
            expect(tela.find('[role="status"]').text()).toContain('Bruno Gestor redefinida');
            // A lista foi recarregada.
            expect(api.get.mock.calls.filter(([caminho]) => caminho === '/users').length).toBeGreaterThan(1);
        });

        it('senha errada mantém a janela aberta com o erro', async () => {
            entrarComo('administrador_prefeitura', 1);
            api.post.mockRejectedValue(new ApiError(422, { message: 'x', errors: { password: ['A senha não confere.'] } }));
            const tela = await abrirTela();

            await linhaDe(tela, 'Bruno Gestor').findAll('button').find((b) => b.text() === 'Redefinir 2FA').trigger('click');
            const modal = tela.findComponent(ConfirmarSenhaModal);
            await modal.find('#confirmar-senha').setValue('errada');
            await modal.find('form').trigger('submit');
            await flushPromises();

            expect(tela.findComponent(ConfirmarSenhaModal).text()).toContain('A senha não confere.');
            expect(tela.find('[role="status"]').exists()).toBe(false);
        });
    });

    it('redefinir pede confirmação e mostra a nova senha temporária uma vez', async () => {
        entrarComo('administrador_prefeitura', 1);
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        api.post.mockResolvedValue({ data: usuarios[1], senha_temporaria: SENHA });
        const tela = await abrirTela();

        await tela.findAll('tbody tr')[1].findAll('button').find((b) => b.text() === 'Redefinir senha').trigger('click');
        await flushPromises();

        expect(window.confirm).toHaveBeenCalledOnce();
        expect(api.post).toHaveBeenCalledWith('/users/2/redefinir-senha');
        expect(tela.find('output').text()).toBe(SENHA);
        expect(tela.text()).toContain('Senha redefinida');
    });

    it('se a pessoa cancelar a confirmação, nada é redefinido', async () => {
        entrarComo('administrador_prefeitura', 1);
        vi.spyOn(window, 'confirm').mockReturnValue(false);
        const tela = await abrirTela();

        await tela.findAll('tbody tr')[1].findAll('button').find((b) => b.text() === 'Redefinir senha').trigger('click');
        await flushPromises();

        expect(api.post).not.toHaveBeenCalled();
        expect(tela.find('output').exists()).toBe(false);
    });
});
