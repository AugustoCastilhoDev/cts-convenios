import { desativarContaDeTroca, limparDadosDeTeste } from './suporte.js';

export default async function globalTeardown() {
    await limparDadosDeTeste();
    await desativarContaDeTroca();
}
