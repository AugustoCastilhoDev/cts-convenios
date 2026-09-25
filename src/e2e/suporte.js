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

export const CONTA_TROCA = { email: 'e2e.troca@exemplo.gov.br' };
export const CONTA_ADMIN_PREFEITURA = { email: 'e2e.adminpref@exemplo.gov.br', senhaPropria: 'SenhaDoAdminE2E-2026' };
export const PREFIXO_EMAIL_E2E = 'e2e.';

/**
 * Deixa uma conta de teste pronta com uma senha temporária NOVA: cria a conta (ou reativa e redefine) e
 * devolve a senha que o sistema gerou, exatamente como o administrador a vê na tela. Idempotente.
 */
async function prepararConta(email, role, nome) {
    const api = await apiAdmin();

    const prefeituras = await (await api.get('/api/tenants')).json();
    const tenantId = prefeituras.data[0].id;

    const existentes = await (await api.get(`/api/users?busca=${encodeURIComponent(email)}`)).json();
    const conta = existentes.data.find((u) => u.email === email);

    let resposta;
    if (conta) {
        await api.put(`/api/users/${conta.id}`, { data: { active: true, role } });
        resposta = await api.post(`/api/users/${conta.id}/redefinir-senha`);
    } else {
        resposta = await api.post('/api/users', { data: { name: nome, email, role, tenant_id: tenantId } });
    }

    if (!resposta.ok()) {
        throw new Error(`Não foi possível preparar a conta ${email} (${resposta.status()}): ${await resposta.text()}`);
    }

    const { senha_temporaria: senha } = await resposta.json();
    await api.dispose();

    return senha;
}

export const prepararContaComSenhaTemporaria = () => prepararConta(CONTA_TROCA.email, 'gestor_convenios', 'E2E Troca de Senha');

/**
 * Administrador da prefeitura de teste, já com a senha própria definida (a temporária foi trocada por API),
 * e o token dele para abrir o sistema sem passar pela tela de login (que tem limite de tentativas).
 */
export async function prepararAdministradorDaPrefeitura() {
    const temporaria = await prepararConta(CONTA_ADMIN_PREFEITURA.email, 'administrador_prefeitura', 'E2E Administrador da Prefeitura');
    const api = await request.newContext({ baseURL: baseURL(), extraHTTPHeaders: { Accept: 'application/json' } });

    const login = await api.post('/api/login', { data: { email: CONTA_ADMIN_PREFEITURA.email, password: temporaria, device_name: 'e2e' } });
    const { token } = await login.json();

    const troca = await request.newContext({ baseURL: baseURL(), extraHTTPHeaders: { Accept: 'application/json', Authorization: `Bearer ${token}` } });
    const resposta = await troca.put('/api/me/password', { data: { current_password: temporaria, password: CONTA_ADMIN_PREFEITURA.senhaPropria } });
    if (!resposta.ok()) {
        throw new Error(`Não foi possível definir a senha do administrador de teste (${resposta.status()}).`);
    }

    await api.dispose();
    await troca.dispose();

    return token;
}

/** Abre o sistema com um token qualquer (sem passar pela tela de login). */
export async function entrarComToken(page, token) {
    await page.addInitScript((t) => localStorage.setItem('cts_token', t), token);
}

/** Desativa todas as contas criadas pelos testes (e-mail começando por "e2e."): usuários não são apagados. */
export async function desativarContasDeTeste() {
    const api = await apiAdmin();
    const contas = await (await api.get(`/api/users?busca=${encodeURIComponent(PREFIXO_EMAIL_E2E)}&por_pagina=200`)).json();

    for (const conta of contas.data ?? []) {
        if (conta.email.startsWith(PREFIXO_EMAIL_E2E) && conta.active) {
            await api.put(`/api/users/${conta.id}`, { data: { active: false } });
        }
    }

    await api.dispose();
}

/** Data daqui a N dias no formato de um <input type="date">. */
export function diasAFrente(dias) {
    return new Date(Date.now() + dias * 86_400_000).toISOString().slice(0, 10);
}
