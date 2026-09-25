import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { CONTA_ADMIN_PREFEITURA, entrarComToken, prepararAdministradorDaPrefeitura } from './suporte.js';

// O administrador da prefeitura gerencia as contas da própria prefeitura. Um teste depende do anterior.
test.describe.configure({ mode: 'serial' });

const SENHA_TEMPORARIA = /^[A-Za-z2-9]{4}-[A-Za-z2-9]{4}-[A-Za-z2-9]{4}$/;
const emailNovo = `e2e.novo.${Date.now() % 1_000_000}@exemplo.gov.br`;
let primeiraSenha = '';

let token = '';

// Uma vez só: preparar a conta faz um login, e o login tem limite de 5 tentativas por minuto.
test.beforeAll(async () => {
    token = await prepararAdministradorDaPrefeitura();
});

test.beforeEach(async ({ page }) => {
    await entrarComToken(page, token);
});

test('vê só a administração da própria prefeitura e trabalha nos convênios', async ({ page }) => {
    await page.goto('/app/');
    const menu = page.getByRole('complementary', { name: 'Menu principal' });

    await expect(menu.getByRole('link', { name: 'Usuários' })).toBeVisible();
    await expect(menu.getByRole('link', { name: 'Auditoria' })).toBeVisible();
    await expect(menu.getByRole('link', { name: 'Prefeituras' })).toHaveCount(0);
    await expect(menu.getByRole('link', { name: 'Pedidos de contato' })).toHaveCount(0);

    // Telas do super administrador: o endereço digitado leva de volta ao painel.
    await page.goto('/app/admin/prefeituras');
    await expect(page).toHaveURL(/\/app\/$/);
    await page.goto('/app/admin/contatos');
    await expect(page).toHaveURL(/\/app\/$/);

    // Trabalha como gestor: pode lançar convênios.
    await page.goto('/app/convenios');
    await expect(page.getByRole('button', { name: 'Novo convênio' })).toBeVisible();
});

test('cria um usuário: a senha temporária aparece uma vez e só fecha pelo botão', async ({ page }) => {
    await page.goto('/app/admin/usuarios');

    // Sem filtro nem coluna de prefeitura: só existe a dele.
    await expect(page.getByLabel('Filtrar por prefeitura')).toHaveCount(0);

    await page.getByRole('button', { name: 'Novo usuário' }).click();
    await expect(page.getByLabel('Prefeitura', { exact: true })).toHaveCount(0);
    await expect(page.locator('input[type="password"]')).toHaveCount(0);

    await page.getByLabel('Nome', { exact: true }).fill('E2E Servidor Novo');
    await page.getByLabel('E-mail').fill(emailNovo);
    await page.getByLabel('Papel', { exact: true }).selectOption({ label: 'Gestor de Convênios' });
    await page.getByRole('button', { name: 'Criar usuário' }).click();

    const aviso = page.getByRole('dialog', { name: 'Usuário criado' });
    await expect(aviso).toBeVisible();
    primeiraSenha = (await aviso.getByLabel('Senha temporária').innerText()).trim();
    expect(primeiraSenha).toMatch(SENHA_TEMPORARIA);
    await expect(aviso).toContainText('só agora');

    // Esc e clique fora não fecham: fechar sem querer perderia a senha.
    await page.keyboard.press('Escape');
    await expect(aviso).toBeVisible();

    await aviso.getByRole('button', { name: /Anotei a senha/ }).click();
    await expect(aviso).toHaveCount(0);

    const linha = page.getByRole('row', { name: new RegExp(emailNovo) });
    await expect(linha).toBeVisible();
    await expect(linha).toContainText('senha temporária');
});

