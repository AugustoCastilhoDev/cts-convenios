<script setup>
import { reactive, ref } from 'vue';
import { api, ApiError } from '../services/api';
import Campo from './Campo.vue';
import ModalBase from './ModalBase.vue';

// usuario = null cria; com um usuário, edita (prefeitura não muda: mover alguém é criar outra conta).
const props = defineProps({
    usuario: { type: Object, default: null },
    prefeituras: { type: Array, required: true },
    prefeituraInicial: { type: String, default: '' },
});
const emit = defineEmits(['salvo', 'fechar']);

const papeis = [
    { valor: 'gestor_convenios', titulo: 'Gestor de Convênios' },
    { valor: 'fiscal_controle_interno', titulo: 'Fiscal de Controle Interno' },
];

const form = reactive({
    name: props.usuario?.name ?? '',
    email: props.usuario?.email ?? '',
    role: props.usuario?.role ?? 'gestor_convenios',
    tenant_id: props.usuario?.tenant_id ?? props.prefeituraInicial,
    password: '',
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
        let resposta;

        if (props.usuario) {
            const dados = { name: form.name, email: form.email, role: form.role, active: form.active };
            // Em branco = manter a senha atual.
            if (form.password) {
                dados.password = form.password;
            }
            resposta = await api.put(`/users/${props.usuario.id}`, dados);
        } else {
            resposta = await api.post('/users', {
                name: form.name,
                email: form.email,
                role: form.role,
                tenant_id: form.tenant_id,
                password: form.password,
            });
        }

        emit('salvo', resposta.data);
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) {
            erros.value = e.errors;
        } else {
            erroGeral.value = e.message;
        }
    } finally {
        salvando.value = false;
    }
}

const campo = 'mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none';
</script>

<template>
    <ModalBase :titulo="usuario ? 'Editar usuário' : 'Novo usuário'" @fechar="emit('fechar')">
        <form class="mt-4 space-y-4" @submit.prevent="salvar">
            <div v-if="erroGeral" role="alert" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ erroGeral }}</div>

            <Campo v-if="!usuario" rotulo="Prefeitura" para="usuario-prefeitura" :erro="erros.tenant_id?.[0]">
                <select id="usuario-prefeitura" v-model="form.tenant_id" required :class="campo">
                    <option value="" disabled>Selecione a prefeitura</option>
                    <option v-for="p in prefeituras.filter((x) => x.active)" :key="p.id" :value="p.id">{{ p.razao_social }}</option>
                </select>
            </Campo>
            <p v-else class="text-sm text-slate-500">Prefeitura: <span class="font-medium text-slate-900">{{ usuario.tenant?.razao_social }}</span></p>

            <Campo rotulo="Nome" para="usuario-nome" :erro="erros.name?.[0]">
                <input id="usuario-nome" v-model="form.name" required :class="campo">
            </Campo>
            <Campo rotulo="E-mail" para="usuario-email" :erro="erros.email?.[0]">
                <input id="usuario-email" v-model="form.email" type="email" required autocomplete="off" :class="campo">
            </Campo>
            <Campo rotulo="Papel" para="usuario-papel" :erro="erros.role?.[0]">
                <select id="usuario-papel" v-model="form.role" :class="campo">
                    <option v-for="p in papeis" :key="p.valor" :value="p.valor">{{ p.titulo }}</option>
                </select>
            </Campo>
            <Campo
                :rotulo="usuario ? 'Nova senha (opcional)' : 'Senha inicial'"
                para="usuario-senha"
                :ajuda="usuario ? 'Deixe em branco para manter a senha atual. Trocar a senha desconecta o usuário.' : 'Mínimo de 8 caracteres, com letras e números. Repasse ao usuário por um canal seguro.'"
                :erro="erros.password?.[0]"
            >
                <input id="usuario-senha" v-model="form.password" type="password" :required="!usuario" autocomplete="new-password" :class="campo">
            </Campo>

            <label v-if="usuario" class="flex items-center gap-2 text-sm">
                <input v-model="form.active" type="checkbox" class="size-4 rounded border-slate-300">
                Conta ativa <span class="text-xs text-slate-500">(desmarcar desconecta o usuário na hora)</span>
            </label>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50" @click="emit('fechar')">Cancelar</button>
                <button type="submit" :disabled="salvando" class="rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800 disabled:opacity-60">
                    {{ salvando ? 'Salvando…' : 'Salvar' }}
                </button>
            </div>
        </form>
    </ModalBase>
</template>
