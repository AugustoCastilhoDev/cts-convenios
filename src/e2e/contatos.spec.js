import { expect, test } from '@playwright/test';
import { entrarComo, PREFIXO } from './suporte.js';

test.describe.configure({ mode: 'serial' });

const municipio = `${PREFIXO} Município Teste/SP`;

test('a página inicial leva às páginas de Privacidade e Termos', async ({ page }) => {
    await page.goto('/');

    await page.getByRole('contentinfo').getByRole('link', { name: 'Privacidade' }).click();
    await expect(page.getByRole('heading', { name: 'Política de Privacidade', level: 1 })).toBeVisible();
    await expect(page.getByText('68.552.491/0001-17').first()).toBeVisible();

    await page.getByRole('contentinfo').getByRole('link', { name: 'Termos de uso' }).click();
    await expect(page.getByRole('heading', { name: 'Termos de Uso', level: 1 })).toBeVisible();
});

test('o formulário exige o aceite e o pedido enviado chega ao painel do admin', async ({ page }) => {
    await page.goto('/#contato');

    await page.getByLabel('Nome', { exact: true }).fill(`${PREFIXO} Visitante`);
    await page.getByLabel(/Município e UF/).fill(municipio);
    await page.getByLabel('E-mail', { exact: true }).fill('visitante.e2e@exemplo.gov.br');

    // Sem o aceite o servidor recusa e a tela aponta o campo.
    await page.getByRole('button', { name: 'Solicitar demonstração' }).last().click();
    await expect(page.locator('[data-erro="aceite"]')).toBeVisible();

    await page.getByRole('checkbox').check();
    await page.getByRole('button', { name: 'Solicitar demonstração' }).last().click();

    await expect(page.locator('#contato-status')).toContainText('Recebemos seu contato');
});

test('o admin encontra o pedido, marca como respondido e exclui', async ({ page }) => {
    await entrarComo(page, 'admin');
    await page.goto('/app/admin/contatos');

    await page.getByLabel('Buscar').fill(PREFIXO);
    await page.getByRole('button', { name: 'Filtrar' }).click();

    const cartao = page.locator('li', { hasText: `${PREFIXO} Visitante` });
    await expect(cartao).toBeVisible();
    await expect(cartao).toContainText(municipio);
    await expect(cartao).toContainText('Pendente');

    await cartao.getByRole('button', { name: 'Marcar como respondido' }).click();
    await expect(cartao).toContainText('Respondido em');

    // Filtro por situação: o respondido sai de "Pendentes".
    await page.getByLabel('Situação').selectOption('pendente');
    await page.getByRole('button', { name: 'Filtrar' }).click();
    await expect(page.locator('li', { hasText: `${PREFIXO} Visitante` })).toHaveCount(0);

    await page.getByLabel('Situação').selectOption('respondido');
    await page.getByRole('button', { name: 'Filtrar' }).click();
    await expect(cartao).toBeVisible();

    page.once('dialog', (dialogo) => dialogo.accept());
    await cartao.getByRole('button', { name: 'Excluir' }).click();

    await expect(page.locator('li', { hasText: `${PREFIXO} Visitante` })).toHaveCount(0);
});

test('o gestor não acessa os pedidos de contato pela API', async ({ request }) => {
    const { tokenDe } = await import('./suporte.js');
    const token = await tokenDe('gestor');

    const resposta = await request.get('/api/contatos', { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } });

    expect(resposta.status()).toBe(403);
});
