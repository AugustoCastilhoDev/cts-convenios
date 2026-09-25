import { expect, test } from '@playwright/test';
import {
    CONTA_ADMIN_PREFEITURA,
    CONTA_DOIS_FATORES,
    codigoTotp,
    entrarComToken,
    prepararAdministradorDaPrefeitura,
    prepararContaParaDoisFatores,
} from './suporte.js';

// Verificação em duas etapas (app autenticador). Um teste depende do anterior.
// Nestes testes o 2FA é opcional (DOIS_FATORES_OBRIGATORIO=false no .env de desenvolvimento e no CI): a tela de
// ativação obrigatória é coberta pelos testes de PHP e de Vitest.
test.describe.configure({ mode: 'serial' });

let tokenDaPessoa = '';
let tokenDoAdministrador = '';
let segredo = '';
let codigosDeRecuperacao = [];

// Uma vez só: preparar cada conta faz logins, e o login tem limite de 5 tentativas por minuto por e-mail.
test.beforeAll(async () => {
    tokenDaPessoa = await prepararContaParaDoisFatores();
    tokenDoAdministrador = await prepararAdministradorDaPrefeitura();
});

test('ativa o 2FA pela tela: QR Code, chave, código do app e códigos de recuperação', async ({ page }) => {
    await entrarComToken(page, tokenDaPessoa);
    await page.goto('/app/seguranca');

    await expect(page.getByRole('heading', { name: 'Segurança da conta', level: 1 })).toBeVisible();
    await expect(page.getByText('Desativada')).toBeVisible();

    // 1. Senha (ativar exige a senha de novo)
    await page.getByLabel('Sua senha').fill('senha-errada-mesmo');
    await page.getByRole('button', { name: 'Continuar' }).click();
    await expect(page.getByRole('alert')).toContainText('A senha não confere');

    await page.getByLabel('Sua senha').fill(CONTA_DOIS_FATORES.senhaPropria);
    await page.getByRole('button', { name: 'Continuar' }).click();

    // 2. App: QR Code (desenhado no navegador) e a chave para digitar
    await expect(page.getByRole('img', { name: /QR Code/ })).toBeVisible();
    segredo = (await page.getByLabel('Chave de configuração').innerText()).replace(/\s+/g, '');
    expect(segredo).toMatch(/^[A-Z2-7]{32}$/);

    await page.getByLabel(/Digite o código de 6 dígitos/).fill('000000');
    await page.getByRole('button', { name: 'Ativar verificação em duas etapas' }).click();
    await expect(page.getByRole('alert')).toContainText('Código inválido');

    await page.getByLabel(/Digite o código de 6 dígitos/).fill(codigoTotp(segredo));
    await page.getByRole('button', { name: 'Ativar verificação em duas etapas' }).click();

    // 3. Códigos de recuperação: aparecem uma vez
    await expect(page.getByText('Verificação em duas etapas ativada')).toBeVisible();
    const lista = page.getByRole('list', { name: 'Códigos de recuperação' });
    codigosDeRecuperacao = (await lista.getByRole('listitem').allInnerTexts()).map((c) => c.trim());
    expect(codigosDeRecuperacao).toHaveLength(8);
    expect(codigosDeRecuperacao[0]).toMatch(/^[2-9A-HJ-NP-Z]{5}-[2-9A-HJ-NP-Z]{5}$/);

    await page.getByRole('button', { name: /Guardei os códigos, concluir/ }).click();
    await expect(page.getByText('Ativa', { exact: true })).toBeVisible();
    await expect(page.getByText('Códigos de recuperação que ainda valem: 8')).toBeVisible();
    // Esta conta pode desativar (o perfil não obriga), então o botão existe.
    await expect(page.getByRole('button', { name: 'Desativar' })).toBeVisible();
});

