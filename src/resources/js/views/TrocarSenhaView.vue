<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, ApiError } from '../services/api';
import { useAuthStore } from '../stores/auth';
import TelaDeAcesso from '../layouts/TelaDeAcesso.vue';

const auth = useAuthStore();
const router = useRouter();

const form = reactive({ current_password: '', password: '', confirmacao: '' });
const erros = ref({});
const salvando = ref(false);

async function salvar() {
    erros.value = {};

    if (form.password !== form.confirmacao) {
        erros.value = { confirmacao: ['A confirmação não confere com a nova senha.'] };
        return;
    }

    salvando.value = true;

    try {
        await api.put('/me/password', { current_password: form.current_password, password: form.password });
        // O servidor tirou a marca de senha temporária: recarrega o usuário e libera o sistema.
        await auth.fetchUser();
        router.replace({ name: 'dashboard' });
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) {
            erros.value = e.errors;
        } else {
            erros.value = { geral: [e instanceof ApiError ? e.message : 'Não foi possível conectar ao servidor.'] };
        }
    } finally {
        salvando.value = false;
    }
}

async function sair() {
    await auth.logout();
    router.replace({ name: 'login' });
}

const campo = 'mt-1 w-full rounded-md border border-borda bg-white px-3 py-2.5 shadow-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none';
</script>

<template>
    <TelaDeAcesso>
        <form class="cartao w-full p-8" @submit.prevent="salvar">
            <h2 class="text-2xl font-semibold tracking-tight text-petroleo">Defina sua senha</h2>
            <p class="mt-1 text-sm text-slate-500">
                Você entrou com uma senha temporária. Por segurança, crie a sua própria antes de continuar.
            </p>

            <div v-if="erros.geral" role="alert" class="mt-6 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ erros.geral[0] }}
            </div>

            <label class="mt-6 block text-sm font-medium" for="senha-atual">Senha temporária (a que você recebeu)</label>
            <input id="senha-atual" v-model="form.current_password" type="password" required autocomplete="current-password" :class="campo">
            <p v-if="erros.current_password" class="mt-1 text-xs text-red-600">{{ erros.current_password[0] }}</p>

            <label class="mt-4 block text-sm font-medium" for="senha-nova">Nova senha</label>
            <input id="senha-nova" v-model="form.password" type="password" required autocomplete="new-password" :class="campo">
            <p v-if="erros.password" class="mt-1 text-xs text-red-600">{{ erros.password[0] }}</p>
            <p v-else class="mt-1 text-xs text-slate-500">Mínimo de 10 caracteres, com letras e números.</p>

            <label class="mt-4 block text-sm font-medium" for="senha-confirmacao">Repita a nova senha</label>
            <input id="senha-confirmacao" v-model="form.confirmacao" type="password" required autocomplete="new-password" :class="campo">
            <p v-if="erros.confirmacao" class="mt-1 text-xs text-red-600">{{ erros.confirmacao[0] }}</p>

            <button
                type="submit"
                :disabled="salvando"
                class="mt-6 w-full rounded-md bg-brand-700 px-4 py-2.5 font-medium text-white shadow-sm hover:bg-brand-800 disabled:opacity-60"
            >
                {{ salvando ? 'Salvando…' : 'Salvar e continuar' }}
            </button>

            <p class="mt-4 text-center text-sm">
                <button type="button" class="text-brand-700 underline hover:no-underline" @click="sair">Sair</button>
            </p>
        </form>
    </TelaDeAcesso>
</template>
