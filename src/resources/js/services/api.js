const TOKEN_KEY = 'cts_token';

export const tokenStorage = {
    get() {
        try {
            return localStorage.getItem(TOKEN_KEY);
        } catch {
            return null;
        }
    },
    set(token) {
        try {
            localStorage.setItem(TOKEN_KEY, token);
        } catch {
            // navegação privada/armazenamento bloqueado: a sessão dura só até recarregar
        }
    },
    clear() {
        try {
            localStorage.removeItem(TOKEN_KEY);
        } catch {
            //
        }
    },
};

export class ApiError extends Error {
    constructor(status, data) {
        super(data?.message ?? 'Erro ao comunicar com o servidor.');
        this.status = status;
        // Erros de validação do Laravel: { campo: ['mensagem', ...] }
        this.errors = data?.errors ?? {};
    }
}

let onUnauthorized = () => {};

export function setUnauthorizedHandler(handler) {
    onUnauthorized = handler;
}

async function request(method, path, { body, params } = {}) {
    const url = new URL(`/api${path}`, window.location.origin);
    Object.entries(params ?? {}).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            url.searchParams.set(key, value);
        }
    });

    const headers = { Accept: 'application/json' };
    const token = tokenStorage.get();
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    const options = { method, headers };
    if (body instanceof FormData) {
        options.body = body;
    } else if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }

    const response = await fetch(url, options);

    if (response.status === 204) {
        return null;
    }

    const data = await response.json().catch(() => null);

    if (!response.ok) {
        // Token expirado ou revogado (ex.: usuário desativado): derruba a sessão local.
        if (response.status === 401) {
            onUnauthorized();
        }
        throw new ApiError(response.status, data);
    }

    return data;
}

async function buscarArquivo(path, params) {
    const url = new URL(`/api${path}`, window.location.origin);
    Object.entries(params ?? {}).forEach(([chave, valor]) => {
        if (valor !== undefined && valor !== null && valor !== '') {
            url.searchParams.set(chave, valor);
        }
    });

    const response = await fetch(url, {
        headers: { Accept: '*/*', Authorization: `Bearer ${tokenStorage.get()}` },
    });

    if (!response.ok) {
        if (response.status === 401) {
            onUnauthorized();
        }
        const data = await response.json().catch(() => null);
        throw new ApiError(response.status, data);
    }

    return response;
}

/**
 * Baixa um arquivo protegido: o navegador não anexa o Bearer token a um link
 * comum, então buscamos o conteúdo com fetch e entregamos como Blob.
 */
export async function baixarBlob(path, params) {
    return (await buscarArquivo(path, params)).blob();
}

/** Como baixarBlob, mas também devolve o nome sugerido pelo servidor (Content-Disposition). */
export async function baixarRelatorio(path, params) {
    const response = await buscarArquivo(path, params);
    const cabecalho = response.headers.get('Content-Disposition') ?? '';
    const nome = /filename="?([^";]+)"?/i.exec(cabecalho)?.[1] ?? 'relatorio';

    return { blob: await response.blob(), nome };
}

/** Entrega um Blob ao usuário como download. */
export function salvarBlob(blob, nome) {
    const endereco = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = endereco;
    link.download = nome;
    link.click();
    URL.revokeObjectURL(endereco);
}

export const api = {
    get: (path, params) => request('GET', path, { params }),
    post: (path, body) => request('POST', path, { body }),
    put: (path, body) => request('PUT', path, { body }),
    delete: (path, body) => request('DELETE', path, { body }),
};
