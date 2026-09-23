import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/LoginView.vue'),
        meta: { guest: true },
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
    history: createWebHistory(),
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

    // Telas de administração: o servidor também barra (403), isto só evita mostrar uma tela vazia.
    if (to.meta.requiresAdmin && !auth.isAdmin) {
        return { name: 'dashboard' };
    }
});

export default router;
