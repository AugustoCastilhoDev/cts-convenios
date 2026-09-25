<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import DoisFatoresAtivacao from '../components/DoisFatoresAtivacao.vue';
import TelaDeAcesso from '../layouts/TelaDeAcesso.vue';

// Administradores só usam o sistema depois de ativar a verificação em duas etapas (o servidor também barra
// tudo com 403 "2fa_obrigatorio" até lá).
const auth = useAuthStore();
const router = useRouter();

async function ativado() {
    // O servidor passou a marcar o 2FA como ativo: recarrega o usuário e libera o sistema.
    await auth.fetchUser();
    router.replace({ name: 'dashboard' });
}

async function sair() {
    await auth.logout();
    router.replace({ name: 'login' });
}
</script>

<template>
    <TelaDeAcesso>
        <div class="cartao w-full p-8">
            <h2 class="text-2xl font-semibold tracking-tight text-petroleo">Ative a verificação em duas etapas</h2>
            <p class="mt-1 mb-6 text-sm text-slate-500">
                Como administrador, você cuida do acesso de outras pessoas. Por isso, além da senha, o login pede um
                código de 6 dígitos gerado por um app no seu celular.
            </p>

            <DoisFatoresAtivacao @ativado="ativado" />

            <p class="mt-4 text-center text-sm">
                <button type="button" class="text-brand-700 underline hover:no-underline" @click="sair">Sair</button>
            </p>
        </div>
    </TelaDeAcesso>
</template>
