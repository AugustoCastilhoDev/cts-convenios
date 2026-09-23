<script setup>
import { reactive, ref } from 'vue';
import { api, ApiError } from '../services/api';
import Campo from './Campo.vue';
import ModalBase from './ModalBase.vue';

// prefeitura = null cadastra uma nova; com uma prefeitura, edita.
const props = defineProps({ prefeitura: { type: Object, default: null } });
const emit = defineEmits(['salvo', 'fechar']);

const form = reactive({
    razao_social: props.prefeitura?.razao_social ?? '',
    cnpj: props.prefeitura?.cnpj ?? '',
});
const erros = ref({});
const erroGeral = ref('');
const salvando = ref(false);

async function salvar() {
    salvando.value = true;
    erros.value = {};
    erroGeral.value = '';

    try {
        const resposta = props.prefeitura
            ? await api.put(`/tenants/${props.prefeitura.id}`, form)
            : await api.post('/tenants', form);
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

const campo = 'mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 focus:outline-none';
</script>

<template>
    <ModalBase :titulo="prefeitura ? 'Editar prefeitura' : 'Nova prefeitura'" @fechar="emit('fechar')">
        <form class="mt-4 space-y-4" @submit.prevent="salvar">
            <div v-if="erroGeral" role="alert" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ erroGeral }}</div>

            <Campo rotulo="Razão social" para="razao-social" :erro="erros.razao_social?.[0]">
                <input id="razao-social" v-model="form.razao_social" required :class="campo">
            </Campo>
            <Campo rotulo="CNPJ" para="cnpj" ajuda="Com ou sem pontuação." :erro="erros.cnpj?.[0]">
                <input id="cnpj" v-model="form.cnpj" required inputmode="numeric" placeholder="00.000.000/0000-00" :class="campo">
            </Campo>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50" @click="emit('fechar')">Cancelar</button>
                <button type="submit" :disabled="salvando" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:opacity-60">
                    {{ salvando ? 'Salvando…' : 'Salvar' }}
                </button>
            </div>
        </form>
    </ModalBase>
</template>
