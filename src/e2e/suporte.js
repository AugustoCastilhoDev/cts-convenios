import { request } from '@playwright/test';

export const PREFIXO = 'E2E';

export const contas = {
    admin: { email: 'admin@ctsconvenios.com.br', senha: 'password' },
    gestor: { email: 'gestor@municipio-exemplo.gov.br', senha: 'password' },
    fiscal: { email: 'fiscal@municipio-exemplo.gov.br', senha: 'password' },
};

const baseURL = () => process.env.E2E_BASE_URL ?? 'http://localhost:8000';

// O login tem limite de 5 tentativas por minuto: um token por papel por execução basta.
const tokens = {};

/** Token da API de uma das contas de teste (login uma vez e guarda). */
export async function tokenDe(papel) {
    if (tokens[papel]) {
        return tokens[papel];
    }

    const api = await request.newContext({ baseURL: baseURL() });
    const resposta = await api.post('/api/login', {
        headers: { Accept: 'application/json' },
        data: { email: contas[papel].email, password: contas[papel].senha, device_name: 'e2e' },
    });

    if (!resposta.ok()) {
        throw new Error(`Login de ${papel} falhou (${resposta.status()}). O banco tem as contas do DatabaseSeeder?`);
    }

    tokens[papel] = (await resposta.json()).token;
    await api.dispose();

    return tokens[papel];
}

/** Abre o sistema já autenticado, sem passar pela tela de login (que tem limite de tentativas). */
export async function entrarComo(page, papel) {
    const token = await tokenDe(papel);

    await page.addInitScript((t) => localStorage.setItem('cts_token', t), token);
}

/** Cliente da API autenticado como admin, para preparar e limpar dados. */
export async function apiAdmin() {
    const token = await tokenDe('admin');

    return request.newContext({
        baseURL: baseURL(),
        extraHTTPHeaders: { Accept: 'application/json', Authorization: `Bearer ${token}` },
    });
}

/** Apaga tudo o que os testes criaram (convênios e pedidos de contato com o prefixo E2E). */
export async function limparDadosDeTeste() {
    const api = await apiAdmin();

    const convenios = await (await api.get(`/api/convenios?busca=${PREFIXO}-&por_pagina=200`)).json();
    for (const convenio of convenios.data ?? []) {
        await api.delete(`/api/convenios/${convenio.id}`);
    }

    const contatos = await (await api.get(`/api/contatos?busca=${PREFIXO}&por_pagina=200`)).json();
    for (const contato of contatos.data ?? []) {
        await api.delete(`/api/contatos/${contato.id}`);
    }

    await api.dispose();
}

export const CONTA_TROCA = { email: 'e2e.troca@exemplo.gov.br', temporaria: 'TemporariaE2E-2026' };

/**
 * Deixa a conta de teste da "senha temporária" pronta: cria (ou reativa) e define a senha temporária,
 * o que também liga a marca de troca obrigatória. Idempotente: pode rodar toda vez.
 */
export async function prepararContaComSenhaTemporaria() {
    const api = await apiAdmin();

    const prefeituras = await (await api.get('/api/tenants')).json();
    const tenantId = prefeituras.data[0].id;

    const existentes = await (await api.get(`/api/users?busca=${encodeURIComponent(CONTA_TROCA.email)}`)).json();
    const conta = existentes.data.find((u) => u.email === CONTA_TROCA.email);

    const resposta = conta
        ? await api.put(`/api/users/${conta.id}`, { data: { password: CONTA_TROCA.temporaria, active: true } })
        : await api.post('/api/users', {
            data: { name: 'E2E Troca de Senha', email: CONTA_TROCA.email, password: CONTA_TROCA.temporaria, role: 'gestor_convenios', tenant_id: tenantId },
        });

    if (!resposta.ok()) {
        throw new Error(`Não foi possível preparar a conta de teste (${resposta.status()}): ${await resposta.text()}`);
    }

    await api.dispose();
}

/** Desativa a conta de teste da senha temporária (usuários não são apagados; a conta fica fora de uso). */
export async function desativarContaDeTroca() {
    const api = await apiAdmin();
    const existentes = await (await api.get(`/api/users?busca=${encodeURIComponent(CONTA_TROCA.email)}`)).json();
    const conta = (existentes.data ?? []).find((u) => u.email === CONTA_TROCA.email);

    if (conta) {
        await api.put(`/api/users/${conta.id}`, { data: { active: false } });
    }

    await api.dispose();
}

/** Data daqui a N dias no formato de um <input type="date">. */
export function diasAFrente(dias) {
    return new Date(Date.now() + dias * 86_400_000).toISOString().slice(0, 10);
}
