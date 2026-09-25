import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

// Config separada da do Vite de propósito: os testes não precisam do plugin do Laravel nem do Tailwind.
export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        include: ['resources/js/tests/**/*.test.js'],
        restoreMocks: true,
    },
});