test('o novo usuário entra com a senha temporária e é obrigado a criar a própria', async ({ browser }) => {
    const contexto = await browser.newContext();
    const pagina = await contexto.newPage();

    await pagina.goto('/app/login');
    await pagina.getByLabel('E-mail').fill(emailNovo);
    await pagina.getByLabel('Senha').fill(primeiraSenha);
    await pagina.getByRole('button', { name: 'Entrar' }).click();

    await expect(pagina).toHaveURL(/\/app\/trocar-senha$/);
    await contexto.close();
});

test('redefinir a senha gera outra temporária e derruba a antiga', async ({ page, request }) => {
    await page.goto('/app/admin/usuarios');
    await page.getByPlaceholder('Buscar por nome ou e-mail').fill(emailNovo);

    const linha = page.getByRole('row', { name: new RegExp(emailNovo) });
    await expect(linha).toBeVisible();

    page.once('dialog', (dialogo) => dialogo.accept());
    await linha.getByRole('button', { name: 'Redefinir senha' }).click();

    const aviso = page.getByRole('dialog', { name: 'Senha redefinida' });
    await expect(aviso).toBeVisible();
    const segundaSenha = (await aviso.getByLabel('Senha temporária').innerText()).trim();
    expect(segundaSenha).toMatch(SENHA_TEMPORARIA);
    expect(segundaSenha).not.toBe(primeiraSenha);
    await aviso.getByRole('button', { name: /Anotei a senha/ }).click();

    // A senha antiga deixou de valer; a nova entra.
    const antiga = await request.post('/api/login', { data: { email: emailNovo, password: primeiraSenha, device_name: 'e2e' }, headers: { Accept: 'application/json' } });
    expect(antiga.status()).toBe(422);
    const nova = await request.post('/api/login', { data: { email: emailNovo, password: segundaSenha, device_name: 'e2e' }, headers: { Accept: 'application/json' } });
    expect(nova.status()).toBe(200);
});

test('desativa a conta, e na própria conta o papel e a situação ficam travados', async ({ page }) => {
    await page.goto('/app/admin/usuarios');
    await page.getByPlaceholder('Buscar por nome ou e-mail').fill(emailNovo);

    const linha = page.getByRole('row', { name: new RegExp(emailNovo) });
    await linha.getByRole('button', { name: 'Editar' }).click();
    await page.getByLabel(/Conta ativa/).uncheck();
    await page.getByRole('button', { name: 'Salvar' }).click();
    await expect(linha).toContainText('Inativo');

    // A própria linha: sem "Redefinir senha", e no formulário nada de mudar o próprio papel.
    await page.getByPlaceholder('Buscar por nome ou e-mail').fill(CONTA_ADMIN_PREFEITURA.email);
    const propria = page.getByRole('row', { name: new RegExp(CONTA_ADMIN_PREFEITURA.email) });
    await expect(propria).toContainText('você');
    await expect(propria.getByRole('button', { name: 'Redefinir senha' })).toHaveCount(0);
    await propria.getByRole('button', { name: 'Editar' }).click();
    await expect(page.getByLabel('Papel', { exact: true })).toBeDisabled();
    await expect(page.getByLabel(/Conta ativa/)).toBeDisabled();
});

test('consulta e exporta a auditoria da prefeitura', async ({ page }) => {
    await page.goto('/app/admin/auditoria');
    await expect(page.getByRole('heading', { name: 'Auditoria', level: 1 })).toBeVisible();

    await page.getByRole('button', { name: /Exportar/ }).click();
    const [download] = await Promise.all([
        page.waitForEvent('download'),
        page.getByRole('menuitem', { name: 'Planilha (CSV)' }).click(),
    ]);

    expect(download.suggestedFilename()).toMatch(/^auditoria-\d{4}-\d{2}-\d{2}\.csv$/);
    const csv = readFileSync(await download.path(), 'utf-8');
    expect(csv).toContain('Prefeitura');
    // As criações e redefinições feitas acima estão na trilha, com o nome legível do papel e sem senhas.
    expect(csv).toContain(emailNovo);
    expect(csv).not.toContain(primeiraSenha);
});
