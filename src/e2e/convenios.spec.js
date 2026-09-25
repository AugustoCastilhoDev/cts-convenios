import { expect, test } from '@playwright/test';
import { diasAFrente, entrarComo, PREFIXO } from './suporte.js';

// Fluxo principal do gestor: cadastrar, mover no quadro, contratar. Um teste depende do anterior.
test.describe.configure({ mode: 'serial' });

const numero = `${PREFIXO}-${Date.now() % 1_000_000}/2026`;

/** A coluna do quadro Kanban com o título dado. */
const coluna = (page, titulo) => page.locator('section', { has: page.getByRole('heading', { name: titulo, exact: true, level: 2 }) });

test.beforeEach(async ({ page }) => {
    await entrarComo(page, 'gestor');
});

test('o gestor cadastra um convênio e ele aparece na coluna Proposta', async ({ page }) => {
    await page.goto('/app/convenios');
    await page.getByRole('button', { name: 'Novo convênio' }).click();

    await page.getByLabel('Número do convênio').fill(numero);
    await page.getByLabel('Órgão concedente').fill('Ministério de Teste (E2E)');
    await page.getByLabel('Objeto').fill('Convênio criado pelo teste de ponta a ponta.');
    await page.getByLabel('Secretaria responsável').selectOption({ label: 'Saúde' });
    await page.getByLabel('Valor do repasse (R$)').fill('800');
    await page.getByLabel('Contrapartida (R$)').fill('200');
    await page.getByLabel('Fim da vigência').fill(diasAFrente(45));
    await page.getByRole('button', { name: /Salvar|Criar|Cadastrar/ }).click();

    const cartao = coluna(page, 'Proposta').locator('article', { hasText: numero });
    await expect(cartao).toBeVisible();
    await expect(cartao).toContainText('Saúde');
    // Vence em ~45 dias: entra na faixa de alerta dos 60 dias.
    await expect(cartao).toContainText(/\d+ d para vencer/);
});

test('a secretaria é obrigatória num convênio novo', async ({ page }) => {
    await page.goto('/app/convenios');
    await page.getByRole('button', { name: 'Novo convênio' }).click();

    await page.getByLabel('Número do convênio').fill(`${numero}-sem-secretaria`);
    await page.getByLabel('Órgão concedente').fill('Ministério de Teste');
    await page.getByLabel('Objeto').fill('Sem secretaria.');
    await page.getByLabel('Valor do repasse (R$)').fill('100');
    await page.getByLabel('Contrapartida (R$)').fill('0');
    await page.getByRole('button', { name: /Salvar|Criar|Cadastrar/ }).click();

    // O formulário continua aberto: o navegador barra o envio sem a secretaria.
    await expect(page.getByRole('heading', { name: 'Novo convênio' })).toBeVisible();
    await expect(page.getByLabel('Secretaria responsável')).toHaveJSProperty('validity.valueMissing', true);
});

test('mover o convênio de etapa muda a coluna', async ({ page }) => {
    await page.goto('/app/convenios');

    const cartao = coluna(page, 'Proposta').locator('article', { hasText: numero });
    await cartao.getByLabel('Mover para etapa').selectOption({ label: 'Mover para: Em Execução' });

    await expect(coluna(page, 'Em Execução').locator('article', { hasText: numero })).toBeVisible();
    await expect(coluna(page, 'Proposta').locator('article', { hasText: numero })).toHaveCount(0);
});

test('um contrato vinculado altera o valor e o percentual contratado', async ({ page }) => {
    await page.goto('/app/convenios');
    await coluna(page, 'Em Execução').locator('article', { hasText: numero }).getByRole('link', { name: numero }).click();

    await expect(page.getByRole('heading', { name: numero })).toBeVisible();
    await expect(page.getByText('0% do valor total')).toBeVisible();

    await page.getByPlaceholder('Nº do contrato').fill('CT-E2E-1');
    await page.getByPlaceholder('Empresa contratada').fill('Construtora Teste E2E');
    await page.getByPlaceholder('Valor (R$)').fill('250');
    await page.getByRole('button', { name: 'Adicionar contrato' }).click();

    // R$ 250 de R$ 1.000 (repasse + contrapartida) = 25%.
    await expect(page.getByText('25% do valor total')).toBeVisible();
    await expect(page.getByText('Construtora Teste E2E')).toBeVisible();

    await page.getByRole('link', { name: 'Convênios' }).first().click();
    const cartao = coluna(page, 'Em Execução').locator('article', { hasText: numero });
    await expect(cartao).toContainText(/Contratado: R\$\s250,00 \(25%\)/);
});

test('o painel filtra por secretaria e o filtro fica no endereço', async ({ page }) => {
    await page.goto('/app/');
    await expect(page.getByRole('heading', { name: 'Painel', level: 1 })).toBeVisible();

    await page.getByLabel('Filtrar por Secretaria').selectOption({ label: 'Saúde' });

    await expect(page).toHaveURL(/secretaria=saude/);
    await expect(page.getByText('Situação financeira e prazos dos convênios em andamento')).toContainText('Saúde');
    // O convênio de teste está em Saúde, com R$ 250 contratados: o painel mostra o total contratado.
    await expect(page.getByText('Prazos críticos')).toBeVisible();
});

test('o fiscal enxerga o convênio, mas sem controles de edição', async ({ browser }) => {
    const contexto = await browser.newContext();
    const page = await contexto.newPage();
    await entrarComo(page, 'fiscal');

    await page.goto('/app/convenios');

    await expect(coluna(page, 'Em Execução').locator('article', { hasText: numero })).toBeVisible();
    await expect(page.getByLabel('Mover para etapa')).toHaveCount(0);

    await contexto.close();
});
