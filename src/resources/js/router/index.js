import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/LoginView.vue'),
        meta: { guest: true },
    },
    // "Esqueci minha senha" e o link do e-mail não são só para visitantes: quem já está logado também pode abrir.
    {
        path: '/esqueci-senha',
        name: 'esqueci-senha',
        component: () => import('../views/EsqueciSenhaView.vue'),
    },
    {
        path: '/redefinir-senha',
        name: 'redefinir-senha',
        component: () => import('../views/RedefinirSenhaView.vue'),
    },
    // Senha temporária: a única tela liberada até a pessoa criar a própria senha.
    {
        path: '/trocar-senha',
        name: 'trocar-senha',
        component: () => import('../views/TrocarSenhaView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/',
        component: () => import('../layouts/AppLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                name: 'dashboard',
                component: () => import('../views/DashboardView.vue'),
            },
            {
                path: 'convenios',
                name: 'kanban',
                component: () => import('../views/KanbanView.vue'),
            },
            {
                path: 'admin/prefeituras',
                name: 'admin-prefeituras',
                component: () => import('../views/admin/PrefeiturasView.vue'),
                meta: { requiresAdmin: true },
            },
            {
                path: 'admin/usuarios',
                name: 'admin-usuarios',
                component: () => import('../views/admin/UsuariosView.vue'),
                meta: { requiresAdmin: true },
            },
            {
                path: 'admin/contatos',
                name: 'admin-contatos',
                component: () => import('../views/admin/ContatosView.vue'),
                meta: { requiresAdmin: true },
            },
            {
                path: 'admin/auditoria',
                name: 'admin-auditoria',
                component: () => import('../views/admin/AuditoriaView.vue'),
                meta: { requiresAdmin: true },
            },
            {
                path: 'convenios/:id',
                name: 'convenio',
                component: () => import('../views/ConvenioDetalheView.vue'),
            },
        ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
    // O sistema mora em /app (a landing page pública fica em /).
    history: createWebHistory('/app/'),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login' };
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }

    // Token guardado, mas usuário ainda não carregado (recarregou a página).
    if (auth.isAuthenticated && !auth.user) {
        try {
            await auth.fetchUser();
        } catch {
            auth.clear();
            return { name: 'login' };
        }
    }

    // Senha temporária: nada além da troca de senha (o servidor também barra com 403 "senha_temporaria").
    if (auth.isAuthenticated && auth.user) {
        if (auth.precisaTrocarSenha && to.name !== 'trocar-senha') {
            return { name: 'trocar-senha' };
        }

        if (!auth.precisaTrocarSenha && to.name === 'trocar-senha') {
            return { name: 'dashboard' };
        }
    }

    // Telas de administração: o servidor também barra (403), isto só evita mostrar uma tela vazia.
    if (to.meta.requiresAdmin && !auth.isAdmin) {
        return { name: 'dashboard' };
    }
});

export default router;
