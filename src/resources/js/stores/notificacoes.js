import { defineStore } from 'pinia';
import { api } from '../services/api';

const INTERVALO_MS = 60_000;

let temporizador = null;

export const useNotificacoesStore = defineStore('notificacoes', {
    state: () => ({
        itens: [],
        naoLidas: 0,
        carregado: false,
    }),

    actions: {
        async carregar() {
            try {
                const resposta = await api.get('/notificacoes');
                this.itens = resposta.data;
                this.naoLidas = resposta.meta.nao_lidas;
                this.carregado = true;
            } catch {
                // Sem rede ou sessão expirada (o 401 já leva ao login): tenta de novo no próximo ciclo.
            }
        },

        /** Consulta agora e depois a cada minuto; também ao voltar para a aba. */
        iniciar() {
            this.parar();
            this.carregar();
            temporizador = setInterval(() => this.carregar(), INTERVALO_MS);
            document.addEventListener('visibilitychange', this.aoVoltarParaAba);
        },

        parar() {
            clearInterval(temporizador);
            temporizador = null;
            document.removeEventListener('visibilitychange', this.aoVoltarParaAba);
        },

        aoVoltarParaAba() {
            if (document.visibilityState === 'visible') {
                useNotificacoesStore().carregar();
            }
        },

        async marcarLida(item) {
            if (item.lida) {
                return;
            }

            // Atualiza a tela na hora; se o servidor recusar, a próxima consulta corrige.
            item.lida = true;
            this.naoLidas = Math.max(0, this.naoLidas - 1);

            try {
                await api.post(`/notificacoes/${item.id}/lida`);
            } catch {
                this.carregar();
            }
        },

        async marcarTodasLidas() {
            this.itens.forEach((item) => {
                item.lida = true;
            });
            this.naoLidas = 0;

            try {
                await api.post('/notificacoes/lidas');
            } catch {
                this.carregar();
            }
        },

        limpar() {
            this.parar();
            this.itens = [];
            this.naoLidas = 0;
            this.carregado = false;
        },
    },
});
