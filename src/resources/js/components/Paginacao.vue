<script setup>
// meta: o objeto "meta" da resposta paginada do Laravel.
defineProps({ meta: { type: Object, default: null } });
const emit = defineEmits(['pagina']);
</script>

<template>
    <div v-if="meta && meta.last_page > 1" class="mt-4 flex items-center justify-between text-sm">
        <span class="text-slate-500">Página {{ meta.current_page }} de {{ meta.last_page }} · {{ meta.total }} registros</span>
        <div class="flex gap-2">
            <button
                class="rounded-md border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50 disabled:opacity-50"
                :disabled="meta.current_page <= 1"
                @click="emit('pagina', meta.current_page - 1)"
            >
                Anterior
            </button>
            <button
                class="rounded-md border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50 disabled:opacity-50"
                :disabled="meta.current_page >= meta.last_page"
                @click="emit('pagina', meta.current_page + 1)"
            >
                Próxima
            </button>
        </div>
    </div>
</template>
