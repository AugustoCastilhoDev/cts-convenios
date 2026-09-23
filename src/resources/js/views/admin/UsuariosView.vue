<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../../services/api';
import Paginacao from '../../components/Paginacao.vue';
import UsuarioFormModal from '../../components/UsuarioFormModal.vue';

const route = useRoute();

const prefeituras = ref([]);
const usuarios = ref([]);
const meta = ref(null);
const carregando = ref(true);
const erro = ref('');

// Vindo de "Prefeituras → Usuários", já abre filtrado pela prefeitura.
const filtroPrefeitura = ref(route.query.prefeitura ?? '');
const filtroPapel = ref('');
const busca = ref('');
const pagina = ref(1);

const formulario = ref(false);
const emEdicao = ref(null);

async function carregar() {
    carregando.value = true;

    try {
        const resposta = await api.get('/users', {
            tenant_id: filtroPrefeitura.value,
            role: filtroPapel.value,
            busca: busca.value.trim(),
            page: pagina.value,
        });
        usuarios.value = resposta.data;
        meta.value = resposta.meta;
    } catch (e) {
        erro.value = e.message;
    } finally {
        carregando.value = false;
    }
}

function abrir(usuario = null) {
    emEdicao.value = usuario;
    formulario.value = true;
}

function salvo() {
    formulario.value = false;
    carregar();
}

// Mudou um filtro: volta para a primeira página. A busca espera o usuário parar de digitar.
let temporizador;
watch([filtroPrefeitura, filtroPapel], () => {
    pagina.value = 1;
    carregar();
});
watch(busca, () => {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => {
        pagina.value = 1;
        carregar();
    }, 300);
});

function irParaPagina(numero) {
    pagina.value = numero;
    carregar();
}

onMounted(async () => {
    try {
        prefeituras.value = (await api.get('/tenants', { todas: 1 })).data;
    } catch (e) {
        erro.value = e.message;
    }
    carregar();
});

const filtro = 'rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none';
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-petroleo">Usuários</h1>
                <p class="text-sm text-slate-500">Gestores e fiscais das prefeituras. Contas de administrador só se criam pelo servidor.</p>
            </div>
            <button class="rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800" @click="abrir()">Novo usuário</button>
        </div>

        <div class="mt-4 flex flex-wrap gap-3">
            <select v-model="filtroPrefeitura" aria-label="Filtrar por prefeitura" :class="filtro">
                <option value="">Todas as prefeituras</option>
                <option v-for="p in prefeituras" :key="p.id" :value="p.id">{{ p.razao_social }}</option>
            </select>
            <select v-model="filtroPapel" aria-label="Filtrar por papel" :class="filtro">
                <option value="">Todos os papéis</option>
                <option value="gestor_convenios">Gestor de Convênios</option>
                <option value="fiscal_controle_interno">Fiscal de Controle Interno</option>
            </select>
            <input v-model="busca" type="search" placeholder="Buscar por nome ou e-mail" :class="[filtro, 'w-full sm:w-72']">
        </div>

        <div v-if="erro" role="alert" class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ erro }}</div>

        <div class="mt-4 overflow-x-auto cartao" :class="{ 'opacity-60': carregando }">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Nome</th>
                        <th class="px-4 py-2 font-medium">E-mail</th>
                        <th class="px-4 py-2 font-medium">Prefeitura</th>
                        <th class="px-4 py-2 font-medium">Papel</th>
                        <th class="px-4 py-2 font-medium">Situação</th>
                        <th class="px-4 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in usuarios" :key="u.id" class="border-t border-slate-100">
                        <td class="px-4 py-2 font-medium">{{ u.name }}</td>
                        <td class="px-4 py-2">{{ u.email }}</td>
                        <td class="px-4 py-2">{{ u.tenant?.razao_social }}</td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ u.role_label }}</td>
                        <td class="px-4 py-2">
                            <span class="rounded px-1.5 py-0.5 text-xs font-medium" :class="u.active ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-600'">
                                {{ u.active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button class="text-brand-700 hover:underline" @click="abrir(u)">Editar</button>
                        </td>
                    </tr>
                    <tr v-if="!usuarios.length && !carregando">
                        <td colspan="6" class="px-4 py-6 text-center text-slate-400">Nenhum usuário encontrado</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Paginacao :meta="meta" @pagina="irParaPagina" />

        <UsuarioFormModal
            v-if="formulario"
            :usuario="emEdicao"
            :prefeituras="prefeituras"
            :prefeitura-inicial="filtroPrefeitura"
            @salvo="salvo"
            @fechar="formulario = false"
        />
    </div>
</template>
