<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../../services/api';
import Paginacao from '../../components/Paginacao.vue';
import SenhaTemporariaModal from '../../components/SenhaTemporariaModal.vue';
import UsuarioFormModal from '../../components/UsuarioFormModal.vue';
import { useAuthStore } from '../../stores/auth';
import { formatarDataHora } from '../../utils/format';

const route = useRoute();
const auth = useAuthStore();

const prefeituras = ref([]);
const usuarios = ref([]);
const meta = ref(null);
const carregando = ref(true);
const erro = ref('');

// Vindo de "Prefeituras → Usuários", já abre filtrado pela prefeitura (só o super administrador filtra por prefeitura).
const filtroPrefeitura = ref(route.query.prefeitura ?? '');
const filtroPapel = ref('');
const busca = ref('');
const pagina = ref(1);

const formulario = ref(false);
const emEdicao = ref(null);
// Senha temporária a mostrar (criação ou redefinição): { titulo, usuario, senha }.
const aviso = ref(null);
const redefinindo = ref(null);

async function carregar() {
    carregando.value = true;

    try {
        const resposta = await api.get('/users', {
            tenant_id: auth.isAdmin ? filtroPrefeitura.value : '',
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

function salvo({ usuario, senha, expiraEm }) {
    formulario.value = false;

    // Conta nova: a senha temporária aparece uma única vez, num aviso que só fecha pelo botão.
    if (senha) {
        aviso.value = { titulo: 'Usuário criado', usuario, senha, expiraEm };
    }

    carregar();
}

async function redefinirSenha(usuario) {
    const texto = `Redefinir a senha de ${usuario.name}? Uma nova senha temporária será gerada, a pessoa será desconectada e precisará criar a própria senha no próximo acesso.`;

    if (!window.confirm(texto)) {
        return;
    }

    redefinindo.value = usuario.id;
    erro.value = '';

    try {
        const resposta = await api.post(`/users/${usuario.id}/redefinir-senha`);
        aviso.value = { titulo: 'Senha redefinida', usuario: resposta.data, senha: resposta.senha_temporaria, expiraEm: resposta.senha_temporaria_expira_em };
    } catch (e) {
        erro.value = e.message;
    } finally {
        redefinindo.value = null;
    }
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
    // A lista de prefeituras é do super administrador; o administrador da prefeitura só enxerga a própria.
    if (auth.isAdmin) {
        try {
            prefeituras.value = (await api.get('/tenants', { todas: 1 })).data;
        } catch (e) {
            erro.value = e.message;
        }
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
                <p class="text-sm text-slate-500">
                    <template v-if="auth.isAdmin">Contas de todas as prefeituras. Contas de super administrador só se criam pelo servidor.</template>
                    <template v-else>Quem tem acesso ao sistema na sua prefeitura. Você cria, edita, desativa e redefine a senha das contas.</template>
                </p>
            </div>
            <button class="rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800" @click="abrir()">Novo usuário</button>
        </div>

        <div class="mt-4 flex flex-wrap gap-3">
            <select v-if="auth.isAdmin" v-model="filtroPrefeitura" aria-label="Filtrar por prefeitura" :class="filtro">
                <option value="">Todas as prefeituras</option>
                <option v-for="p in prefeituras" :key="p.id" :value="p.id">{{ p.razao_social }}</option>
            </select>
            <select v-model="filtroPapel" aria-label="Filtrar por papel" :class="filtro">
                <option value="">Todos os papéis</option>
                <option value="administrador_prefeitura">Administrador da Prefeitura</option>
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
                        <th v-if="auth.isAdmin" class="px-4 py-2 font-medium">Prefeitura</th>
                        <th class="px-4 py-2 font-medium">Papel</th>
                        <th class="px-4 py-2 font-medium">Situação</th>
                        <th class="px-4 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in usuarios" :key="u.id" class="border-t border-slate-100">
                        <td class="px-4 py-2 font-medium">
                            {{ u.name }}
                            <span v-if="u.id === auth.user?.id" class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-normal text-slate-600">você</span>
                        </td>
                        <td class="px-4 py-2">{{ u.email }}</td>
                        <td v-if="auth.isAdmin" class="px-4 py-2">{{ u.tenant?.razao_social }}</td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ u.role_label }}</td>
                        <td class="px-4 py-2">
                            <span class="rounded px-1.5 py-0.5 text-xs font-medium" :class="u.active ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-600'">
                                {{ u.active ? 'Ativo' : 'Inativo' }}
                            </span>
                            <span
                                v-if="u.must_change_password && u.active"
                                class="ml-1 rounded px-1.5 py-0.5 text-xs font-medium whitespace-nowrap"
                                :class="u.senha_temporaria_expirada ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-900'"
                                :title="u.senha_temporaria_expirada
                                    ? 'A senha temporária venceu: use Redefinir senha para gerar outra'
                                    : `Ainda não criou a própria senha (a temporária vale até ${formatarDataHora(u.senha_temporaria_expira_em)})`"
                            >
                                {{ u.senha_temporaria_expirada ? 'senha temporária vencida' : 'senha temporária' }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <button class="text-brand-700 hover:underline" @click="abrir(u)">Editar</button>
                            <button
                                v-if="u.id !== auth.user?.id"
                                class="ml-4 text-brand-700 hover:underline disabled:opacity-50"
                                :disabled="redefinindo === u.id"
                                @click="redefinirSenha(u)"
                            >
                                {{ redefinindo === u.id ? 'Redefinindo…' : 'Redefinir senha' }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!usuarios.length && !carregando">
                        <td :colspan="auth.isAdmin ? 6 : 5" class="px-4 py-6 text-center text-slate-400">Nenhum usuário encontrado</td>
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

        <SenhaTemporariaModal
            v-if="aviso"
            :titulo="aviso.titulo"
            :nome="aviso.usuario.name"
            :email="aviso.usuario.email"
            :senha="aviso.senha"
            :expira-em="aviso.expiraEm"
            @fechar="aviso = null"
        />
    </div>
</template>
