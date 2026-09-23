<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();

async function sair() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="min-h-screen">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex h-14 max-w-[1600px] items-center justify-between px-4">
                <div class="flex items-center gap-8">
                    <span class="font-semibold tracking-tight">CTS Convênios</span>
                    <nav class="flex gap-4 text-sm">
                        <RouterLink :to="{ name: 'dashboard' }" class="text-slate-600 hover:text-slate-900" exact-active-class="font-medium text-slate-900">
                            Painel
                        </RouterLink>
                        <RouterLink :to="{ name: 'kanban' }" class="text-slate-600 hover:text-slate-900" active-class="font-medium text-slate-900">
                            Convênios
                        </RouterLink>
                    </nav>
                </div>
                <div class="flex items-center gap-4 text-sm">
                    <span class="hidden text-right leading-tight sm:block">
                        <span class="block font-medium">{{ auth.user?.name }}</span>
                        <span class="block text-xs text-slate-500">
                            {{ auth.user?.tenant?.razao_social }}
                        </span>
                    </span>
                    <button class="rounded-md border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50" @click="sair">
                        Sair
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-[1600px] px-4 py-6">
            <RouterView />
        </main>
    </div>
</template>
