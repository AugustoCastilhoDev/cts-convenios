<script setup>
import { computed } from 'vue';

// regularidade: { situacao: 'regular' | 'risco', prazos_vencidos, convenios_afetados, maior_atraso_dias }
const props = defineProps({ regularidade: { type: Object, required: true } });

const emRisco = computed(() => props.regularidade.situacao === 'risco');
const prazos = computed(() => props.regularidade.prazos_vencidos);
</script>

<template>
    <div>
        <div
            role="status"
            class="inline-flex flex-wrap items-center gap-x-3 gap-y-1 rounded-full border px-4 py-1.5 text-sm"
            :class="emRisco ? 'border-red-200 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-green-800'"
        >
            <span class="size-2.5 rounded-full" :class="emRisco ? 'bg-red-500 motion-safe:animate-pulse' : 'bg-green-500'" aria-hidden="true" />
            <span class="font-medium">{{ emRisco ? 'Atenção: Risco de Inadimplência (CADIN)' : 'Município Regular' }}</span>
            <span v-if="emRisco" class="text-xs opacity-80">
                {{ prazos }} {{ prazos === 1 ? 'prazo vencido' : 'prazos vencidos' }} · maior atraso: {{ regularidade.maior_atraso_dias }}
                {{ regularidade.maior_atraso_dias === 1 ? 'dia' : 'dias' }}
                · <a href="#prazos-criticos" class="underline hover:no-underline">ver prazos</a>
            </span>
        </div>
        <!-- O indicador só enxerga o que está cadastrado aqui: não pode parecer uma certidão. -->
        <p class="mt-1.5 text-xs text-slate-500">
            Situação do município pelos prazos cadastrados no CTS. Não substitui a consulta oficial ao CADIN/CAUC.
        </p>
    </div>
</template>
