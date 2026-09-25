<script setup>
import { ref } from 'vue';
import { ApiError } from '../services/api';
import Campo from './Campo.vue';
import ModalBase from './ModalBase.vue';

// Ações sensíveis pedem a senha de novo (e, ao desativar o 2FA, um código): um token roubado, sozinho, não basta.
// A ação é passada pela tela: recebe { password, codigo } e devolve o resultado, que sobe em "concluido".
const props = defineProps({
    titulo: { type: String, required: true },
    texto: { type: String, default: '' },
    rotuloBotao: { type: String, default: 'Confirmar' },
    pedeCodigo: { type: Boolean, default: false },
    acao: { type: Function, required: true },
});
const emit = defineEmits(['concluido', 'fechar']);

const password = ref('');
const codigo = ref('');
const erros = ref({});
const erroGeral = ref('');
const enviando = ref(false);

async function confirmar() {
    enviando.value = true;
    erros.value = {};
    erroGeral.value = '';

    try {
        emit('concluido', await props.acao({ password: password.value, codigo: codigo.value.trim() }));
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) {
            erros.value = e.errors;
            erroGeral.value = e.errors.dois_fatores?.[0] ?? '';
        } else {
            erroGeral.value = e instanceof ApiError ? e.message : 'Não foi possível conectar ao servidor.';
        }
    } finally {
        enviando.value = false;
    }
}

const campo = 'mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none';
</script>

<template>
    <ModalBase :titulo="titulo" @fechar="emit('fechar')">
        <form class="mt-4 space-y-4" @submit.prevent="confirmar">
            <p v-if="texto" class="text-sm text-slate-600">{{ texto }}</p>
            <div v-if="erroGeral" role="alert" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ erroGeral }}</div>

            <Campo rotulo="Sua senha" para="confirmar-senha" :erro="erros.password?.[0]">
                <input id="confirmar-senha" v-model="password" type="password" required autocomplete="current-password" :class="campo">
            </Campo>

            <Campo
                v-if="pedeCodigo"
                rotulo="Código do app ou código de recuperação"
                para="confirmar-codigo"
                :erro="erros.codigo?.[0]"
            >
                <input id="confirmar-codigo" v-model="codigo" required autocomplete="one-time-code" :class="campo">
            </Campo>

            <div class="flex justify-end gap-3">
                <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50" @click="emit('fechar')">Cancelar</button>
                <button type="submit" :disabled="enviando" class="rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800 disabled:opacity-60">
                    {{ enviando ? 'Confirmando…' : rotuloBotao }}
                </button>
            </div>
        </form>
    </ModalBase>
</template>
