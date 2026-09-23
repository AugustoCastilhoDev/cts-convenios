<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../../services/api';
import PrefeituraFormModal from '../../components/PrefeituraFormModal.vue';

const prefeituras = ref([]);
const carregando = ref(true);
const erro = ref('');
const formulario = ref(false);
const emEdicao = ref(null);

async function carregar() {
    try {
        prefeituras.value = (await api.get('/tenants', { todas: 1 })).data;
    } catch (e) {
        erro.value = e.message;
    } finally {
        carregando.value = false;
    }
}

function abrir(prefeitura = null) {
    emEdicao.value = prefeitura;
    formulario.value = true;
}

function salvo() {
    formulario.value = false;
    carregar();
}

async function alternarSituacao(prefeitura) {
    const ativando = !prefeitura.active;
    const aviso = ativando
        ? `Reativar "${prefeitura.razao_social}"? Os usuários voltam a acessar o sistema.`
        : `Desativar "${prefeitura.razao_social}"? Todos os usuários dela perdem o acesso imediatamente. Os dados são preservados.`;

    if (!window.confirm(aviso)) {
        return;
    }

    try {
        await api.put(`/tenants/${prefeitura.id}`, { active: ativando });
        await carregar();
    } catch (e) {
        erro.value = e.message;
    }
}

onMounted(carregar);
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Prefeituras</h1>
                <p class="text-sm text-slate-500">Clientes da plataforma. Uma prefeitura desativada perde o acesso, mas mantém todo o histórico.</p>
            </div>
            <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" @click="abrir()">Nova prefeitura</button>
        </div>

        <div v-if="erro" role="alert" class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ erro }}</div>
        <p v-if="carregando" class="mt-6 text-sm text-slate-500">Carregando…</p>

        <div v-else class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Razão social</th>
                        <th class="px-4 py-2 font-medium">CNPJ</th>
                        <th class="px-4 py-2 text-right font-medium">Usuários</th>
                        <th class="px-4 py-2 text-right font-medium">Convênios</th>
                        <th class="px-4 py-2 font-medium">Situação</th>
                        <th class="px-4 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in prefeituras" :key="p.id" class="border-t border-slate-100">
                        <td class="px-4 py-2 font-medium">{{ p.razao_social }}</td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ p.cnpj }}</td>
                        <td class="px-4 py-2 text-right">{{ p.usuarios_count }}</td>
                        <td class="px-4 py-2 text-right">{{ p.convenios_count }}</td>
                        <td class="px-4 py-2">
                            <span class="rounded px-1.5 py-0.5 text-xs font-medium" :class="p.active ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-600'">
                                {{ p.active ? 'Ativa' : 'Desativada' }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <RouterLink :to="{ name: 'admin-usuarios', query: { prefeitura: p.id } }" class="mr-3 text-blue-700 hover:underline">Usuários</RouterLink>
                            <button class="mr-3 text-blue-700 hover:underline" @click="abrir(p)">Editar</button>
                            <button :class="p.active ? 'text-red-700' : 'text-green-700'" class="hover:underline" @click="alternarSituacao(p)">
                                {{ p.active ? 'Desativar' : 'Reativar' }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!prefeituras.length">
                        <td colspan="6" class="px-4 py-6 text-center text-slate-400">Nenhuma prefeitura cadastrada</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PrefeituraFormModal v-if="formulario" :prefeitura="emEdicao" @salvo="salvo" @fechar="formulario = false" />
    </div>
</template>
