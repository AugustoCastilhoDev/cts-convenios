<script setup>
import { computed, reactive, ref } from 'vue';
import { api, ApiError } from '../services/api';
import { useAuthStore } from '../stores/auth';
import Campo from './Campo.vue';
import ModalBase from './ModalBase.vue';

// usuario = null cria; com um usuário, edita (prefeitura não muda: mover alguém é criar outra conta).
// Senha nunca é digitada aqui: ao criar, o sistema gera uma temporária e a devolve UMA vez.
const props = defineProps({
    usuario: { type: Object, default: null },
    prefeituras: { type: Array, default: () => [] },
    prefeituraInicial: { type: String, default: '' },
});
const emit = defineEmits(['salvo', 'fechar']);

const auth = useAuthStore();

const papeis = [
    { valor: 'gestor_convenios', titulo: 'Gestor de Convênios' },
    { valor: 'fiscal_controle_interno', titulo: 'Fiscal de Controle Interno' },
    { valor: 'administrador_prefeitura', titulo: 'Administrador da Prefeitura' },
];

// O super administrador escolhe a prefeitura; o administrador da prefeitura só cria na dele (o servidor força isso).
const escolhePrefeitura = computed(() => auth.isAdmin);
// Ninguém desativa a própria conta nem muda o próprio papel (o servidor também recusa).
const propria = computed(() => props.usuario?.id === auth.user?.id);

const form = reactive({
    name: props.usuario?.name ?? '',
    email: props.usuario?.email ?? '',
    role: props.usuario?.role ?? 'gestor_convenios',
    tenant_id: props.usuario?.tenant_id ?? props.prefeituraInicial,
    active: props.usuario?.active ?? true,
});
const erros = ref({});
const erroGeral = ref('');
const salvando = ref(false);

async function salvar() {
    salvando.value = true;
    erros.value = {};
    erroGeral.value = '';

    try {
        if (props.usuario) {
            const dados = { name: form.name, email: form.email };
            if (!propria.value) {
                Object.assign(dados, { role: form.role, active: form.active });
            }
            const resposta = await api.put(`/users/${props.usuario.id}`, dados);
            emit('salvo', { usuario: resposta.data, senha: null });
        } else {
            const dados = { name: form.name, email: form.email, role: form.role };
            if (escolhePrefeitura.value) {
                dados.tenant_id = form.tenant_id;
            }
            const resposta = await api.post('/users', dados);
            emit('salvo', { usuario: resposta.data, senha: resposta.senha_temporaria, expiraEm: resposta.senha_temporaria_expira_em });
        }
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) {
            erros.value = e.errors;
            // Recusas do servidor sem campo próprio (ex.: "não pode desativar a si mesmo") aparecem no alto.
            erroGeral.value = erros.value.active?.[0] ?? '';
        } else {
            erroGeral.value = e.message;
        }
    } finally {
        salvando.value = false;
    }
}

const campo = 'mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none disabled:bg-slate-100 disabled:text-slate-500';
</script>

<template>
    <ModalBase :titulo="usuario ? 'Editar usuário' : 'Novo usuário'" @fechar="emit('fechar')">
        <form class="mt-4 space-y-4" @submit.prevent="salvar">
            <div v-if="erroGeral" role="alert" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ erroGeral }}</div>

            <template v-if="!usuario && escolhePrefeitura">
                <Campo rotulo="Prefeitura" para="usuario-prefeitura" :erro="erros.tenant_id?.[0]">
                    <select id="usuario-prefeitura" v-model="form.tenant_id" required :class="campo">
                        <option value="" disabled>Selecione a prefeitura</option>
                        <option v-for="p in prefeituras.filter((x) => x.active)" :key="p.id" :value="p.id">{{ p.razao_social }}</option>
                    </select>
                </Campo>
            </template>
            <p v-else-if="usuario" class="text-sm text-slate-500">Prefeitura: <span class="font-medium text-slate-900">{{ usuario.tenant?.razao_social }}</span></p>

            <Campo rotulo="Nome" para="usuario-nome" :erro="erros.name?.[0]">
                <input id="usuario-nome" v-model="form.name" required :class="campo">
            </Campo>
            <Campo rotulo="E-mail" para="usuario-email" :erro="erros.email?.[0]">
                <input id="usuario-email" v-model="form.email" type="email" required autocomplete="off" :class="campo">
            </Campo>
            <Campo rotulo="Papel" para="usuario-papel" :erro="erros.role?.[0]" :ajuda="propria ? 'Você não pode mudar o próprio papel. Peça a outro administrador.' : ''">
                <select id="usuario-papel" v-model="form.role" :disabled="propria" :class="campo">
                    <option v-for="p in papeis" :key="p.valor" :value="p.valor">{{ p.titulo }}</option>
                </select>
            </Campo>

            <p v-if="!usuario" class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                O sistema vai gerar uma <strong>senha temporária</strong> e mostrá-la uma única vez ao salvar.
                No primeiro acesso a pessoa cria a própria senha.
            </p>

            <label v-if="usuario" class="flex items-center gap-2 text-sm">
                <input v-model="form.active" type="checkbox" :disabled="propria" class="size-4 rounded border-slate-300">
                Conta ativa <span class="text-xs text-slate-500">(desmarcar desconecta o usuário na hora)</span>
            </label>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50" @click="emit('fechar')">Cancelar</button>
                <button type="submit" :disabled="salvando" class="rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800 disabled:opacity-60">
                    {{ salvando ? 'Salvando…' : (usuario ? 'Salvar' : 'Criar usuário') }}
                </button>
            </div>
        </form>
    </ModalBase>
</template>
