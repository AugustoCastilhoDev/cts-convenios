<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AlterarSenhaModal from '../components/AlterarSenhaModal.vue';
import Icone from '../components/Icone.vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const CHAVE_RECOLHIDA = 'cts_menu_recolhido';

function lerPreferencia() {
    try {
        return localStorage.getItem(CHAVE_RECOLHIDA) === '1';
    } catch {
        return false;
    }
}

// Menu recolhido (só ícones) vale para telas grandes e é lembrado entre visitas.
const recolhido = ref(lerPreferencia());
const gavetaAberta = ref(false);
const alterandoSenha = ref(false);

function alternarRecolhido() {
    recolhido.value = !recolhido.value;
    try {
        localStorage.setItem(CHAVE_RECOLHIDA, recolhido.value ? '1' : '0');
    } catch {
        // sem armazenamento: a preferência só dura até recarregar
    }
}

// No celular o menu é uma gaveta: fecha ao navegar.
watch(() => route.fullPath, () => {
    gavetaAberta.value = false;
});

const principal = [
    { rota: 'dashboard', rotulo: 'Painel', icone: 'painel', prefixo: '/', exato: true },
    { rota: 'kanban', rotulo: 'Convênios', icone: 'convenios', prefixo: '/convenios' },
];

const administracao = [
    { rota: 'admin-prefeituras', rotulo: 'Prefeituras', icone: 'prefeituras', prefixo: '/admin/prefeituras' },
    { rota: 'admin-usuarios', rotulo: 'Usuários', icone: 'usuarios', prefixo: '/admin/usuarios' },
    { rota: 'admin-auditoria', rotulo: 'Auditoria', icone: 'auditoria', prefixo: '/admin/auditoria' },
];

function ativo(item) {
    return item.exato ? route.path === item.prefixo : route.path.startsWith(item.prefixo);
}

const iniciais = computed(() => {
    const partes = (auth.user?.name ?? '').trim().split(/\s+/).filter(Boolean);

    return ((partes[0]?.[0] ?? '') + (partes.length > 1 ? partes.at(-1)[0] : '')).toUpperCase();
});

async function sair() {
    await auth.logout();
    router.push({ name: 'login' });
}

// Item de menu: o traço dourado à esquerda marca a página atual.
const itemBase = 'relative flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors';
const itemAtivo = 'bg-white/10 text-white before:absolute before:top-2 before:bottom-2 before:-left-3 before:w-[3px] before:rounded-r before:bg-ouro';
const itemInativo = 'text-slate-300 hover:bg-white/5 hover:text-white';
</script>

<template>
    <div class="min-h-screen bg-canvas">
        <!-- Barra superior: só no celular/tablet, onde o menu vira gaveta -->
        <header class="sticky top-0 z-20 flex h-14 items-center gap-3 bg-petroleo px-4 text-white lg:hidden">
            <button class="-ml-1 rounded-md p-1.5 hover:bg-white/10" aria-label="Abrir menu" @click="gavetaAberta = true">
                <Icone nome="menu" />
            </button>
            <span class="font-semibold tracking-tight">CTS Convênios</span>
        </header>

        <div v-if="gavetaAberta" class="fixed inset-0 z-30 bg-slate-900/60 lg:hidden" @click="gavetaAberta = false" />

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-petroleo text-slate-300 transition-[width,translate] duration-200"
            :class="[gavetaAberta ? 'translate-x-0' : '-translate-x-full lg:translate-x-0', recolhido ? 'lg:w-[4.75rem]' : 'lg:w-64']"
            aria-label="Menu principal"
        >
            <div class="flex h-16 shrink-0 items-center gap-3 px-4">
                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-500 text-sm font-bold text-white ring-1 ring-white/20">CTS</span>
                <span class="min-w-0 leading-tight" :class="{ 'lg:hidden': recolhido }">
                    <span class="block truncate font-semibold text-white">CTS Convênios</span>
                    <span class="block truncate text-xs text-slate-400">Prazos em dia, sem CADIN</span>
                </span>
                <button class="ml-auto rounded-md p-1.5 text-slate-300 hover:bg-white/10 lg:hidden" aria-label="Fechar menu" @click="gavetaAberta = false">
                    <Icone nome="fechar" />
                </button>
            </div>

            <nav class="min-h-0 flex-1 space-y-6 overflow-y-auto px-3 py-4">
                <ul class="space-y-1">
                    <li v-for="item in principal" :key="item.rota">
                        <RouterLink
                            :to="{ name: item.rota }"
                            :class="[itemBase, ativo(item) ? itemAtivo : itemInativo]"
                            :title="recolhido ? item.rotulo : null"
                            :aria-current="ativo(item) ? 'page' : null"
                        >
                            <Icone :nome="item.icone" />
                            <span :class="{ 'lg:hidden': recolhido }">{{ item.rotulo }}</span>
                        </RouterLink>
                    </li>
                </ul>

                <div v-if="auth.isAdmin">
                    <p class="mb-2 px-3 text-xs font-medium text-slate-400" :class="{ 'lg:hidden': recolhido }">Administração</p>
                    <hr v-if="recolhido" class="mb-2 hidden border-white/10 lg:block">
                    <ul class="space-y-1">
                        <li v-for="item in administracao" :key="item.rota">
                            <RouterLink
                                :to="{ name: item.rota }"
                                :class="[itemBase, ativo(item) ? itemAtivo : itemInativo]"
                                :title="recolhido ? item.rotulo : null"
                                :aria-current="ativo(item) ? 'page' : null"
                            >
                                <Icone :nome="item.icone" />
                                <span :class="{ 'lg:hidden': recolhido }">{{ item.rotulo }}</span>
                            </RouterLink>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="shrink-0 space-y-1 border-t border-white/10 p-3">
                <div class="flex items-center gap-3 px-2 py-2">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-petroleo-claro text-xs font-semibold text-white ring-1 ring-white/20" aria-hidden="true">{{ iniciais }}</span>
                    <span class="min-w-0 leading-tight" :class="{ 'lg:hidden': recolhido }">
                        <span class="block truncate text-sm font-medium text-white">{{ auth.user?.name }}</span>
                        <span class="block truncate text-xs text-slate-400">{{ auth.user?.tenant?.razao_social ?? auth.user?.role_label }}</span>
                    </span>
                </div>

                <button :class="[itemBase, itemInativo]" :title="recolhido ? 'Alterar senha' : null" @click="alterandoSenha = true">
                    <Icone nome="chave" />
                    <span :class="{ 'lg:hidden': recolhido }">Alterar senha</span>
                </button>
                <button :class="[itemBase, itemInativo]" :title="recolhido ? 'Sair' : null" @click="sair">
                    <Icone nome="sair" />
                    <span :class="{ 'lg:hidden': recolhido }">Sair</span>
                </button>
                <button
                    :class="[itemBase, itemInativo, 'hidden lg:flex']"
                    :aria-label="recolhido ? 'Expandir menu' : 'Recolher menu'"
                    :title="recolhido ? 'Expandir menu' : null"
                    @click="alternarRecolhido"
                >
                    <Icone nome="recolher" />
                    <span :class="{ 'lg:hidden': recolhido }">Recolher menu</span>
                </button>
            </div>
        </aside>

        <div class="transition-[padding] duration-200" :class="recolhido ? 'lg:pl-[4.75rem]' : 'lg:pl-64'">
            <main class="mx-auto max-w-[1800px] px-4 py-6 lg:px-8 lg:py-8">
                <RouterView />
            </main>
        </div>

        <AlterarSenhaModal v-if="alterandoSenha" @fechar="alterandoSenha = false" />
    </div>
</template>
