import { desativarContasDeTeste, limparDadosDeTeste } from './suporte.js';

export default async function globalTeardown() {
    await limparDadosDeTeste();
    await desativarContasDeTeste();
}
