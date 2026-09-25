<script setup>
import { ref } from 'vue';
import { salvarBlob } from '../services/api';

// Os códigos só são conhecidos agora: o servidor guarda apenas um hash deles e não os mostra de novo.
const props = defineProps({
    codigos: { type: Array, required: true },
});

const copiados = ref(false);
const falhaAoCopiar = ref(false);

async function copiar() {
    falhaAoCopiar.value = false;

    try {
        await navigator.clipboard.writeText(props.codigos.join('\n'));
        copiados.value = true;
    } catch {
        // Sem permissão de área de transferência: os códigos continuam visíveis para copiar à mão.
        falhaAoCopiar.value = true;
    }
}

function baixar() {
    const texto = `CTS Convênios - códigos de recuperação\nCada código vale uma vez.\n\n${props.codigos.join('\n')}\n`;

    salvarBlob(new Blob([texto], { type: 'text/plain;charset=utf-8' }), 'codigos-recuperacao-cts-convenios.txt');
}
</script>

<template>
    <div>
        <ul class="grid grid-cols-2 gap-2 rounded-md border border-brand-200 bg-brand-50 p-4 font-mono text-base tracking-wider text-petroleo" aria-label="Códigos de recuperação">
            <li v-for="codigo in codigos" :key="codigo" class="select-all">{{ codigo }}</li>
        </ul>

        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-50" @click="copiar">
                {{ copiados ? 'Copiados!' : 'Copiar' }}
            </button>
            <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-50" @click="baixar">Baixar (.txt)</button>
        </div>
        <p v-if="falhaAoCopiar" role="alert" class="mt-2 text-xs text-red-700">Não foi possível copiar automaticamente. Selecione os códigos acima e copie.</p>

        <ul class="mt-4 space-y-2 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="note">
            <li><strong>Estes códigos aparecem só agora.</strong> Guarde-os num gerenciador de senhas ou impressos, em lugar seguro.</li>
            <li>Cada código vale <strong>uma vez</strong> e substitui o app se você perder o celular.</li>
        </ul>
    </div>
</template>
