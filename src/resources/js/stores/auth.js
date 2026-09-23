import { defineStore } from 'pinia';
import { api, tokenStorage } from '../services/api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: tokenStorage.get(),
    }),

    getters: {
        isAuthenticated: (state) => state.token !== null,
        // Quem pode alterar convênios (espelha ConvenioPolicy::create/update).
        podeEditar: (state) => ['gestor_convenios', 'administrador_interno'].includes(state.user?.role),
    },

    actions: {
        async login(email, password) {
            const data = await api.post('/login', {
                email,
                password,
                device_name: 'spa-web',
            });

            this.token = data.token;
            this.user = data.user;
            tokenStorage.set(data.token);
        },

        /** Recarrega o usuário do token guardado (após F5). */
        async fetchUser() {
            this.user = await api.get('/me');
        },

        async logout() {
            try {
                await api.post('/logout');
            } catch {
                // token já inválido no servidor: segue para a limpeza local
            }
            this.clear();
        },

        clear() {
            this.user = null;
            this.token = null;
            tokenStorage.clear();
        },
    },
});
