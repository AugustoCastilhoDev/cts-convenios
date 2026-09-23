<script setup>
import { onBeforeUnmount, onMounted } from 'vue';

defineProps({ titulo: { type: String, required: true }, largura: { type: String, default: 'max-w-lg' } });
const emit = defineEmits(['fechar']);

function aoTeclar(evento) {
    if (evento.key === 'Escape') {
        emit('fechar');
    }
}

onMounted(() => document.addEventListener('keydown', aoTeclar));
onBeforeUnmount(() => document.removeEventListener('keydown', aoTeclar));
</script>

<template>
    <div class="fixed inset-0 z-30 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4" @mousedown.self="emit('fechar')">
        <div role="dialog" aria-modal="true" :aria-label="titulo" class="my-8 w-full rounded-lg bg-white p-6 shadow-xl" :class="largura">
            <h2 class="text-lg font-semibold">{{ titulo }}</h2>
            <slot />
        </div>
    </div>
</template>
