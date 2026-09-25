<script setup>
import { ref } from 'vue';
import ModalBase from './ModalBase.vue';

// A senha temporária só é conhecida agora: o servidor a guarda apenas como hash e não a mostra de novo.
// Por isso este aviso só fecha pelo botão (Esc e clique fora são ignorados: fechar sem querer perderia a senha).
defineProps({
    titulo: { type: String, required: true },
    nome: { type: String, required: true },
    email: { type: String, required: true },
    senha: { type: String, required: true },
});
const emit = defineEmits(['fechar']);

const copiada = ref(false);
const falhaAoCopiar = ref(false);

async function copiar(senha) {
    falhaAoCopiar.value = false;

    try {
        await navigator.clipboard.writeText(senha);
        copiada.value = true;
    } catch {
        // Sem permissão de área de transferência (ou página sem HTTPS): a senha continua visível para copiar à mão.
        falhaAoCopiar.value = true;
    }
}
</script>

<template>
    <ModalBase :titulo="titulo" @fechar="() => {}">
        <p class="mt-3 text-sm text-slate-600">
            Senha temporária de <strong class="text-slate-900">{{ nome }}</strong> (<span class="break-all">{{ email }}</span>):
        </p>

        <div class="mt-3 flex items-stretch gap-2">
            <output
                class="flex-1 rounded-md border border-brand-200 bg-brand-50 px-4 py-3 text-center font-mono text-2xl font-semibold tracking-wider text-petroleo select-all"
                aria-label="Senha temporária"
            >{{ senha }}</output>
            <button
                type="button"
                class="rounded-md border border-slate-300 px-4 text-sm font-medium hover:bg-slate-50"
                @click="copiar(senha)"
            >
                {{ copiada ? 'Copiada!' : 'Copiar' }}
            </button>
        </div>
        <p v-if="falhaAoCopiar" role="alert" class="mt-2 text-xs text-red-700">Não foi possível copiar automaticamente. Selecione a senha acima e copie.</p>

        <ul class="mt-4 space-y-2 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="note">
            <li><strong>Esta senha aparece só agora.</strong> Anote ou copie antes de fechar: depois não dá para ver de novo (só gerar outra em "Redefinir senha").</li>
            <li>Repasse por um canal seguro (pessoalmente ou mensagem direta), <strong>nunca</strong> em grupo ou e-mail com várias pessoas.</li>
            <li>No primeiro acesso a pessoa será obrigada a criar a própria senha, e a temporária deixa de valer.</li>
        </ul>

        <div class="mt-6 flex justify-end">
            <button
                type="button"
                class="rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800"
                @click="emit('fechar')"
            >
                Anotei a senha, fechar
            </button>
        </div>
    </ModalBase>
</template>