test('o login passa a pedir o código: o errado é recusado e o do app entra', async ({ browser }) => {
    const contexto = await browser.newContext();
    const pagina = await contexto.newPage();

    await pagina.goto('/app/login');
    await pagina.getByLabel('E-mail').fill(CONTA_DOIS_FATORES.email);
    await pagina.getByLabel('Senha').fill(CONTA_DOIS_FATORES.senhaPropria);
    await pagina.getByRole('button', { name: 'Entrar' }).click();

    await expect(pagina.getByRole('heading', { name: 'Verificação em duas etapas' })).toBeVisible();
    // A senha não fica na tela do segundo passo e nada de sistema abriu ainda.
    await expect(pagina.getByLabel('Senha')).toHaveCount(0);
    expect(await pagina.evaluate(() => localStorage.getItem('cts_token'))).toBeNull();

    await pagina.getByLabel('Código do app').fill('000000');
    await pagina.getByRole('button', { name: 'Entrar' }).click();
    await expect(pagina.getByRole('alert')).toContainText('Código inválido');

    // O código do período que já foi usado na ativação não vale de novo: entra o do período seguinte.
    await pagina.getByLabel('Código do app').fill(codigoTotp(segredo, 1));
    await pagina.getByRole('button', { name: 'Entrar' }).click();

    await expect(pagina).toHaveURL(/\/app\/$/);
    await expect(pagina.getByRole('complementary', { name: 'Menu principal' })).toBeVisible();
    await contexto.close();
});

test('quem perdeu o celular entra com um código de recuperação', async ({ browser }) => {
    const contexto = await browser.newContext();
    const pagina = await contexto.newPage();

    await pagina.goto('/app/login');
    await pagina.getByLabel('E-mail').fill(CONTA_DOIS_FATORES.email);
    await pagina.getByLabel('Senha').fill(CONTA_DOIS_FATORES.senhaPropria);
    await pagina.getByRole('button', { name: 'Entrar' }).click();

    await pagina.getByRole('button', { name: /Perdi o celular/ }).click();
    await pagina.getByLabel('Código de recuperação').fill(codigosDeRecuperacao[0]);
    await pagina.getByRole('button', { name: 'Entrar' }).click();

    await expect(pagina).toHaveURL(/\/app\/$/);

    // Um código gasto a menos.
    await pagina.goto('/app/seguranca');
    await expect(pagina.getByText('Códigos de recuperação que ainda valem: 7')).toBeVisible();
    await contexto.close();
});

test('o administrador da prefeitura redefine o 2FA e a pessoa volta a entrar só com a senha', async ({ page, request }) => {
    await entrarComToken(page, tokenDoAdministrador);
    await page.goto('/app/admin/usuarios');
    await page.getByPlaceholder('Buscar por nome ou e-mail').fill(CONTA_DOIS_FATORES.email);

    const linha = page.getByRole('row', { name: new RegExp(CONTA_DOIS_FATORES.email) });
    await expect(linha).toBeVisible();
    await expect(linha).toContainText('2FA');

    // Na própria linha do administrador não há o que redefinir por aqui.
    await page.getByPlaceholder('Buscar por nome ou e-mail').fill(CONTA_ADMIN_PREFEITURA.email);
    await expect(page.getByRole('row', { name: new RegExp(CONTA_ADMIN_PREFEITURA.email) }).getByRole('button', { name: 'Redefinir 2FA' })).toHaveCount(0);
    await page.getByPlaceholder('Buscar por nome ou e-mail').fill(CONTA_DOIS_FATORES.email);

    await linha.getByRole('button', { name: 'Redefinir 2FA' }).click();
    const janela = page.getByRole('dialog', { name: /Redefinir 2FA de E2E Dois Fatores/ });

    // Pede a senha de quem redefine.
    await janela.getByLabel('Sua senha').fill('senha-errada-mesmo');
    await janela.getByRole('button', { name: 'Redefinir 2FA' }).click();
    await expect(janela.getByText('A senha não confere')).toBeVisible();

    await janela.getByLabel('Sua senha').fill(CONTA_ADMIN_PREFEITURA.senhaPropria);
    await janela.getByRole('button', { name: 'Redefinir 2FA' }).click();

    await expect(page.getByRole('status')).toContainText('redefinida');
    await expect(janela).toHaveCount(0);
    await expect(linha).not.toContainText('2FA');

    // As sessões da pessoa caíram e o login volta a ser só e-mail e senha.
    const login = await request.post('/api/login', {
        data: { email: CONTA_DOIS_FATORES.email, password: CONTA_DOIS_FATORES.senhaPropria, device_name: 'e2e' },
        headers: { Accept: 'application/json' },
    });
    expect(login.status()).toBe(200);
    expect((await login.json()).dois_fatores).toBeUndefined();
    expect((await login.json()).token).toBeTruthy();
});
