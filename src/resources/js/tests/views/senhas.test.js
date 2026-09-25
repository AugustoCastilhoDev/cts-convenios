import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { api, ApiError } from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import EsqueciSenhaView from '../../views/EsqueciSenhaView.vue';
import RedefinirSenhaView from '../../views/RedefinirSenhaView.vue';
import TrocarSenhaView from '../../views/TrocarSenhaView.vue';

vi.mock('../../services/api', async (importOriginal) => {
    const original = await importOriginal();

    return { ...original, api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() } };
});

const tela = { template: '<div />' };

function criarRoteador() {
    return createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/login', name: 'login', component: tela },
            { path: '/esqueci-senha', name: 'esqueci-senha', component: EsqueciSenhaView },
            { path: '/redefinir-senha', name: 'redefinir-senha', component: RedefinirSenhaView },
            { path: '/trocar-senha', name: 'trocar-senha', component: TrocarSenhaView },
            { path: '/', name: 'dashboard', component: tela },
        ],
    });
}

async function abrir(componente, caminho) {
    const router = criarRoteador();
    router.push(caminho);
    await router.isReady();
    const pinia = createPinia();
    setActivePinia(pinia);
    const tela = mount(componente, { global: { plugins: [router, pinia] } });

    return { tela, router };
}

beforeEach(() => {
    localStorage.clear();
    vi.clearAllMocks();
});

describe('Esqueci minha senha', () => {
    it('mostra a mensagem do servidor e esconde o formulário depois do envio', async () => {
        api.post.mockResolvedValue({ message: 'Se este e-mail estiver cadastrado, enviamos um link.' });
        const { tela } = await abrir(EsqueciSenhaView, '/esqueci-senha');

        await tela.find('input[type="email"]').setValue('  ana@prefeitura.gov.br ');
        await tela.find('form').trigger('submit');
        await flushPromises();

        expect(api.post).toHaveBeenCalledWith('/esqueci-senha', { email: 'ana@prefeitura.gov.br' });
        expect(tela.text()).toContain('Se este e-mail estiver cadastrado');
        expect(tela.find('form').exists()).toBe(false);
    });

    it('mostra o erro quando o servidor recusa (por exemplo, limite de pedidos)', async () => {
        api.post.mockRejectedValue(new ApiError(429, { message: 'Too Many Attempts.' }));
        const { tela } = await abrir(EsqueciSenhaView, '/esqueci-senha');

        await tela.find('input[type="email"]').setValue('ana@prefeitura.gov.br');
        await tela.find('form').trigger('submit');
        await flushPromises();

        expect(tela.find('[role="alert"]').text()).toContain('Too Many Attempts.');
        expect(tela.find('form').exists()).toBe(true);
    });
});

