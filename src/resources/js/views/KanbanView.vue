<script setup>
import { computed, onMounted, ref } from 'vue';
import { api, ApiError } from '../services/api';
import { useAuthStore } from '../stores/auth';
import { formatarMoeda, formatarData, situacaoPrazo } from '../utils/format';
import { statusConvenio } from '../utils/status';
import BotaoExportar from '../components/BotaoExportar.vue';
import ConvenioFormModal from '../components/ConvenioFormModal.vue';

const auth = useAuthStore();

const colunas = statusConvenio.map((s) => ({ status: s.status, titulo: s.titulo, barra: s.barra }));

const convenios = ref([]);
const carregando = ref(true);
const erro = ref('');
const busca = ref('');
const arrastando = ref(null);
const sobreColuna = ref(null);
const criando = ref(false);

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

function soltar(status) {
    const convenio = arrastando.value;
    finalizarArraste();

    return moverPara(convenio, status);
}

// Usado pelo arrastar e soltar (mouse) e pelo seletor "Mover para…" (toque/teclado).
async function moverPara(convenio, status) {
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

function convenioCriado() {
    criando.value = false;
    carregar();
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
            <div class="flex w-full items-center gap-3 sm:w-auto">
            <input
                v-model="busca"
                type="search"
                placeholder="Buscar por número, objeto ou órgão"
                class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm sm:w-72 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 focus:outline-none"
            >
            <BotaoExportar
                rotulo="Exportar carteira"
                :opcoes="[
                    { rotulo: 'Planilha (CSV)', path: '/convenios/exportar', params: { formato: 'csv' } },
                    { rotulo: 'Documento (PDF)', path: '/convenios/exportar', params: { formato: 'pdf' } },
                ]"
                class="shrink-0"
                @erro="erro = $event"
            />
            <button
                v-if="auth.podeEditar"
                class="shrink-0 rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800"
                @click="criando = true"
            >
                Novo convênio
            </button>
            </div>
        </div>

        <div v-if="erro" role="alert" class="mt-4 flex items-start justify-between rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            <span>{{ erro }}</span>
            <button class="ml-4 font-medium underline" @click="erro = ''">Fechar</button>
        </div>

        <p v-if="carregando" class="mt-8 text-sm text-slate-500">Carregando convênios…</p>

        <div v-else class="mt-4 flex snap-x snap-mandatory gap-4 overflow-x-auto pb-4">
            <section
                v-for="coluna in colunas"
                :key="coluna.status"
                class="min-w-[85%] flex-1 snap-start rounded-lg bg-slate-100 transition-colors sm:min-w-60"
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
                            <h3 class="text-sm font-semibold">
                                <RouterLink :to="{ name: 'convenio', params: { id: c.id } }" class="hover:text-blue-700 hover:underline" draggable="false">
                                    {{ c.numero_convenio }}
                                </RouterLink>
                            </h3>
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

                        <select
                            v-if="auth.podeEditar"
                            :value="c.status"
                            aria-label="Mover para etapa"
                            class="mt-3 w-full rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-600"
                            @change="moverPara(c, $event.target.value)"
                        >
                            <option v-for="col in colunas" :key="col.status" :value="col.status">
                                {{ col.status === c.status ? 'Etapa: ' : 'Mover para: ' }}{{ col.titulo }}
                            </option>
                        </select>
                    </article>

                    <p v-if="!porColuna[coluna.status].length" class="px-2 py-4 text-center text-xs text-slate-400">
                        Nenhum convênio
                    </p>
                </div>
            </section>
        </div>

        <ConvenioFormModal v-if="criando" @salvo="convenioCriado" @fechar="criando = false" />
    </div>
</template>
