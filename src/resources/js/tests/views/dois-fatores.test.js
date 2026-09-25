import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import CodigosDeRecuperacao from '../../components/CodigosDeRecuperacao.vue';
import ConfirmarSenhaModal from '../../components/ConfirmarSenhaModal.vue';
import DoisFatoresAtivacao from '../../components/DoisFatoresAtivacao.vue';
import QrCode from '../../components/QrCode.vue';
import { api, ApiError, tokenStorage } from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import LoginView from '../../views/LoginView.vue';
import SegurancaView from '../../views/SegurancaView.vue';

vi.mock('../../services/api', async (importOriginal) => {
    const original = await importOriginal();

    return { ...original, api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() } };
});

const SEGREDO = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
const URL_OTPAUTH = `otpauth://totp/CTS%20Conv%C3%AAnios%3Aana%40prefeitura.gov.br?secret=${SEGREDO}&issuer=CTS%20Conv%C3%AAnios`;
const CODIGOS = ['AB23C-D4EFG', 'HJK56-MNPQR', 'STU78-VWXY9', '23456-789AB', 'CDEFG-HJKMN', 'PQRST-UVWXY', 'Z2345-6789A', 'BCDEF-GHJKL'];

const tela = { template: '<div />' };

function roteador() {
    return createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/login', name: 'login', component: tela },
            { path: '/esqueci-senha', name: 'esqueci-senha', component: tela },
            { path: '/', name: 'dashboard', component: tela },
        ],
    });
}

