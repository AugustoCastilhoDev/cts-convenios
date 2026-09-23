<script setup>
// Rótulo + controle (no slot) + mensagem de erro de validação do servidor.
defineProps({
    rotulo: { type: String, required: true },
    para: { type: String, required: true },
    erro: { type: String, default: '' },
    ajuda: { type: String, default: '' },
});
</script>

<template>
    <div>
        <label class="text-sm font-medium" :for="para">{{ rotulo }}</label>
        <slot />
        <p v-if="ajuda && !erro" class="mt-1 text-xs text-slate-500">{{ ajuda }}</p>
        <p v-if="erro" class="mt-1 text-xs text-red-600">{{ erro }}</p>
    </div>
</template>
