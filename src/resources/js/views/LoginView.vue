<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { ApiError } from '../services/api';

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
        router.push({ name: 'dashboard' });
    } catch (e) {
        erro.value = e instanceof ApiError
            ? (Object.values(e.errors)[0]?.[0] ?? e.message)
            : 'Não foi possível conectar ao servidor.';
    } finally {
        carregando.value = false;
    }
}
</script>

<template>
    <main class="grid min-h-screen lg:grid-cols-2">
        <section class="hidden flex-col justify-between bg-slate-900 p-12 text-white lg:flex">
            <div class="text-lg font-semibold tracking-tight">CTS Convênios</div>
            <div>
                <h1 class="max-w-md text-3xl leading-tight font-semibold">
                    Nenhum prazo de convênio perdido. Nenhuma inadimplência no CADIN.
                </h1>
                <p class="mt-4 max-w-md text-slate-300">
                    Acompanhe convênios, emendas e contratos da prefeitura e receba alertas antes
                    de cada vencimento.
                </p>
            </div>
            <div class="text-sm text-slate-400">Castilho Soluções Digitais</div>
        </section>

        <section class="flex items-center justify-center p-6">
            <form class="w-full max-w-sm" @submit.prevent="entrar">
                <h2 class="text-2xl font-semibold">Entrar</h2>
                <p class="mt-1 text-sm text-slate-500">Use o e-mail cadastrado pela sua prefeitura.</p>

                <div v-if="erro" role="alert" class="mt-6 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ erro }}
                </div>

                <label class="mt-6 block text-sm font-medium" for="email">E-mail</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="username"
                    class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 focus:outline-none"
                >

                <label class="mt-4 block text-sm font-medium" for="password">Senha</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 focus:outline-none"
                >

                <button
                    type="submit"
                    :disabled="carregando"
                    class="mt-6 w-full rounded-md bg-blue-700 px-4 py-2.5 font-medium text-white hover:bg-blue-800 disabled:opacity-60"
                >
                    {{ carregando ? 'Entrando…' : 'Entrar' }}
                </button>
            </form>
        </section>
    </main>
</template>
