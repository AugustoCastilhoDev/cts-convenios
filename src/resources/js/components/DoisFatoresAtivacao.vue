<script setup>
import { computed, ref } from 'vue';
import { api, ApiError } from '../services/api';
import CodigosDeRecuperacao from './CodigosDeRecuperacao.vue';
import QrCode from './QrCode.vue';

// Ativação em três passos: confirmar a senha, ligar o app autenticador (QR Code ou chave) e guardar os
// códigos de recuperação. Usado na tela obrigatória dos administradores e na tela "Segurança da conta".
const emit = defineEmits(['ativado']);

const passo = ref('senha');
const senha = ref('');
const codigo = ref('');
const ativacao = ref(null);
const codigos = ref([]);
const erro = ref('');
const enviando = ref(false);

// A chave em blocos de 4 caracteres é mais fácil de digitar no app.
const chaveEmBlocos = computed(() => (ativacao.value?.segredo.match(/.{1,4}/g) ?? []).join(' '));

async function chamar(acao) {
    enviando.value = true;
    erro.value = '';

    try {
        await acao();
    } catch (e) {
        erro.value = e instanceof ApiError
            ? (Object.values(e.errors)[0]?.[0] ?? e.message)
            : 'Não foi possível conectar ao servidor.';
    } finally {
        enviando.value = false;
    }
}

function iniciar() {
    return chamar(async () => {
        ativacao.value = (await api.post('/2fa/iniciar', { password: senha.value })).data;
        senha.value = '';
        passo.value = 'app';
    });
}

function confirmar() {
    return chamar(async () => {
        codigos.value = (await api.post('/2fa/confirmar', { codigo: codigo.value.trim() })).data.codigos;
        ativacao.value = null;
        passo.value = 'codigos';
    });
}

const campo = 'mt-1 w-full rounded-md border border-borda bg-white px-3 py-2.5 shadow-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none';
const botao = 'w-full rounded-md bg-brand-700 px-4 py-2.5 font-medium text-white shadow-sm hover:bg-brand-800 disabled:opacity-60';
</script>

<template>
    <div>
        <div v-if="erro" role="alert" class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ erro }}</div>

        <form v-if="passo === 'senha'" @submit.prevent="iniciar">
            <p class="text-sm text-slate-600">Para começar, confirme a sua senha.</p>
            <label class="mt-4 block text-sm font-medium" for="dois-fatores-senha">Sua senha</label>
            <input id="dois-fatores-senha" v-model="senha" type="password" required autocomplete="current-password" :class="campo">
            <button type="submit" :disabled="enviando" :class="[botao, 'mt-4']">{{ enviando ? 'Verificando…' : 'Continuar' }}</button>
        </form>

        <form v-else-if="passo === 'app'" @submit.prevent="confirmar">
            <ol class="space-y-4 text-sm text-slate-700">
                <li>
                    <strong>Instale um app autenticador</strong> no celular, se ainda não tiver
                    (Google Authenticator, Microsoft Authenticator, Authy ou o seu gerenciador de senhas).
                </li>
                <li>
                    <strong>Adicione esta conta no app:</strong> escaneie o QR Code ou digite a chave.
                    <div class="mt-3 flex flex-col items-center gap-3 sm:flex-row sm:items-start">
                        <QrCode :texto="ativacao.url" />
                        <div class="min-w-0">
                            <p class="text-xs text-slate-500">Não consegue escanear? Digite esta chave no app (tipo "baseada em tempo"):</p>
                            <output class="mt-1 block rounded-md border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm tracking-wider break-all text-petroleo select-all" aria-label="Chave de configuração">{{ chaveEmBlocos }}</output>
                        </div>
                    </div>
                </li>
                <li>
                    <label class="font-semibold" for="dois-fatores-codigo">Digite o código de 6 dígitos que o app mostra</label>
                    <input
                        id="dois-fatores-codigo"
                        v-model="codigo"
                        required
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="7"
                        placeholder="000000"
                        :class="[campo, 'text-center font-mono text-lg tracking-widest']"
                    >
                </li>
            </ol>
            <button type="submit" :disabled="enviando" :class="[botao, 'mt-5']">{{ enviando ? 'Verificando…' : 'Ativar verificação em duas etapas' }}</button>
        </form>

        <div v-else>
            <p class="mb-3 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">Verificação em duas etapas ativada.</p>
            <p class="mb-3 text-sm text-slate-700">Guarde os códigos de recuperação abaixo: são a saída se você perder o celular.</p>
            <CodigosDeRecuperacao :codigos="codigos" />
            <button type="button" :class="[botao, 'mt-5']" @click="emit('ativado')">Guardei os códigos, concluir</button>
        </div>
    </div>
</template>
