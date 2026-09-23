<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import AlterarSenhaModal from '../components/AlterarSenhaModal.vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();

const alterandoSenha = ref(false);

async function sair() {
    await auth.logout();
    router.push({ name: 'login' });
}

const link = 'text-slate-600 hover:text-slate-900';
</script>

<template>
    <div class="min-h-screen">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex min-h-14 max-w-[1600px] flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-2">
                <div class="flex flex-wrap items-center gap-x-8 gap-y-1">
                    <span class="font-semibold tracking-tight">CTS Convênios</span>
                    <nav class="flex flex-wrap gap-x-4 gap-y-1 text-sm" aria-label="Principal">
                        <RouterLink :to="{ name: 'dashboard' }" :class="link" exact-active-class="font-medium text-slate-900">Painel</RouterLink>
                        <RouterLink :to="{ name: 'kanban' }" :class="link" active-class="font-medium text-slate-900">Convênios</RouterLink>

                        <template v-if="auth.isAdmin">
                            <span class="hidden h-5 w-px self-center bg-slate-200 sm:block" aria-hidden="true" />
                            <RouterLink :to="{ name: 'admin-prefeituras' }" :class="link" active-class="font-medium text-slate-900">Prefeituras</RouterLink>
                            <RouterLink :to="{ name: 'admin-usuarios' }" :class="link" active-class="font-medium text-slate-900">Usuários</RouterLink>
                            <RouterLink :to="{ name: 'admin-auditoria' }" :class="link" active-class="font-medium text-slate-900">Auditoria</RouterLink>
                        </template>
                    </nav>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <span class="hidden text-right leading-tight sm:block">
                        <span class="block font-medium">{{ auth.user?.name }}</span>
                        <span class="block text-xs text-slate-500">
                            {{ auth.user?.tenant?.razao_social ?? auth.user?.role_label }}
                        </span>
                    </span>
                    <button class="rounded-md border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50" @click="alterandoSenha = true">
                        Alterar senha
                    </button>
                    <button class="rounded-md border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50" @click="sair">
                        Sair
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-[1600px] px-4 py-6">
            <RouterView />
        </main>

        <AlterarSenhaModal v-if="alterandoSenha" @fechar="alterandoSenha = false" />
    </div>
</template>
