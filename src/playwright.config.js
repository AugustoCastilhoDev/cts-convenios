import { defineConfig } from '@playwright/test';

/*
 * Testes de ponta a ponta: um navegador de verdade usando o sistema de verdade (HTTP + banco).
 *
 *   Local:  docker compose up -d && npm run test:e2e         (usa http://localhost:8000 e o Chrome instalado)
 *   Outro:  E2E_BASE_URL=http://servidor npm run test:e2e
 *
 * Precisam das contas do DatabaseSeeder (admin/gestor/fiscal, senha "password"), então NUNCA rode contra
 * produção. Os testes criam dados com o prefixo "E2E" e apagam tudo ao terminar (e no começo, se uma
 * rodada anterior foi interrompida).
 */
export default defineConfig({
    testDir: './e2e',
    // Um por vez: todos compartilham o mesmo banco.
    workers: 1,
    fullyParallel: false,
    retries: process.env.CI ? 1 : 0,
    timeout: 30_000,
    expect: { timeout: 10_000 },
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    globalSetup: './e2e/global-setup.js',
    globalTeardown: './e2e/global-teardown.js',
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8000',
        // O Chrome já instalado (no GitHub Actions também): evita baixar navegador.
        channel: process.env.E2E_CHANNEL ?? 'chrome',
        locale: 'pt-BR',
        timezoneId: 'America/Sao_Paulo',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
});
