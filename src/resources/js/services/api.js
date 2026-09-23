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

export const api = {
    get: (path, params) => request('GET', path, { params }),
    post: (path, body) => request('POST', path, { body }),
    put: (path, body) => request('PUT', path, { body }),
    delete: (path) => request('DELETE', path),
};
