import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError, api, baixarRelatorio, setUnauthorizedHandler, tokenStorage } from '../../services/api';

function resposta(corpo, { status = 200, cabecalhos = {} } = {}) {
    return new Response(corpo === null ? null : JSON.stringify(corpo), { status, headers: cabecalhos });
}

beforeEach(() => {
    localStorage.clear();
    vi.stubGlobal('fetch', vi.fn());
    setUnauthorizedHandler(() => {});
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('requisições', () => {
    it('envia o token guardado como Bearer', async () => {
        tokenStorage.set('abc123');
        fetch.mockResolvedValue(resposta({ ok: true }));

        await api.get('/me');

        const [url, opcoes] = fetch.mock.calls[0];
        expect(String(url)).toMatch(/\/api\/me$/);
        expect(opcoes.headers.Authorization).toBe('Bearer abc123');
        expect(opcoes.headers.Accept).toBe('application/json');
    });

    it('sem token não manda o cabeçalho de autorização', async () => {
        fetch.mockResolvedValue(resposta({}));

        await api.get('/convenios');

        expect(fetch.mock.calls[0][1].headers.Authorization).toBeUndefined();
    });

    it('descarta parâmetros vazios na query', async () => {
        fetch.mockResolvedValue(resposta({}));

        await api.get('/convenios', { busca: 'ab', status: '', secretaria: null, page: 2, outro: undefined });

        const url = new URL(String(fetch.mock.calls[0][0]));
        expect(url.searchParams.get('busca')).toBe('ab');
        expect(url.searchParams.get('page')).toBe('2');
        expect([...url.searchParams.keys()]).toEqual(['busca', 'page']);
    });

    it('envia JSON no corpo do POST', async () => {
        fetch.mockResolvedValue(resposta({ id: 1 }, { status: 201 }));

        await api.post('/convenios', { numero_convenio: '1/2026' });

        const opcoes = fetch.mock.calls[0][1];
        expect(opcoes.method).toBe('POST');
        expect(opcoes.headers['Content-Type']).toBe('application/json');
        expect(JSON.parse(opcoes.body)).toEqual({ numero_convenio: '1/2026' });
    });

    it('deixa o navegador definir o Content-Type de um upload (FormData)', async () => {
        fetch.mockResolvedValue(resposta({}));
        const dados = new FormData();
        dados.append('arquivo', new Blob(['x']), 'x.pdf');

        await api.post('/convenios/1/arquivos', dados);

        const opcoes = fetch.mock.calls[0][1];
        expect(opcoes.body).toBe(dados);
        expect(opcoes.headers['Content-Type']).toBeUndefined();
    });

    it('204 devolve null', async () => {
        fetch.mockResolvedValue(new Response(null, { status: 204 }));

        await expect(api.delete('/convenios/1')).resolves.toBeNull();
    });
});

describe('erros', () => {
    it('erro de validação vira ApiError com os erros por campo', async () => {
        fetch.mockResolvedValue(resposta({ message: 'Dados inválidos.', errors: { email: ['Obrigatório'] } }, { status: 422 }));

        const erro = await api.post('/users', {}).catch((e) => e);

        expect(erro).toBeInstanceOf(ApiError);
        expect(erro.status).toBe(422);
        expect(erro.message).toBe('Dados inválidos.');
        expect(erro.errors.email).toEqual(['Obrigatório']);
    });

    it('resposta que não é JSON ainda dá uma mensagem útil', async () => {
        fetch.mockResolvedValue(new Response('<html>502</html>', { status: 502 }));

        const erro = await api.get('/dashboard').catch((e) => e);

        expect(erro.status).toBe(502);
        expect(erro.message).toBe('Erro ao comunicar com o servidor.');
    });

    it('401 aciona o tratador de sessão expirada', async () => {
        const tratador = vi.fn();
        setUnauthorizedHandler(tratador);
        fetch.mockResolvedValue(resposta({ message: 'Unauthenticated.' }, { status: 401 }));

        await api.get('/me').catch(() => {});

        expect(tratador).toHaveBeenCalledOnce();
    });

    it('403 não derruba a sessão', async () => {
        const tratador = vi.fn();
        setUnauthorizedHandler(tratador);
        fetch.mockResolvedValue(resposta({ message: 'Forbidden' }, { status: 403 }));

        await api.get('/audits').catch(() => {});

        expect(tratador).not.toHaveBeenCalled();
    });
});

describe('baixarRelatorio', () => {
    it('lê o nome do arquivo sugerido pelo servidor', async () => {
        tokenStorage.set('t');
        fetch.mockResolvedValue(new Response('csv', { headers: { 'Content-Disposition': 'attachment; filename="convenios-2026-09-25.csv"' } }));

        const { blob, nome } = await baixarRelatorio('/convenios/exportar', { formato: 'csv' });

        expect(nome).toBe('convenios-2026-09-25.csv');
        expect(await blob.text()).toBe('csv');
        expect(fetch.mock.calls[0][1].headers.Authorization).toBe('Bearer t');
    });

    it('sem cabeçalho usa um nome padrão', async () => {
        fetch.mockResolvedValue(new Response('x'));

        expect((await baixarRelatorio('/x')).nome).toBe('relatorio');
    });
});

describe('tokenStorage', () => {
    it('guarda e limpa o token', () => {
        tokenStorage.set('abc');
        expect(tokenStorage.get()).toBe('abc');

        tokenStorage.clear();
        expect(tokenStorage.get()).toBeNull();
    });

    it('não quebra quando o armazenamento está bloqueado', () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('bloqueado');
        });

        expect(tokenStorage.get()).toBeNull();
    });
});
