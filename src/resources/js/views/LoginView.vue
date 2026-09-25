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

// Segundo passo (quem tem verificação em duas etapas): o desafio recebido depois da senha e o código digitado.
const desafio = ref('');
const codigo = ref('');
const usandoRecuperacao = ref(false);

function mostrarErro(e) {
    erro.value = e instanceof ApiError
        ? (Object.values(e.errors)[0]?.[0] ?? e.message)
        : 'Não foi possível conectar ao servidor.';
}

async function entrar() {
    carregando.value = true;
    erro.value = '';

    try {
        const segundoPasso = await auth.login(form.email, form.password);

        if (segundoPasso) {
            desafio.value = segundoPasso.desafio;
            form.password = '';

            return;
        }

        // Quem entrou com senha temporária (ou sem o 2FA obrigatório) é levado à tela certa pelo guarda de rotas.
        router.push({ name: 'dashboard' });
    } catch (e) {
        mostrarErro(e);
    } finally {
        carregando.value = false;
    }
}

async function verificar() {
    carregando.value = true;
    erro.value = '';

    try {
        await auth.verificarDoisFatores(desafio.value, codigo.value.trim());
        router.push({ name: 'dashboard' });
    } catch (e) {
        mostrarErro(e);

        // O desafio expirou ou acabaram as tentativas: recomeça pelo e-mail e senha.
        if (e instanceof ApiError && e.errors.desafio) {
            voltar(erro.value);
        }
    } finally {
        carregando.value = false;
    }
}

function voltar(mensagem = '') {
    desafio.value = '';
    codigo.value = '';
    usandoRecuperacao.value = false;
    erro.value = mensagem;
}

function alternarRecuperacao() {
    usandoRecuperacao.value = !usandoRecuperacao.value;
    codigo.value = '';
    erro.value = '';
}

const campo = 'mt-1 w-full rounded-md border border-borda bg-white px-3 py-2.5 shadow-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none';
</script>

<template>
    <TelaDeAcesso>
        <form v-if="desafio" class="cartao w-full p-8" @submit.prevent="verificar">
            <h2 class="text-2xl font-semibold tracking-tight text-petroleo">Verificação em duas etapas</h2>
            <p class="mt-1 text-sm text-slate-500">
                <template v-if="usandoRecuperacao">Digite um dos seus códigos de recuperação. Cada um vale uma vez.</template>
                <template v-else>Digite o código de 6 dígitos que o app autenticador mostra no seu celular.</template>
            </p>

            <div v-if="erro" role="alert" class="mt-6 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ erro }}
            </div>

            <label class="mt-6 block text-sm font-medium" for="codigo">{{ usandoRecuperacao ? 'Código de recuperação' : 'Código do app' }}</label>
            <input
                id="codigo"
                v-model="codigo"
                required
                autofocus
                autocomplete="one-time-code"
                :inputmode="usandoRecuperacao ? 'text' : 'numeric'"
                :maxlength="usandoRecuperacao ? 16 : 7"
                :placeholder="usandoRecuperacao ? 'XXXXX-XXXXX' : '000000'"
                :class="[campo, 'text-center font-mono text-lg tracking-widest']"
            >

            <button
                type="submit"
                :disabled="carregando"
                class="mt-6 w-full rounded-md bg-brand-700 px-4 py-2.5 font-medium text-white shadow-sm hover:bg-brand-800 disabled:opacity-60"
            >
                {{ carregando ? 'Verificando…' : 'Entrar' }}
            </button>

            <p class="mt-4 flex flex-col items-center gap-2 text-sm">
                <button type="button" class="text-brand-700 underline hover:no-underline" @click="alternarRecuperacao">
                    {{ usandoRecuperacao ? 'Usar o código do app' : 'Perdi o celular: usar um código de recuperação' }}
                </button>
                <button type="button" class="text-slate-500 underline hover:no-underline" @click="voltar()">Voltar</button>
            </p>
        </form>

        <form v-else class="cartao w-full p-8" @submit.prevent="entrar">
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