describe('Redefinir senha (link do e-mail)', () => {
    it('lê o token e o e-mail do link e os tira do endereço', async () => {
        const { router } = await abrir(RedefinirSenhaView, '/redefinir-senha?token=abc123&email=ana%40prefeitura.gov.br');
        await flushPromises();

        expect(router.currentRoute.value.query).toEqual({});
    });

    it('envia token, e-mail e a nova senha e confirma a troca', async () => {
        api.post.mockResolvedValue({ message: 'Senha alterada.' });
        const { tela } = await abrir(RedefinirSenhaView, '/redefinir-senha?token=abc123&email=ana%40prefeitura.gov.br');

        const [nova, confirmacao] = tela.findAll('input[type="password"]');
        await nova.setValue('NovaSenhaForte123');
        await confirmacao.setValue('NovaSenhaForte123');
        await tela.find('form').trigger('submit');
        await flushPromises();

        expect(api.post).toHaveBeenCalledWith('/redefinir-senha', {
            token: 'abc123',
            email: 'ana@prefeitura.gov.br',
            password: 'NovaSenhaForte123',
            password_confirmation: 'NovaSenhaForte123',
        });
        expect(tela.text()).toContain('Senha alterada');
    });

    it('confirmação diferente não chega ao servidor', async () => {
        const { tela } = await abrir(RedefinirSenhaView, '/redefinir-senha?token=abc123&email=ana%40prefeitura.gov.br');

        const [nova, confirmacao] = tela.findAll('input[type="password"]');
        await nova.setValue('NovaSenhaForte123');
        await confirmacao.setValue('OutraCoisa1234');
        await tela.find('form').trigger('submit');
        await flushPromises();

        expect(api.post).not.toHaveBeenCalled();
        expect(tela.text()).toContain('A confirmação não confere');
    });

    it('link vencido ou já usado leva a pedir um novo', async () => {
        api.post.mockRejectedValue(new ApiError(422, { message: 'x', errors: { token: ['Este link é inválido ou expirou.'] } }));
        const { tela } = await abrir(RedefinirSenhaView, '/redefinir-senha?token=velho&email=ana%40prefeitura.gov.br');

        const [nova, confirmacao] = tela.findAll('input[type="password"]');
        await nova.setValue('NovaSenhaForte123');
        await confirmacao.setValue('NovaSenhaForte123');
        await tela.find('form').trigger('submit');
        await flushPromises();

        expect(tela.text()).toContain('Este link é inválido ou expirou');
        expect(tela.text()).toContain('Pedir um novo link');
        expect(tela.find('form').exists()).toBe(false);
    });

    it('senha fraca mostra o motivo no campo e mantém o formulário', async () => {
        api.post.mockRejectedValue(new ApiError(422, { message: 'x', errors: { password: ['A senha precisa ter pelo menos 10 caracteres.'] } }));
        const { tela } = await abrir(RedefinirSenhaView, '/redefinir-senha?token=abc&email=ana%40prefeitura.gov.br');

        const [nova, confirmacao] = tela.findAll('input[type="password"]');
        await nova.setValue('curta1');
        await confirmacao.setValue('curta1');
        await tela.find('form').trigger('submit');
        await flushPromises();

        expect(tela.text()).toContain('pelo menos 10 caracteres');
        expect(tela.find('form').exists()).toBe(true);
    });

    it('sem token ou sem e-mail no link, avisa na hora', async () => {
        const { tela } = await abrir(RedefinirSenhaView, '/redefinir-senha');

        expect(tela.text()).toContain('Este link é inválido ou expirou');
        expect(tela.find('form').exists()).toBe(false);
    });
});

describe('Troca obrigatória da senha temporária', () => {
    it('troca a senha, recarrega o usuário e segue para o painel', async () => {
        api.put.mockResolvedValue({ message: 'ok' });
        api.get.mockResolvedValue({ name: 'Ana', role: 'gestor_convenios', must_change_password: false });
        const { tela, router } = await abrir(TrocarSenhaView, '/trocar-senha');

        const [atual, nova, confirmacao] = tela.findAll('input[type="password"]');
        await atual.setValue('Temporaria123');
        await nova.setValue('MinhaSenhaPropria99');
        await confirmacao.setValue('MinhaSenhaPropria99');
        await tela.find('form').trigger('submit');
        await flushPromises();

        expect(api.put).toHaveBeenCalledWith('/me/password', { current_password: 'Temporaria123', password: 'MinhaSenhaPropria99' });
        expect(api.get).toHaveBeenCalledWith('/me');
        expect(useAuthStore().precisaTrocarSenha).toBe(false);
        expect(router.currentRoute.value.name).toBe('dashboard');
    });

    it('senha temporária errada mostra o erro e continua na tela', async () => {
        api.put.mockRejectedValue(new ApiError(422, { message: 'x', errors: { current_password: ['A senha atual não confere.'] } }));
        const { tela, router } = await abrir(TrocarSenhaView, '/trocar-senha');

        const [atual, nova, confirmacao] = tela.findAll('input[type="password"]');
        await atual.setValue('errada');
        await nova.setValue('MinhaSenhaPropria99');
        await confirmacao.setValue('MinhaSenhaPropria99');
        await tela.find('form').trigger('submit');
        await flushPromises();

        expect(tela.text()).toContain('A senha atual não confere.');
        expect(router.currentRoute.value.name).toBe('trocar-senha');
    });

    it('sair leva de volta ao login', async () => {
        api.post.mockResolvedValue({ message: 'Sessão encerrada.' });
        const { tela, router } = await abrir(TrocarSenhaView, '/trocar-senha');

        await tela.findAll('button').find((b) => b.text() === 'Sair').trigger('click');
        await flushPromises();

        expect(api.post).toHaveBeenCalledWith('/logout');
        expect(router.currentRoute.value.name).toBe('login');
    });
});
