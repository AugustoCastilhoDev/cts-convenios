import { defineStore } from 'pinia';
import { api, tokenStorage } from '../services/api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: tokenStorage.get(),
    }),

    getters: {
        isAuthenticated: (state) => state.token !== null,
        // Entrou com a senha que um administrador definiu: precisa criar a própria antes de usar o sistema.
        precisaTrocarSenha: (state) => state.user?.must_change_password === true,
        // Administrador que ainda não ativou a verificação em duas etapas: só usa a tela de ativação até ativar.
        precisaAtivarDoisFatores: (state) => state.user?.two_factor_obrigatorio === true && state.user?.two_factor_ativo !== true,
        // O sino de alertas é de quem trabalha os prazos de uma prefeitura (espelha AlertaPrazoPolicy).
        temSino: (state) => ['administrador_prefeitura', 'gestor_convenios', 'fiscal_controle_interno'].includes(state.user?.role),
        // Super administrador (equipe da plataforma) e administrador da prefeitura (a pessoa de confiança do município).
        isAdmin: (state) => state.user?.role === 'administrador_interno',
        isAdminPrefeitura: (state) => state.user?.role === 'administrador_prefeitura',
        // Cria/desativa usuários e consulta a auditoria: o super administrador em todas as prefeituras, o da prefeitura só na dele.
        podeGerenciarUsuarios: (state) => ['administrador_interno', 'administrador_prefeitura'].includes(state.user?.role),
        // Só o Administrador Interno apaga registros (ArquivoConvenioPolicy::delete e ConvenioPolicy::delete).
        podeExcluir: (state) => state.user?.role === 'administrador_interno',
        // Quem pode alterar convênios (espelha ConvenioPolicy::create/update).
        podeEditar: (state) => ['gestor_convenios', 'administrador_prefeitura', 'administrador_interno'].includes(state.user?.role),
    },

    actions: {
        /**
         * Primeiro passo do login. Sem 2FA já entra e devolve null. Com 2FA o servidor ainda não emitiu o token:
         * devolve o "desafio" que a tela entrega, junto com o código do app, a verificarDoisFatores().
         */
        async login(email, password) {
            const data = await api.post('/login', {
                email,
                password,
                device_name: 'spa-web',
            });

            if (data.dois_fatores) {
                return { desafio: data.desafio };
            }

            this.iniciarSessao(data);

            return null;
        },

        /** Segundo passo: o desafio do primeiro + o código de 6 dígitos do app (ou um código de recuperação). */
        async verificarDoisFatores(desafio, codigo) {
            this.iniciarSessao(await api.post('/login/2fa', { desafio, codigo }));
        },

        iniciarSessao(data) {
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
