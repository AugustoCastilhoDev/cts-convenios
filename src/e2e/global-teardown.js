import { limparDadosDeTeste } from './suporte.js';

export default async function globalTeardown() {
    await limparDadosDeTeste();
}
