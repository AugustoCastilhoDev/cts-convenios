<script setup>
import { computed, ref } from 'vue';
import Icone from './Icone.vue';
import ModalBase from './ModalBase.vue';
import { formatarData, textoDoPrazo } from '../utils/format';

// prazo: item de "prazos críticos" do painel (convenio_id, numero_convenio, tipo_prazo_label, data_prazo, dias).
const props = defineProps({ prazo: { type: Object, required: true } });
const emit = defineEmits(['fechar']);

const etapa = ref('previa'); // previa -> simulando -> simulado
const situacao = computed(() => textoDoPrazo(props.prazo.dias));

const mensagem = computed(() => (
    `o prazo de "${props.prazo.tipo_prazo_label}" do convênio ${props.prazo.numero_convenio} ${situacao.value} `
    + `(${formatarData(props.prazo.data_prazo)}). Solicitamos a verificação das pendências e o registro das providências no sistema.`
));

function simular() {
    etapa.value = 'simulando';
    // Só feedback visual: nada é enviado. Uma pausa curta para a pessoa perceber que houve uma ação.
    setTimeout(() => {
        etapa.value = 'simulado';
    }, 700);
}
</script>

<template>
    <ModalBase titulo="Cobrança de prazo ao fiscal" @fechar="emit('fechar')">
        <div class="mt-4 flex gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="note">
            <strong class="shrink-0">Prévia.</strong>
            <span>O disparo real ainda não está ativo: esta tela simula o envio e <strong>nenhuma notificação é enviada</strong>.</span>
        </div>

        <template v-if="etapa !== 'simulado'">
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-slate-500">Convênio</dt>
                    <dd class="font-medium">{{ prazo.numero_convenio }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Prazo</dt>
                    <dd class="font-medium">{{ prazo.tipo_prazo_label }} · {{ situacao }}</dd>
                </div>
            </dl>

            <p class="mt-4 text-sm font-medium">Mensagem que o fiscal responsável receberia</p>
            <blockquote class="mt-1 rounded-md border border-borda bg-slate-50 px-3 py-2 text-sm text-slate-700">
                Prezado(a) fiscal, {{ mensagem }}
            </blockquote>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50" @click="emit('fechar')">Cancelar</button>
                <button
                    type="button"
                    :disabled="etapa === 'simulando'"
                    class="inline-flex items-center gap-2 rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800 disabled:opacity-60"
                    @click="simular"
                >
                    <Icone nome="envelope" class="size-4" />
                    {{ etapa === 'simulando' ? 'Simulando…' : 'Simular disparo' }}
                </button>
            </div>
        </template>

        <div v-else role="status">
            <p class="mt-4 rounded-md border border-green-200 bg-green-50 px-3 py-3 text-sm text-green-900">
                <strong>Simulação concluída.</strong> Se o disparo estivesse ativo, o fiscal do convênio {{ prazo.numero_convenio }}
                receberia a mensagem por e-mail e no sino do sistema. Nenhuma notificação foi enviada.
            </p>
            <div class="mt-6 flex justify-end">
                <button type="button" class="rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800" @click="emit('fechar')">Fechar</button>
            </div>
        </div>
    </ModalBase>
</template>
