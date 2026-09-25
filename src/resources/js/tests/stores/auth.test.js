import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api, tokenStorage } from '../../services/api';
import { useAuthStore } from '../../stores/auth';

vi.mock('../../services/api', async (importOriginal) => {
    const original = await importOriginal();

    return { ...original, api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() } };
});

beforeEach(() => {
    localStorage.clear();
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('login', () => {
    it('guarda o token e o usuário', async () => {
        api.post.mockResolvedValue({ token: 'tok', user: { name: 'Ana', role: 'gestor_convenios' } });
        const auth = useAuthStore();

        await auth.login('ana@x.gov.br', 'segredo');

        expect(api.post).toHaveBeenCalledWith('/login', { email: 'ana@x.gov.br', password: 'segredo', device_name: 'spa-web' });
        expect(auth.isAuthenticated).toBe(true);
        expect(auth.user.name).toBe('Ana');
        expect(tokenStorage.get()).toBe('tok');
    });

    it('login recusado não deixa token guardado', async () => {
        api.post.mockRejectedValue(new Error('Credenciais inválidas'));
        const auth = useAuthStore();

        await expect(auth.login('a@b.c', 'x')).rejects.toThrow('Credenciais inválidas');

        expect(auth.isAuthenticated).toBe(false);
        expect(tokenStorage.get()).toBeNull();
    });
});

describe('sessão guardada', () => {
    it('começa autenticado quando já há token (recarregar a página)', () => {
        tokenStorage.set('antigo');

        expect(useAuthStore().isAuthenticated).toBe(true);
    });

    it('fetchUser recarrega o usuário do servidor', async () => {
        api.get.mockResolvedValue({ name: 'Bia', role: 'fiscal_controle_interno' });
        const auth = useAuthStore();

        await auth.fetchUser();

        expect(api.get).toHaveBeenCalledWith('/me');
        expect(auth.user.name).toBe('Bia');
    });

    it('logout limpa a sessão local mesmo se o servidor falhar', async () => {
        tokenStorage.set('tok');
        api.post.mockRejectedValue(new Error('token já inválido'));
        const auth = useAuthStore();
        auth.user = { name: 'Ana' };

        await auth.logout();

        expect(auth.user).toBeNull();
        expect(auth.isAuthenticated).toBe(false);
        expect(tokenStorage.get()).toBeNull();
    });
});

describe('permissões por papel (espelham as policies do servidor)', () => {
    const comPapel = (role) => {
        const auth = useAuthStore();
        auth.user = { role };

        return auth;
    };

    it('administrador interno pode tudo, inclusive excluir', () => {
        const auth = comPapel('administrador_interno');

        expect(auth.isAdmin).toBe(true);
        expect(auth.podeEditar).toBe(true);
        expect(auth.podeExcluir).toBe(true);
        expect(auth.temSino).toBe(false);
    });

    it('gestor edita, mas não exclui, e tem o sino de alertas', () => {
        const auth = comPapel('gestor_convenios');

        expect(auth.isAdmin).toBe(false);
        expect(auth.podeEditar).toBe(true);
        expect(auth.podeExcluir).toBe(false);
        expect(auth.temSino).toBe(true);
    });

    it('fiscal só consulta e tem o sino de alertas', () => {
        const auth = comPapel('fiscal_controle_interno');

        expect(auth.podeEditar).toBe(false);
        expect(auth.podeExcluir).toBe(false);
        expect(auth.temSino).toBe(true);
    });

    it('sem usuário carregado ninguém tem permissão', () => {
        const auth = useAuthStore();

        expect(auth.podeEditar).toBe(false);
        expect(auth.isAdmin).toBe(false);
    });
});
