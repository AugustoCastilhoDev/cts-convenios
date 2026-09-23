<script setup>
import { computed, onMounted, ref } from 'vue';
import { api, ApiError } from '../services/api';
import { useAuthStore } from '../stores/auth';
import { formatarMoeda, formatarData, situacaoPrazo } from '../utils/format';

const auth = useAuthStore();

// Ordem do fluxo de um convênio; as cores são strings completas para o Tailwind enxergá-las.
const colunas = [
    { status: 'proposta', titulo: 'Proposta', barra: 'bg-slate-400' },
    { status: 'em_analise', titulo: 'Em Análise', barra: 'bg-amber-400' },
    { status: 'aprovado', titulo: 'Aprovado', barra: 'bg-blue-500' },
    { status: 'em_execucao', titulo: 'Em Execução', barra: 'bg-indigo-500' },
    { status: 'prestacao_contas', titulo: 'Prestação de Contas', barra: 'bg-purple-500' },
    { status: 'finalizado', titulo: 'Finalizado', barra: 'bg-green-500' },
];

const convenios = ref([]);
const carregando = ref(true);
const erro = ref('');
const busca = ref('');
const arrastando = ref(null);
const sobreColuna = ref(null);

const porColuna = computed(() => {
    const termo = busca.value.trim().toLowerCase();
    const filtrados = termo
        ? convenios.value.filter((c) =>
            [c.numero_convenio, c.objeto, c.orgao_concedente].some((t) => t?.toLowerCase().includes(termo)))
        : convenios.value;

    return Object.fromEntries(
        colunas.map((col) => [col.status, filtrados.filter((c) => c.status === col.status)]),
    );
});

async function carregar() {
    carregando.value = true;
    erro.value = '';

    try {
        const resposta = await api.get('/convenios', { por_pagina: 200 });
        convenios.value = resposta.data;
    } catch (e) {
        erro.value = e.message;
    } finally {
        carregando.value = false;
    }
}

function iniciarArraste(evento, convenio) {
    arrastando.value = convenio;
    evento.dataTransfer.effectAllowed = 'move';
    evento.dataTransfer.setData('text/plain', convenio.id);
}

function finalizarArraste() {
    arrastando.value = null;
    sobreColuna.value = null;
}

async function soltar(status) {
    const convenio = arrastando.value;
    finalizarArraste();

    if (!convenio || convenio.status === status) {
        return;
    }

    const anterior = { ...convenio };

    // Atualização otimista: o card muda de coluna na hora; se a API recusar, volta.
    convenio.status = status;

    try {
        // A API atualiza o convênio inteiro (PUT), então reenviamos os demais campos.
        const resposta = await api.put(`/convenios/${convenio.id}`, {
            numero_convenio: anterior.numero_convenio,
            orgao_concedente: anterior.orgao_concedente,
            objeto: anterior.objeto,
            valor_repasse: anterior.valor_repasse,
            valor_contrapartida: anterior.valor_contrapartida,
            data_assinatura: anterior.data_assinatura,
            data_vigencia_fim: anterior.data_vigencia_fim,
            prazo_prestacao_contas: anterior.prazo_prestacao_contas,
            status,
        });
        Object.assign(convenio, resposta.data);
    } catch (e) {
        Object.assign(convenio, anterior);
        erro.value = e instanceof ApiError && e.status === 403
            ? 'Seu perfil não tem permissão para alterar convênios.'
            : `Não foi possível mover o convênio: ${e.message}`;
    }
}

onMounted(carregar);
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Convênios</h1>
                <p class="text-sm text-slate-500">
                    {{ convenios.length }} no total
                    <template v-if="auth.podeEditar"> · arraste um card para mudar a etapa</template>
                </p>
            </div>
            <input
                v-model="busca"
                type="search"
                placeholder="Buscar por número, objeto ou órgão"
                class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm sm:w-72 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 focus:outline-none"
            >
        </div>

        <div v-if="erro" role="alert" class="mt-4 flex items-start justify-between rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            <span>{{ erro }}</span>
            <button class="ml-4 font-medium underline" @click="erro = ''">Fechar</button>
        </div>

        <p v-if="carregando" class="mt-8 text-sm text-slate-500">Carregando convênios…</p>

        <div v-else class="mt-4 flex gap-4 overflow-x-auto pb-4">
            <section
                v-for="coluna in colunas"
                :key="coluna.status"
                class="w-72 shrink-0 rounded-lg bg-slate-100 transition-colors"
                :class="{ 'ring-2 ring-blue-500': sobreColuna === coluna.status }"
                @dragover.prevent="sobreColuna = coluna.status"
                @dragleave="sobreColuna = null"
                @drop.prevent="soltar(coluna.status)"
            >
                <header class="flex items-center gap-2 px-3 pt-3 pb-2">
                    <span class="size-2.5 rounded-full" :class="coluna.barra" />
                    <h2 class="text-sm font-semibold">{{ coluna.titulo }}</h2>
                    <span class="ml-auto rounded-full bg-white px-2 text-xs text-slate-600">
                        {{ porColuna[coluna.status].length }}
                    </span>
                </header>

                <div class="min-h-24 space-y-2 p-2">
                    <article
                        v-for="c in porColuna[coluna.status]"
                        :key="c.id"
                        :draggable="auth.podeEditar"
                        class="rounded-md border border-slate-200 bg-white p-3 shadow-sm"
                        :class="[auth.podeEditar ? 'cursor-grab active:cursor-grabbing' : '', arrastando?.id === c.id ? 'opacity-40' : '']"
                        @dragstart="iniciarArraste($event, c)"
                        @dragend="finalizarArraste"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="text-sm font-semibold">{{ c.numero_convenio }}</h3>
                            <span
                                v-if="c.status !== 'finalizado' && situacaoPrazo(c.dias_para_vencimento)"
                                class="shrink-0 rounded px-1.5 py-0.5 text-xs font-medium"
                                :class="situacaoPrazo(c.dias_para_vencimento).classes"
                            >
                                {{ situacaoPrazo(c.dias_para_vencimento).texto }}
                            </span>
                        </div>
                        <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ c.objeto }}</p>
                        <p class="mt-1 truncate text-xs text-slate-500">{{ c.orgao_concedente }}</p>

                        <dl class="mt-3 grid grid-cols-2 gap-2 border-t border-slate-100 pt-2 text-xs">
                            <div>
                                <dt class="text-slate-500">Saldo disponível</dt>
                                <dd class="font-medium" :class="c.saldo_disponivel < 0 ? 'text-red-700' : ''">
                                    {{ formatarMoeda(c.saldo_disponivel) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Vigência até</dt>
                                <dd class="font-medium">{{ formatarData(c.data_vigencia_fim) }}</dd>
                            </div>
                        </dl>
                    </article>

                    <p v-if="!porColuna[coluna.status].length" class="px-2 py-4 text-center text-xs text-slate-400">
                        Nenhum convênio
                    </p>
                </div>
            </section>
        </div>
    </div>
</template>
