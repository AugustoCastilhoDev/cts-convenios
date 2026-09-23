<script setup>
import { reactive, ref } from 'vue';
import { api, ApiError } from '../services/api';
import Campo from './Campo.vue';
import ModalBase from './ModalBase.vue';

const emit = defineEmits(['fechar']);

const form = reactive({ current_password: '', password: '', confirmacao: '' });
const erros = ref({});
const erroGeral = ref('');
const salvando = ref(false);
const concluido = ref(false);

async function salvar() {
    erros.value = {};
    erroGeral.value = '';

    if (form.password !== form.confirmacao) {
        erros.value = { confirmacao: ['A confirmação não confere com a nova senha.'] };
        return;
    }

    salvando.value = true;

    try {
        await api.put('/me/password', { current_password: form.current_password, password: form.password });
        concluido.value = true;
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
    <ModalBase titulo="Alterar senha" @fechar="emit('fechar')">
        <div v-if="concluido">
            <p class="mt-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">
                Senha alterada. Os outros dispositivos onde você estava conectado foram desconectados.
            </p>
            <div class="mt-6 flex justify-end">
                <button class="rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800" @click="emit('fechar')">Fechar</button>
            </div>
        </div>

        <form v-else class="mt-4 space-y-4" @submit.prevent="salvar">
            <div v-if="erroGeral" role="alert" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ erroGeral }}</div>

            <Campo rotulo="Senha atual" para="senha-atual" :erro="erros.current_password?.[0]">
                <input id="senha-atual" v-model="form.current_password" type="password" required autocomplete="current-password" :class="campo">
            </Campo>
            <Campo rotulo="Nova senha" para="senha-nova" ajuda="Mínimo de 8 caracteres, com letras e números." :erro="erros.password?.[0]">
                <input id="senha-nova" v-model="form.password" type="password" required autocomplete="new-password" :class="campo">
            </Campo>
            <Campo rotulo="Repita a nova senha" para="senha-confirmacao" :erro="erros.confirmacao?.[0]">
                <input id="senha-confirmacao" v-model="form.confirmacao" type="password" required autocomplete="new-password" :class="campo">
            </Campo>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50" @click="emit('fechar')">Cancelar</button>
                <button type="submit" :disabled="salvando" class="rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800 disabled:opacity-60">
                    {{ salvando ? 'Salvando…' : 'Alterar senha' }}
                </button>
            </div>
        </form>
    </ModalBase>
</template>
