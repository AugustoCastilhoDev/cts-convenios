import { expect, test } from '@playwright/test';
import { contas, entrarComo } from './suporte.js';

test.describe('entrada no sistema', () => {
    test('quem não está logado é levado à tela de login', async ({ page }) => {
        await page.goto('/app/convenios');

        await expect(page).toHaveURL(/\/app\/login$/);
        await expect(page.getByRole('heading', { name: 'Entrar' })).toBeVisible();
    });

    test('senha errada mostra o aviso e não entra', async ({ page }) => {
        await page.goto('/app/login');
        // E-mail que não existe: não gasta a cota de tentativas das contas reais.
        await page.getByLabel('E-mail').fill('ninguem@exemplo.gov.br');
        await page.getByLabel('Senha').fill('senha-errada');
        await page.getByRole('button', { name: 'Entrar' }).click();

        await expect(page.getByRole('alert')).toContainText('credenciais');
        await expect(page).toHaveURL(/\/app\/login$/);
    });

    test('o gestor entra pelo formulário, vê o Painel e sai', async ({ page }) => {
        await page.goto('/app/login');
        await page.getByLabel('E-mail').fill(contas.gestor.email);
        await page.getByLabel('Senha').fill(contas.gestor.senha);
        await page.getByRole('button', { name: 'Entrar' }).click();

        await expect(page.getByRole('heading', { name: 'Painel', level: 1 })).toBeVisible();
        await expect(page.getByText(/Município Regular|Risco de Inadimplência/)).toBeVisible();

        await page.getByRole('button', { name: 'Sair' }).click();

        await expect(page).toHaveURL(/\/app\/login$/);
    });
});

test.describe('o que cada papel enxerga', () => {
    test('o gestor não vê a Administração e é barrado se digitar o endereço', async ({ page }) => {
        await entrarComo(page, 'gestor');
        await page.goto('/app/');

        await expect(page.getByRole('heading', { name: 'Painel', level: 1 })).toBeVisible();
        // Só o menu lateral: "Administração" também é uma opção do filtro de secretarias.
        const menu = page.getByRole('complementary', { name: 'Menu principal' });
        await expect(menu.getByText('Administração', { exact: true })).toHaveCount(0);
        await expect(menu.getByRole('link', { name: 'Pedidos de contato' })).toHaveCount(0);

        await page.goto('/app/admin/usuarios');

        await expect(page).toHaveURL(/\/app\/$/);
        await expect(page.getByRole('heading', { name: 'Painel', level: 1 })).toBeVisible();
    });

    test('o fiscal só consulta: não há botão de novo convênio', async ({ page }) => {
        await entrarComo(page, 'fiscal');
        await page.goto('/app/convenios');

        await expect(page.getByRole('heading', { name: 'Convênios', level: 1 })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Novo convênio' })).toHaveCount(0);
        await expect(page.getByLabel('Mover para etapa')).toHaveCount(0);
    });

    test('o administrador vê a Administração da plataforma', async ({ page }) => {
        await entrarComo(page, 'admin');
        await page.goto('/app/');

        for (const item of ['Prefeituras', 'Usuários', 'Pedidos de contato', 'Auditoria']) {
            await expect(page.getByRole('link', { name: item })).toBeVisible();
        }

        await page.getByRole('link', { name: 'Auditoria' }).click();
        await expect(page.getByRole('heading', { name: 'Auditoria', level: 1 })).toBeVisible();
    });
});
