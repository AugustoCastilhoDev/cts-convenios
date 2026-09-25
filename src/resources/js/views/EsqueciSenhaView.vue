<script setup>
import { ref } from 'vue';
import { api, ApiError } from '../services/api';
import TelaDeAcesso from '../layouts/TelaDeAcesso.vue';

const email = ref('');
const enviando = ref(false);
const enviado = ref(false);
const mensagem = ref('');
const erro = ref('');

async function pedir() {
    enviando.value = true;
    erro.value = '';

    try {
        const resposta = await api.post('/esqueci-senha', { email: email.value.trim() });
        // O servidor responde igual exista ou não o e-mail: a tela também não revela nada.
        mensagem.value = resposta.message;
        enviado.value = true;
    } catch (e) {
        erro.value = e instanceof ApiError
            ? (Object.values(e.errors)[0]?.[0] ?? e.message)
            : 'Não foi possível conectar ao servidor.';
    } finally {
        enviando.value = false;
    }
}

const campo = 'mt-1 w-full rounded-md border border-borda bg-white px-3 py-2.5 shadow-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none';
</script>

<template>
    <TelaDeAcesso>
        <div class="cartao w-full p-8">
            <h2 class="text-2xl font-semibold tracking-tight text-petroleo">Esqueci minha senha</h2>

            <div v-if="enviado" role="status">
                <p class="mt-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{{ mensagem }}</p>
                <p class="mt-4 text-sm text-slate-500">Não chegou? Veja a caixa de spam ou peça de novo em alguns minutos.</p>
            </div>

            <form v-else @submit.prevent="pedir">
                <p class="mt-1 text-sm text-slate-500">Informe o e-mail da sua conta e enviaremos um link para criar uma nova senha.</p>

                <div v-if="erro" role="alert" class="mt-6 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ erro }}
                </div>

                <label class="mt-6 block text-sm font-medium" for="email">E-mail</label>
                <input id="email" v-model="email" type="email" required autocomplete="username" :class="campo">

                <button
                    type="submit"
                    :disabled="enviando"
                    class="mt-6 w-full rounded-md bg-brand-700 px-4 py-2.5 font-medium text-white shadow-sm hover:bg-brand-800 disabled:opacity-60"
                >
                    {{ enviando ? 'Enviando…' : 'Enviar link' }}
                </button>
            </form>

            <p class="mt-4 text-center text-sm">
                <RouterLink :to="{ name: 'login' }" class="text-brand-700 underline hover:no-underline">Voltar para entrar</RouterLink>
            </p>
        </div>
    </TelaDeAcesso>
</template>
