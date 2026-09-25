<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, ApiError } from '../services/api';
import TelaDeAcesso from '../layouts/TelaDeAcesso.vue';

const route = useRoute();
const router = useRouter();

// O link do e-mail traz ?token=...&email=.... Guardamos e tiramos do endereço: o token não fica no
// histórico do navegador nem no cabeçalho de origem de nenhuma requisição.
const token = String(route.query.token ?? '');
const email = String(route.query.email ?? '');
router.replace({ name: 'redefinir-senha' });

const form = reactive({ password: '', confirmacao: '' });
const erros = ref({});
const linkInvalido = ref(!token || !email);
const salvando = ref(false);
const concluido = ref(false);

async function salvar() {
    erros.value = {};

    if (form.password !== form.confirmacao) {
        erros.value = { confirmacao: ['A confirmação não confere com a nova senha.'] };
        return;
    }

    salvando.value = true;

    try {
        await api.post('/redefinir-senha', {
            token,
            email,
            password: form.password,
            password_confirmation: form.confirmacao,
        });
        concluido.value = true;
    } catch (e) {
        if (e instanceof ApiError && e.status === 422 && e.errors.token) {
            linkInvalido.value = true;
        } else if (e instanceof ApiError && e.status === 422) {
            erros.value = e.errors;
        } else {
            erros.value = { geral: [e instanceof ApiError ? e.message : 'Não foi possível conectar ao servidor.'] };
        }
    } finally {
        salvando.value = false;
    }
}

const campo = 'mt-1 w-full rounded-md border border-borda bg-white px-3 py-2.5 shadow-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none';
</script>

<template>
    <TelaDeAcesso>
        <div class="cartao w-full p-8">
            <h2 class="text-2xl font-semibold tracking-tight text-petroleo">Criar nova senha</h2>

            <div v-if="concluido" role="status">
                <p class="mt-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">
                    Senha alterada. Você já pode entrar com a nova senha.
                </p>
                <RouterLink
                    :to="{ name: 'login' }"
                    class="mt-6 block w-full rounded-md bg-brand-700 px-4 py-2.5 text-center font-medium text-white shadow-sm hover:bg-brand-800"
                >
                    Entrar
                </RouterLink>
            </div>

            <div v-else-if="linkInvalido" role="alert">
                <p class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    Este link é inválido ou expirou. Cada link vale por 60 minutos e só pode ser usado uma vez.
                </p>
                <RouterLink
                    :to="{ name: 'esqueci-senha' }"
                    class="mt-6 block w-full rounded-md bg-brand-700 px-4 py-2.5 text-center font-medium text-white shadow-sm hover:bg-brand-800"
                >
                    Pedir um novo link
                </RouterLink>
            </div>

            <form v-else @submit.prevent="salvar">
                <p class="mt-1 text-sm text-slate-500">Escolha uma senha que só você conheça.</p>

                <div v-if="erros.geral" role="alert" class="mt-6 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ erros.geral[0] }}
                </div>

                <label class="mt-6 block text-sm font-medium" for="nova-senha">Nova senha</label>
                <input id="nova-senha" v-model="form.password" type="password" required autocomplete="new-password" :class="campo">
                <p v-if="erros.password" class="mt-1 text-xs text-red-600">{{ erros.password[0] }}</p>
                <p v-else class="mt-1 text-xs text-slate-500">Mínimo de 10 caracteres, com letras e números.</p>

                <label class="mt-4 block text-sm font-medium" for="confirmacao">Repita a nova senha</label>
                <input id="confirmacao" v-model="form.confirmacao" type="password" required autocomplete="new-password" :class="campo">
                <p v-if="erros.confirmacao" class="mt-1 text-xs text-red-600">{{ erros.confirmacao[0] }}</p>

                <button
                    type="submit"
                    :disabled="salvando"
                    class="mt-6 w-full rounded-md bg-brand-700 px-4 py-2.5 font-medium text-white shadow-sm hover:bg-brand-800 disabled:opacity-60"
                >
                    {{ salvando ? 'Salvando…' : 'Salvar nova senha' }}
                </button>
            </form>
        </div>
    </TelaDeAcesso>
</template>