beforeEach(() => {
    localStorage.clear();
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

afterEach(() => vi.restoreAllMocks());

describe('auth store: login em duas etapas', () => {
    it('com 2FA o primeiro passo não guarda token: devolve o desafio', async () => {
        api.post.mockResolvedValue({ dois_fatores: true, desafio: 'd'.repeat(48) });
        const auth = useAuthStore();

        const passo = await auth.login('ana@prefeitura.gov.br', 'segredo123');

        expect(passo).toEqual({ desafio: 'd'.repeat(48) });
        expect(auth.isAuthenticated).toBe(false);
        expect(tokenStorage.get()).toBeNull();
    });

    it('sem 2FA entra direto e devolve null', async () => {
        api.post.mockResolvedValue({ token: 'tok', user: { name: 'Ana' } });
        const auth = useAuthStore();

        expect(await auth.login('ana@prefeitura.gov.br', 'segredo123')).toBeNull();
        expect(auth.isAuthenticated).toBe(true);
    });

    it('o segundo passo envia desafio + código e guarda o token e o usuário', async () => {
        api.post.mockResolvedValue({ token: 'tok2', user: { name: 'Ana', two_factor_ativo: true } });
        const auth = useAuthStore();

        await auth.verificarDoisFatores('d'.repeat(48), '123456');

        expect(api.post).toHaveBeenCalledWith('/login/2fa', { desafio: 'd'.repeat(48), codigo: '123456' });
        expect(auth.isAuthenticated).toBe(true);
        expect(auth.user.two_factor_ativo).toBe(true);
        expect(tokenStorage.get()).toBe('tok2');
    });

    it('só precisa ativar quem o servidor obriga e ainda não ativou', () => {
        const auth = useAuthStore();

        auth.user = { role: 'administrador_prefeitura', two_factor_obrigatorio: true, two_factor_ativo: false };
        expect(auth.precisaAtivarDoisFatores).toBe(true);

        auth.user = { role: 'administrador_prefeitura', two_factor_obrigatorio: true, two_factor_ativo: true };
        expect(auth.precisaAtivarDoisFatores).toBe(false);

        auth.user = { role: 'gestor_convenios', two_factor_obrigatorio: false, two_factor_ativo: false };
        expect(auth.precisaAtivarDoisFatores).toBe(false);

        auth.user = null;
        expect(auth.precisaAtivarDoisFatores).toBe(false);
    });
});

describe('guarda de rotas do 2FA', () => {
    async function irPara(usuario, destino) {
        vi.resetModules();
        const { default: router } = await import('../../router/index.js');
        const { useAuthStore: store } = await import('../../stores/auth');
        const auth = store();
        auth.token = 'tok';
        auth.user = usuario;

        await router.push(destino);

        return router.currentRoute.value.name;
    }

    it('administrador sem 2FA só enxerga a tela de ativação', async () => {
        const usuario = { role: 'administrador_prefeitura', must_change_password: false, two_factor_obrigatorio: true, two_factor_ativo: false };

        expect(await irPara(usuario, '/convenios')).toBe('ativar-2fa');
    });

    it('a troca da senha temporária vem antes da ativação', async () => {
        const usuario = { role: 'administrador_prefeitura', must_change_password: true, two_factor_obrigatorio: true, two_factor_ativo: false };

        expect(await irPara(usuario, '/convenios')).toBe('trocar-senha');
    });

    it('com o 2FA ativo a tela de ativação leva ao painel', async () => {
        const usuario = { role: 'administrador_prefeitura', must_change_password: false, two_factor_obrigatorio: true, two_factor_ativo: true };

        expect(await irPara(usuario, '/ativar-2fa')).toBe('dashboard');
    });

    it('gestor não é obrigado e usa a tela de segurança normalmente', async () => {
        const usuario = { role: 'gestor_convenios', must_change_password: false, two_factor_obrigatorio: false, two_factor_ativo: false };

        expect(await irPara(usuario, '/seguranca')).toBe('seguranca');
    });
});

describe('QrCode', () => {
    it('desenha o endereço otpauth como SVG, sem imagem externa', () => {
        const qr = mount(QrCode, { props: { texto: URL_OTPAUTH } });

        expect(qr.find('svg').attributes('role')).toBe('img');
        expect(qr.find('svg').attributes('aria-label')).toContain('QR Code');
        expect(qr.find('path').attributes('d')).toMatch(/^M\d+ \d+h1v1h-1z/);
        expect(qr.find('img').exists()).toBe(false);
    });

    it('textos diferentes geram desenhos diferentes', () => {
        const a = mount(QrCode, { props: { texto: URL_OTPAUTH } }).find('path').attributes('d');
        const b = mount(QrCode, { props: { texto: `${URL_OTPAUTH}x` } }).find('path').attributes('d');

        expect(a).not.toBe(b);
    });
});

describe('CodigosDeRecuperacao', () => {
    it('lista os códigos, copia todos e baixa um arquivo de texto', async () => {
        const escrever = vi.fn().mockResolvedValue();
        Object.defineProperty(navigator, 'clipboard', { value: { writeText: escrever }, configurable: true });
        URL.createObjectURL = vi.fn(() => 'blob:codigos');
        URL.revokeObjectURL = vi.fn();
        let baixado = '';
        vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function baixar() {
            baixado = this.download;
        });
        const lista = mount(CodigosDeRecuperacao, { props: { codigos: CODIGOS } });

        expect(lista.findAll('li').filter((li) => CODIGOS.includes(li.text()))).toHaveLength(8);
        expect(lista.text()).toContain('só agora');

        await lista.findAll('button')[0].trigger('click');
        await flushPromises();
        expect(escrever).toHaveBeenCalledWith(CODIGOS.join('\n'));
        expect(lista.text()).toContain('Copiados!');

        await lista.findAll('button')[1].trigger('click');
        expect(baixado).toBe('codigos-recuperacao-cts-convenios.txt');
    });

    it('sem permissão de copiar, avisa e deixa os códigos visíveis', async () => {
        Object.defineProperty(navigator, 'clipboard', { value: { writeText: vi.fn().mockRejectedValue(new Error('negado')) }, configurable: true });
        const lista = mount(CodigosDeRecuperacao, { props: { codigos: CODIGOS } });

        await lista.findAll('button')[0].trigger('click');
        await flushPromises();

        expect(lista.find('[role="alert"]').text()).toContain('Não foi possível copiar');
        expect(lista.text()).toContain(CODIGOS[0]);
    });
});

describe('DoisFatoresAtivacao', () => {
    it('três passos: senha, app autenticador e códigos de recuperação', async () => {
        api.post
            .mockResolvedValueOnce({ data: { segredo: SEGREDO, url: URL_OTPAUTH } })
            .mockResolvedValueOnce({ data: { codigos: CODIGOS } });
        const ativacao = mount(DoisFatoresAtivacao);

        // 1. senha
        await ativacao.find('#dois-fatores-senha').setValue('MinhaSenha123');
        await ativacao.find('form').trigger('submit');
        await flushPromises();
        expect(api.post).toHaveBeenNthCalledWith(1, '/2fa/iniciar', { password: 'MinhaSenha123' });

        // 2. app: QR Code, chave em blocos de 4 e o campo do código
        expect(ativacao.findComponent(QrCode).props('texto')).toBe(URL_OTPAUTH);
        expect(ativacao.find('output[aria-label="Chave de configuração"]').text()).toBe('JBSW Y3DP EHPK 3PXP JBSW Y3DP EHPK 3PXP');
        await ativacao.find('#dois-fatores-codigo').setValue(' 123456 ');
        await ativacao.find('form').trigger('submit');
        await flushPromises();
        expect(api.post).toHaveBeenNthCalledWith(2, '/2fa/confirmar', { codigo: '123456' });

        // 3. códigos de recuperação (e o segredo já não está na tela)
        expect(ativacao.findComponent(CodigosDeRecuperacao).props('codigos')).toEqual(CODIGOS);
        expect(ativacao.text()).toContain('Verificação em duas etapas ativada');
        expect(ativacao.findComponent(QrCode).exists()).toBe(false);
        expect(ativacao.text()).not.toContain('JBSW');

        await ativacao.findAll('button').find((b) => b.text().startsWith('Guardei')).trigger('click');
        expect(ativacao.emitted('ativado')).toHaveLength(1);
    });

    it('senha errada mostra o erro e continua no primeiro passo', async () => {
        api.post.mockRejectedValue(new ApiError(422, { message: 'x', errors: { password: ['A senha não confere.'] } }));
        const ativacao = mount(DoisFatoresAtivacao);

        await ativacao.find('#dois-fatores-senha').setValue('errada');
        await ativacao.find('form').trigger('submit');
        await flushPromises();

        expect(ativacao.find('[role="alert"]').text()).toBe('A senha não confere.');
        expect(ativacao.find('#dois-fatores-senha').exists()).toBe(true);
        expect(ativacao.findComponent(QrCode).exists()).toBe(false);
    });

    it('código errado mostra o erro e deixa tentar de novo', async () => {
        api.post
            .mockResolvedValueOnce({ data: { segredo: SEGREDO, url: URL_OTPAUTH } })
            .mockRejectedValueOnce(new ApiError(422, { message: 'x', errors: { codigo: ['Código inválido ou vencido. Confira o app e o horário do celular.'] } }));
        const ativacao = mount(DoisFatoresAtivacao);

        await ativacao.find('#dois-fatores-senha').setValue('MinhaSenha123');
        await ativacao.find('form').trigger('submit');
        await flushPromises();
        await ativacao.find('#dois-fatores-codigo').setValue('000000');
        await ativacao.find('form').trigger('submit');
        await flushPromises();

        expect(ativacao.find('[role="alert"]').text()).toContain('Código inválido ou vencido');
        expect(ativacao.find('#dois-fatores-codigo').exists()).toBe(true);
    });
});

describe('ConfirmarSenhaModal', () => {
    it('envia a senha (e o código, quando pedido) e entrega o resultado', async () => {
        const acao = vi.fn().mockResolvedValue({ ok: true });
        const modal = mount(ConfirmarSenhaModal, { props: { titulo: 'Desativar', pedeCodigo: true, acao } });

        await modal.find('#confirmar-senha').setValue('MinhaSenha123');
        await modal.find('#confirmar-codigo').setValue(' 654321 ');
        await modal.find('form').trigger('submit');
        await flushPromises();

        expect(acao).toHaveBeenCalledWith({ password: 'MinhaSenha123', codigo: '654321' });
        expect(modal.emitted('concluido')[0][0]).toEqual({ ok: true });
    });

    it('sem o pedido de código, o campo nem aparece', () => {
        const modal = mount(ConfirmarSenhaModal, { props: { titulo: 'Gerar', acao: vi.fn() } });

        expect(modal.find('#confirmar-codigo').exists()).toBe(false);
    });

    it('mostra o erro do campo e a recusa geral do servidor', async () => {
        const acao = vi.fn()
            .mockRejectedValueOnce(new ApiError(422, { message: 'x', errors: { password: ['A senha não confere.'] } }))
            .mockRejectedValueOnce(new ApiError(422, { message: 'x', errors: { dois_fatores: ['A verificação em duas etapas é obrigatória para o seu perfil.'] } }));
        const modal = mount(ConfirmarSenhaModal, { props: { titulo: 'Desativar', acao } });

        await modal.find('#confirmar-senha').setValue('errada');
        await modal.find('form').trigger('submit');
        await flushPromises();
        expect(modal.text()).toContain('A senha não confere.');
        expect(modal.emitted('concluido')).toBeUndefined();

        await modal.find('form').trigger('submit');
        await flushPromises();
        expect(modal.find('[role="alert"]').text()).toContain('obrigatória para o seu perfil');
    });

    it('cancelar fecha sem chamar a ação', async () => {
        const acao = vi.fn();
        const modal = mount(ConfirmarSenhaModal, { props: { titulo: 'Gerar', acao } });

        await modal.findAll('button').find((b) => b.text() === 'Cancelar').trigger('click');

        expect(modal.emitted('fechar')).toHaveLength(1);
        expect(acao).not.toHaveBeenCalled();
    });
});

describe('SegurancaView', () => {
    function abrir(usuario) {
        const auth = useAuthStore();
        auth.token = 'tok';
        auth.user = usuario;
        api.get.mockResolvedValue({ ...usuario });

        return mount(SegurancaView, { global: { plugins: [roteador()] }, attachTo: document.body });
    }

    const ativo = { id: 1, name: 'Ana', role: 'gestor_convenios', two_factor_ativo: true, two_factor_obrigatorio: false, two_factor_codigos_restantes: 5 };

    it('sem 2FA mostra a ativação', () => {
        const tela = abrir({ ...ativo, two_factor_ativo: false, two_factor_codigos_restantes: 0 });

        expect(tela.text()).toContain('Desativada');
        expect(tela.findComponent(DoisFatoresAtivacao).exists()).toBe(true);
        tela.unmount();
    });

    it('com 2FA mostra o estado, os códigos que restam e as ações', () => {
        const tela = abrir(ativo);

        expect(tela.text()).toContain('Ativa');
        expect(tela.text()).toContain('Códigos de recuperação que ainda valem: 5');
        expect(tela.text()).not.toContain('Gere novos antes que acabem');
        expect(tela.text()).toContain('Gerar novos códigos');
        expect(tela.text()).toContain('Desativar');
        tela.unmount();
    });

    it('avisa quando os códigos estão acabando', () => {
        const tela = abrir({ ...ativo, two_factor_codigos_restantes: 1 });

        expect(tela.text()).toContain('Gere novos antes que acabem');
        tela.unmount();
    });

    it('perfil obrigado não tem o botão de desativar', () => {
        const tela = abrir({ ...ativo, role: 'administrador_prefeitura', two_factor_obrigatorio: true });

        expect(tela.text()).toContain('É obrigatória para o seu perfil');
        expect(tela.findAll('button').map((b) => b.text())).not.toContain('Desativar');
        tela.unmount();
    });

    it('gerar novos códigos pede a senha e mostra os códigos uma vez', async () => {
        const tela = abrir(ativo);
        api.post.mockResolvedValue({ data: { codigos: CODIGOS } });

        await tela.findAll('button').find((b) => b.text() === 'Gerar novos códigos').trigger('click');
        const campo = tela.findComponent(ConfirmarSenhaModal).find('#confirmar-senha');
        await campo.setValue('MinhaSenha123');
        await tela.findComponent(ConfirmarSenhaModal).find('form').trigger('submit');
        await flushPromises();

        expect(api.post).toHaveBeenCalledWith('/2fa/codigos-recuperacao', { password: 'MinhaSenha123' });
        expect(tela.findComponent(CodigosDeRecuperacao).props('codigos')).toEqual(CODIGOS);
        expect(tela.text()).toContain('Novos códigos de recuperação');

        // Esc não fecha: só o botão (fechar sem querer perderia os códigos).
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await flushPromises();
        expect(tela.findComponent(CodigosDeRecuperacao).exists()).toBe(true);
        await tela.findAll('button').find((b) => b.text().startsWith('Guardei os códigos, fechar')).trigger('click');
        expect(tela.findComponent(CodigosDeRecuperacao).exists()).toBe(false);
        tela.unmount();
    });

    it('desativar envia senha e código e recarrega o usuário', async () => {
        const tela = abrir(ativo);
        api.delete.mockResolvedValue({ message: 'ok' });
        api.get.mockResolvedValue({ ...ativo, two_factor_ativo: false, two_factor_codigos_restantes: 0 });

        await tela.findAll('button').find((b) => b.text() === 'Desativar').trigger('click');
        const modal = tela.findComponent(ConfirmarSenhaModal);
        await modal.find('#confirmar-senha').setValue('MinhaSenha123');
        await modal.find('#confirmar-codigo').setValue('123456');
        await modal.find('form').trigger('submit');
        await flushPromises();

        expect(api.delete).toHaveBeenCalledWith('/2fa', { password: 'MinhaSenha123', codigo: '123456' });
        expect(api.get).toHaveBeenCalledWith('/me');
        expect(tela.text()).toContain('Verificação em duas etapas desativada');
        expect(tela.text()).toContain('Desativada');
        tela.unmount();
    });
});

describe('LoginView em duas etapas', () => {
    async function abrirLogin() {
        const router = roteador();
        router.push('/login');
        await router.isReady();
        const login = mount(LoginView, { global: { plugins: [router] } });

        return { login, router };
    }

    async function entrarComSenha(login) {
        await login.find('#email').setValue('ana@prefeitura.gov.br');
        await login.find('#password').setValue('MinhaSenha123');
        await login.find('form').trigger('submit');
        await flushPromises();
    }

    it('depois da senha pede o código e só então entra', async () => {
        api.post
            .mockResolvedValueOnce({ dois_fatores: true, desafio: 'd'.repeat(48) })
            .mockResolvedValueOnce({ token: 'tok', user: { name: 'Ana' } });
        const { login, router } = await abrirLogin();

        await entrarComSenha(login);
        expect(login.text()).toContain('Verificação em duas etapas');
        expect(login.find('#password').exists()).toBe(false);
        expect(router.currentRoute.value.name).toBe('login');

        await login.find('#codigo').setValue('123456');
        await login.find('form').trigger('submit');
        await flushPromises();

        expect(api.post).toHaveBeenLastCalledWith('/login/2fa', { desafio: 'd'.repeat(48), codigo: '123456' });
        expect(router.currentRoute.value.name).toBe('dashboard');
    });

    it('código errado mostra o erro e deixa tentar de novo', async () => {
        api.post
            .mockResolvedValueOnce({ dois_fatores: true, desafio: 'd'.repeat(48) })
            .mockRejectedValueOnce(new ApiError(422, { message: 'x', errors: { codigo: ['Código inválido ou vencido.'] } }));
        const { login } = await abrirLogin();

        await entrarComSenha(login);
        await login.find('#codigo').setValue('000000');
        await login.find('form').trigger('submit');
        await flushPromises();

        expect(login.find('[role="alert"]').text()).toBe('Código inválido ou vencido.');
        expect(login.find('#codigo').exists()).toBe(true);
    });

    it('desafio expirado volta ao e-mail e senha com a mensagem', async () => {
        api.post
            .mockResolvedValueOnce({ dois_fatores: true, desafio: 'd'.repeat(48) })
            .mockRejectedValueOnce(new ApiError(422, { message: 'x', errors: { desafio: ['A verificação expirou. Entre novamente com e-mail e senha.'] } }));
        const { login } = await abrirLogin();

        await entrarComSenha(login);
        await login.find('#codigo').setValue('000000');
        await login.find('form').trigger('submit');
        await flushPromises();

        expect(login.find('#password').exists()).toBe(true);
        expect(login.find('[role="alert"]').text()).toContain('A verificação expirou');
    });

    it('alterna para o código de recuperação e volta', async () => {
        api.post.mockResolvedValueOnce({ dois_fatores: true, desafio: 'd'.repeat(48) });
        const { login } = await abrirLogin();

        await entrarComSenha(login);
        expect(login.find('#codigo').attributes('inputmode')).toBe('numeric');

        await login.findAll('button').find((b) => b.text().startsWith('Perdi o celular')).trigger('click');
        expect(login.text()).toContain('Digite um dos seus códigos de recuperação');
        expect(login.find('#codigo').attributes('inputmode')).toBe('text');
        expect(login.find('#codigo').attributes('placeholder')).toBe('XXXXX-XXXXX');

        await login.findAll('button').find((b) => b.text() === 'Voltar').trigger('click');
        expect(login.find('#password').exists()).toBe(true);
        expect(login.find('#password').element.value).toBe('');
    });
});
