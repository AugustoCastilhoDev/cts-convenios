<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { ApiError } from '../services/api';
import TelaDeAcesso from '../layouts/TelaDeAcesso.vue';

const auth = useAuthStore();
const router = useRouter();

const form = reactive({ email: '', password: '' });
const carregando = ref(false);
const erro = ref('');

async function entrar() {
    carregando.value = true;
    erro.value = '';

    try {
        await auth.login(form.email, form.password);
        // Quem entrou com senha temporária é levado à troca pelo guarda de rotas.
        router.push({ name: 'dashboard' });
    } catch (e) {
        erro.value = e instanceof ApiError
            ? (Object.values(e.errors)[0]?.[0] ?? e.message)
            : 'Não foi possível conectar ao servidor.';
    } finally {
        carregando.value = false;
    }
}

const campo = 'mt-1 w-full rounded-md border border-borda bg-white px-3 py-2.5 shadow-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none';
</script>

<template>
    <TelaDeAcesso>
        <form class="cartao w-full p-8" @submit.prevent="entrar">
            <h2 class="text-2xl font-semibold tracking-tight text-petroleo">Entrar</h2>
            <p class="mt-1 text-sm text-slate-500">Use o e-mail cadastrado pela sua prefeitura.</p>

            <div v-if="erro" role="alert" class="mt-6 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ erro }}
            </div>

            <label class="mt-6 block text-sm font-medium" for="email">E-mail</label>
            <input id="email" v-model="form.email" type="email" required autocomplete="username" :class="campo">

            <label class="mt-4 block text-sm font-medium" for="password">Senha</label>
            <input id="password" v-model="form.password" type="password" required autocomplete="current-password" :class="campo">

            <button
                type="submit"
                :disabled="carregando"
                class="mt-6 w-full rounded-md bg-brand-700 px-4 py-2.5 font-medium text-white shadow-sm hover:bg-brand-800 disabled:opacity-60"
            >
                {{ carregando ? 'Entrando…' : 'Entrar' }}
            </button>

            <p class="mt-4 text-center text-sm">
                <RouterLink :to="{ name: 'esqueci-senha' }" class="text-brand-700 underline hover:no-underline">Esqueci minha senha</RouterLink>
            </p>
        </form>
    </TelaDeAcesso>
</template>
