import { expect, test } from '@playwright/test';
import { CONTA_TROCA, prepararContaComSenhaTemporaria } from './suporte.js';

test.describe('esqueci minha senha', () => {
    test('o login leva ao pedido e a resposta não revela se o e-mail existe', async ({ page }) => {
        await page.goto('/app/login');
        await page.getByRole('link', { name: 'Esqueci minha senha' }).click();

        await expect(page).toHaveURL(/\/app\/esqueci-senha$/);
        await expect(page.getByRole('heading', { name: 'Esqueci minha senha' })).toBeVisible();

        // E-mail que não existe: a resposta é a mesma de uma conta real.
        await page.getByLabel('E-mail').fill(`ninguem.${Date.now()}@exemplo.gov.br`);
        await page.getByRole('button', { name: 'Enviar link' }).click();

        await expect(page.getByRole('status')).toContainText('Se este e-mail estiver cadastrado');
        await page.getByRole('link', { name: 'Voltar para entrar' }).click();
        await expect(page).toHaveURL(/\/app\/login$/);
    });

    test('um link sem token ou vencido leva a pedir outro', async ({ page }) => {
        await page.goto('/app/redefinir-senha');

        await expect(page.getByRole('alert')).toContainText('inválido ou expirou');
        await page.getByRole('link', { name: 'Pedir um novo link' }).click();
        await expect(page).toHaveURL(/\/app\/esqueci-senha$/);

        // Com token e e-mail, mas falso: o servidor recusa e a tela avisa, sem revelar o motivo.
        await page.goto('/app/redefinir-senha?token=token-falso&email=ninguem%40exemplo.gov.br');
        await page.getByLabel('Nova senha', { exact: true }).fill('NovaSenhaForte123');
        await page.getByLabel('Repita a nova senha').fill('NovaSenhaForte123');
        await page.getByRole('button', { name: 'Salvar nova senha' }).click();

        await expect(page.getByRole('alert')).toContainText('inválido ou expirou');
        // O token saiu do endereço assim que a tela abriu.
        await expect(page).toHaveURL(/\/app\/redefinir-senha$/);
    });
});

test.describe('senha temporária', () => {
    test.beforeEach(async () => {
        await prepararContaComSenhaTemporaria();
    });

    test('quem entra com a senha do administrador só chega ao sistema depois de criar a própria', async ({ page }) => {
        await page.goto('/app/login');
        await page.getByLabel('E-mail').fill(CONTA_TROCA.email);
        await page.getByLabel('Senha').fill(CONTA_TROCA.temporaria);
        await page.getByRole('button', { name: 'Entrar' }).click();

        // Levado direto à troca, sem ver o painel.
        await expect(page).toHaveURL(/\/app\/trocar-senha$/);
        await expect(page.getByRole('heading', { name: 'Defina sua senha' })).toBeVisible();

        // Tentar ir a outra tela volta para a troca.
        await page.goto('/app/convenios');
        await expect(page).toHaveURL(/\/app\/trocar-senha$/);

        // Senha temporária errada: recusa e fica na tela.
        await page.getByLabel(/Senha temporária/).fill('errada-errada');
        await page.getByLabel('Nova senha', { exact: true }).fill('MinhaSenhaPropria99');
        await page.getByLabel('Repita a nova senha').fill('MinhaSenhaPropria99');
        await page.getByRole('button', { name: 'Salvar e continuar' }).click();
        await expect(page.getByText('A senha atual não confere.')).toBeVisible();

        // Senha nova fraca: o servidor explica o motivo.
        await page.getByLabel(/Senha temporária/).fill(CONTA_TROCA.temporaria);
        await page.getByLabel('Nova senha', { exact: true }).fill('curta1');
        await page.getByLabel('Repita a nova senha').fill('curta1');
        await page.getByRole('button', { name: 'Salvar e continuar' }).click();
        await expect(page).toHaveURL(/\/app\/trocar-senha$/);

        // Senha boa: segue para o painel, agora liberado.
        await page.getByLabel('Nova senha', { exact: true }).fill('MinhaSenhaPropria99');
        await page.getByLabel('Repita a nova senha').fill('MinhaSenhaPropria99');
        await page.getByRole('button', { name: 'Salvar e continuar' }).click();

        await expect(page.getByRole('heading', { name: 'Painel', level: 1 })).toBeVisible();
        await page.goto('/app/convenios');
        await expect(page.getByRole('heading', { name: 'Convênios', level: 1 })).toBeVisible();
    });
});
