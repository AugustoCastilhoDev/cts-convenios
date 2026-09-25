import { limparDadosDeTeste } from './suporte.js';

// Se uma rodada anterior foi interrompida, sobrou dado de teste: começa limpo.
export default async function globalSetup() {
    await limparDadosDeTeste();
}
