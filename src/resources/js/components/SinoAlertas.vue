<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Icone from './Icone.vue';
import { useNotificacoesStore } from '../stores/notificacoes';
import { corDoPrazo, formatarData, textoDoPrazo } from '../utils/format';

// escuro: usado sobre a barra escura do celular.
defineProps({ escuro: { type: Boolean, default: false } });

const notificacoes = useNotificacoesStore();
const router = useRouter();
const aberto = ref(false);

function aoTeclar(evento) {
    if (evento.key === 'Escape') {
        aberto.value = false;
    }
}

onMounted(() => document.addEventListener('keydown', aoTeclar));
onBeforeUnmount(() => document.removeEventListener('keydown', aoTeclar));

function abrirItem(item) {
    notificacoes.marcarLida(item);
    aberto.value = false;
    router.push({ name: 'convenio', params: { id: item.convenio_id } });
}
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="relative rounded-full p-2 transition-colors"
            :class="escuro ? 'text-white hover:bg-white/10' : 'text-slate-600 hover:bg-white hover:text-petroleo hover:shadow-sm'"
            aria-haspopup="dialog"
            :aria-expanded="aberto"
            :aria-label="notificacoes.naoLidas ? `Alertas de prazo: ${notificacoes.naoLidas} não lidos` : 'Alertas de prazo: nenhum não lido'"
            @click="aberto = !aberto"
        >
            <Icone nome="sino" class="size-6" />
            <span
                v-if="notificacoes.naoLidas"
                class="absolute top-0 right-0 grid min-w-5 place-items-center rounded-full bg-red-600 px-1 text-xs leading-5 font-semibold text-white ring-2"
                :class="escuro ? 'ring-petroleo' : 'ring-canvas'"
                aria-hidden="true"
            >
                {{ notificacoes.naoLidas > 9 ? '9+' : notificacoes.naoLidas }}
            </span>
        </button>

        <div v-if="aberto" class="fixed inset-0 z-40" @click="aberto = false" />

        <div
            v-if="aberto"
            role="dialog"
            aria-label="Alertas de prazo"
            class="absolute right-0 z-50 mt-2 w-96 max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg border border-borda bg-white text-slate-900 shadow-cartao-alto"
        >
            <div class="flex items-center justify-between border-b border-borda px-4 py-3">
                <h2 class="font-semibold text-petroleo">Alertas de prazo</h2>
                <button
                    v-if="notificacoes.naoLidas"
                    type="button"
                    class="text-sm text-brand-700 hover:underline"
                    @click="notificacoes.marcarTodasLidas()"
                >
                    Marcar todos como lidos
                </button>
            </div>

            <ul class="max-h-[26rem] divide-y divide-slate-100 overflow-y-auto">
                <li v-for="item in notificacoes.itens" :key="item.id">
                    <button
                        type="button"
                        class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50"
                        :class="{ 'bg-brand-50/60': !item.lida }"
                        @click="abrirItem(item)"
                    >
                        <span class="mt-1.5 size-2.5 shrink-0 rounded-full" :class="corDoPrazo(item.dias)" aria-hidden="true" />
                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline justify-between gap-2">
                                <span class="truncate text-sm text-petroleo" :class="item.lida ? 'font-medium' : 'font-semibold'">
                                    Convênio {{ item.numero_convenio }}
                                </span>
                                <span v-if="!item.lida" class="shrink-0 text-xs font-medium text-brand-700">novo</span>
                            </span>
                            <span class="block text-sm text-slate-600">
                                {{ item.tipo_prazo_label }}: {{ textoDoPrazo(item.dias) }}
                            </span>
                            <span class="block text-xs text-slate-500">Prazo em {{ formatarData(item.data_prazo) }}</span>
                        </span>
                    </button>
                </li>

                <li v-if="!notificacoes.itens.length" class="px-4 py-8 text-center text-sm text-slate-500">
                    {{ notificacoes.carregado ? 'Nenhum alerta de prazo nos últimos 60 dias.' : 'Carregando…' }}
                </li>
            </ul>

            <p class="border-t border-borda bg-slate-50 px-4 py-2 text-xs text-slate-500">
                Estes alertas também chegam por e-mail.
            </p>
        </div>
    </div>
</template>
