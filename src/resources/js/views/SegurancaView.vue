<script setup>
import { computed, ref } from 'vue';
import { api } from '../services/api';
import CodigosDeRecuperacao from '../components/CodigosDeRecuperacao.vue';
import ConfirmarSenhaModal from '../components/ConfirmarSenhaModal.vue';
import DoisFatoresAtivacao from '../components/DoisFatoresAtivacao.vue';
import ModalBase from '../components/ModalBase.vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();

const ativo = computed(() => auth.user?.two_factor_ativo === true);
const obrigatorio = computed(() => auth.user?.two_factor_obrigatorio === true);
const restantes = computed(() => auth.user?.two_factor_codigos_restantes ?? 0);

// 'codigos' (gerar novos) ou 'desativar': qual confirmação de senha está aberta.
const confirmando = ref(null);
// Códigos novos a mostrar (uma vez), num aviso que só fecha pelo botão.
const novosCodigos = ref(null);
const aviso = ref('');

const gerarCodigos = ({ password }) => api.post('/2fa/codigos-recuperacao', { password });
const desativar = ({ password, codigo }) => api.delete('/2fa', { password, codigo });

async function codigosGerados(resposta) {
    confirmando.value = null;
    novosCodigos.value = resposta.data.codigos;
    await auth.fetchUser();
}

async function desativado() {
    confirmando.value = null;
    aviso.value = 'Verificação em duas etapas desativada. Ela só voltará a valer se você ativar de novo.';
    await auth.fetchUser();
}

async function ativadoAgora() {
    aviso.value = '';
    await auth.fetchUser();
}
</script>

<template>
    <div class="max-w-2xl">
        <h1 class="text-2xl font-semibold tracking-tight text-petroleo">Segurança da conta</h1>
        <p class="text-sm text-slate-500">Proteja o seu acesso com um segundo passo além da senha.</p>

        <p v-if="aviso" role="status" class="mt-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{{ aviso }}</p>

        <section class="cartao mt-6 p-6" aria-labelledby="titulo-2fa">
            <div class="flex flex-wrap items-center gap-3">
                <h2 id="titulo-2fa" class="text-lg font-semibold">Verificação em duas etapas</h2>
                <span class="rounded px-1.5 py-0.5 text-xs font-medium" :class="ativo ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-600'">
                    {{ ativo ? 'Ativa' : 'Desativada' }}
                </span>
            </div>

            <template v-if="ativo">
                <p class="mt-2 text-sm text-slate-600">
                    Ao entrar, além da senha, você digita o código de 6 dígitos do app autenticador.
                    <template v-if="obrigatorio">É obrigatória para o seu perfil.</template>
                </p>

                <p class="mt-4 text-sm" :class="restantes <= 2 ? 'font-medium text-amber-800' : 'text-slate-600'">
                    Códigos de recuperação que ainda valem: <strong>{{ restantes }}</strong>
                    <template v-if="restantes <= 2">. Gere novos antes que acabem.</template>
                </p>

                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50" @click="confirmando = 'codigos'">
                        Gerar novos códigos
                    </button>
                    <button
                        v-if="!obrigatorio"
                        type="button"
                        class="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
                        @click="confirmando = 'desativar'"
                    >
                        Desativar
                    </button>
                </div>
            </template>

            <template v-else>
                <p class="mt-2 mb-5 text-sm text-slate-600">
                    Com ela ativa, quem descobrir a sua senha ainda não consegue entrar: falta o código do celular.
                    <template v-if="obrigatorio">É obrigatória para o seu perfil.</template>
                </p>
                <DoisFatoresAtivacao @ativado="ativadoAgora" />
            </template>
        </section>

        <ConfirmarSenhaModal
            v-if="confirmando === 'codigos'"
            titulo="Gerar novos códigos de recuperação"
            texto="Os códigos antigos, usados ou não, deixam de valer. Confirme com a sua senha."
            rotulo-botao="Gerar códigos"
            :acao="gerarCodigos"
            @concluido="codigosGerados"
            @fechar="confirmando = null"
        />

        <ConfirmarSenhaModal
            v-if="confirmando === 'desativar'"
            titulo="Desativar a verificação em duas etapas"
            texto="Sua conta volta a depender só da senha. Confirme com a sua senha e um código atual."
            rotulo-botao="Desativar"
            pede-codigo
            :acao="desativar"
            @concluido="desativado"
            @fechar="confirmando = null"
        />

        <!-- Os códigos só aparecem agora: Esc e clique fora são ignorados (fechar sem querer os perderia). -->
        <ModalBase v-if="novosCodigos" titulo="Novos códigos de recuperação" @fechar="() => {}">
            <div class="mt-3">
                <CodigosDeRecuperacao :codigos="novosCodigos" />
            </div>
            <div class="mt-6 flex justify-end">
                <button type="button" class="rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800" @click="novosCodigos = null">
                    Guardei os códigos, fechar
                </button>
            </div>
        </ModalBase>
    </div>
</template>
