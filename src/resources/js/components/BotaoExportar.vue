<script setup>
import { ref } from 'vue';
import { baixarRelatorio, salvarBlob } from '../services/api';

// opcoes: [{ rotulo, path, params }] — cada uma vira um item do menu.
const props = defineProps({
    rotulo: { type: String, required: true },
    opcoes: { type: Array, required: true },
});
const emit = defineEmits(['erro']);

const aberto = ref(false);
const gerando = ref(false);

async function gerar(opcao) {
    aberto.value = false;
    gerando.value = true;

    try {
        const { blob, nome } = await baixarRelatorio(opcao.path, opcao.params);
        salvarBlob(blob, nome);
    } catch (e) {
        emit('erro', `Não foi possível gerar o relatório: ${e.message}`);
    } finally {
        gerando.value = false;
    }
}
</script>

<template>
    <div class="relative" @keydown.esc="aberto = false">
        <button
            type="button"
            :disabled="gerando"
            aria-haspopup="menu"
            :aria-expanded="aberto"
            class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50 disabled:opacity-60"
            @click="aberto = !aberto"
        >
            {{ gerando ? 'Gerando…' : props.rotulo }} <span aria-hidden="true">▾</span>
        </button>

        <div v-if="aberto" class="fixed inset-0 z-10" @click="aberto = false" />
        <ul v-if="aberto" role="menu" class="absolute right-0 z-20 mt-1 w-44 rounded-md border border-slate-200 bg-white py-1 shadow-lg">
            <li v-for="opcao in props.opcoes" :key="opcao.rotulo" role="none">
                <button type="button" role="menuitem" class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50" @click="gerar(opcao)">
                    {{ opcao.rotulo }}
                </button>
            </li>
        </ul>
    </div>
</template>
