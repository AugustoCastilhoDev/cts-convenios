import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '../../services/api';
import { useNotificacoesStore } from '../../stores/notificacoes';

vi.mock('../../services/api', () => ({
    api: { get: vi.fn(), post: vi.fn() },
}));

const lista = () => ({
    data: [
        { id: 'a', lida: false, dias: 15 },
        { id: 'b', lida: false, dias: 30 },
        { id: 'c', lida: true, dias: 60 },
    ],
    meta: { nao_lidas: 2 },
});

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    vi.useFakeTimers();
});

afterEach(() => {
    useNotificacoesStore().parar();
    vi.useRealTimers();
});

describe('carregar', () => {
    it('guarda os itens e a contagem de não lidas', async () => {
        api.get.mockResolvedValue(lista());
        const store = useNotificacoesStore();

        await store.carregar();

        expect(store.itens).toHaveLength(3);
        expect(store.naoLidas).toBe(2);
        expect(store.carregado).toBe(true);
    });

    it('falha de rede é silenciosa e mantém o que já havia', async () => {
        api.get.mockResolvedValueOnce(lista()).mockRejectedValueOnce(new Error('offline'));
        const store = useNotificacoesStore();

        await store.carregar();
        await store.carregar();

        expect(store.itens).toHaveLength(3);
        expect(store.naoLidas).toBe(2);
    });
});

describe('marcar como lida', () => {
    it('atualiza a tela na hora e avisa o servidor', async () => {
        api.get.mockResolvedValue(lista());
        api.post.mockResolvedValue(null);
        const store = useNotificacoesStore();
        await store.carregar();

        await store.marcarLida(store.itens[0]);

        expect(store.itens[0].lida).toBe(true);
        expect(store.naoLidas).toBe(1);
        expect(api.post).toHaveBeenCalledWith('/notificacoes/a/lida');
    });

    it('item já lido não gera chamada', async () => {
        api.get.mockResolvedValue(lista());
        const store = useNotificacoesStore();
        await store.carregar();

        await store.marcarLida(store.itens[2]);

        expect(api.post).not.toHaveBeenCalled();
        expect(store.naoLidas).toBe(2);
    });

    it('se o servidor recusar, recarrega para corrigir a tela', async () => {
        api.get.mockResolvedValue(lista());
        api.post.mockRejectedValue(new Error('falhou'));
        const store = useNotificacoesStore();
        await store.carregar();

        await store.marcarLida(store.itens[0]);

        expect(api.get).toHaveBeenCalledTimes(2);
    });

    it('a contagem nunca fica negativa', async () => {
        api.post.mockResolvedValue(null);
        const store = useNotificacoesStore();
        store.itens = [{ id: 'x', lida: false }];
        store.naoLidas = 0;

        await store.marcarLida(store.itens[0]);

        expect(store.naoLidas).toBe(0);
    });

    it('marcar todas zera a contagem', async () => {
        api.get.mockResolvedValue(lista());
        api.post.mockResolvedValue(null);
        const store = useNotificacoesStore();
        await store.carregar();

        await store.marcarTodasLidas();

        expect(store.itens.every((i) => i.lida)).toBe(true);
        expect(store.naoLidas).toBe(0);
        expect(api.post).toHaveBeenCalledWith('/notificacoes/lidas');
    });
});

describe('consulta periódica', () => {
    it('consulta ao iniciar e a cada minuto, e para ao sair', async () => {
        api.get.mockResolvedValue(lista());
        const store = useNotificacoesStore();

        store.iniciar();
        expect(api.get).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(60_000);
        expect(api.get).toHaveBeenCalledTimes(2);

        store.parar();
        await vi.advanceTimersByTimeAsync(180_000);
        expect(api.get).toHaveBeenCalledTimes(2);
    });

    it('limpar esvazia tudo (troca de usuário)', async () => {
        api.get.mockResolvedValue(lista());
        const store = useNotificacoesStore();
        await store.carregar();

        store.limpar();

        expect(store.itens).toEqual([]);
        expect(store.naoLidas).toBe(0);
        expect(store.carregado).toBe(false);
    });
});
