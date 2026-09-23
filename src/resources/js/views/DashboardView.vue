<script setup>
import { computed, onMounted, ref } from 'vue';
import { api } from '../services/api';
import { formatarMoeda, formatarData, situacaoPrazo } from '../utils/format';
import { statusConvenio } from '../utils/status';

const dados = ref(null);
const carregando = ref(true);
const erro = ref('');

// A barra de cada etapa usa a mesma cor das colunas do Kanban.
const corDaEtapa = Object.fromEntries(statusConvenio.map((s) => [s.status, s.barra]));

const maiorQuantidade = computed(() => Math.max(1, ...(dados.value?.por_etapa ?? []).map((e) => e.quantidade)));
const totalContratos = computed(() => (dados.value?.contratos_por_execucao ?? []).reduce((soma, c) => soma + c.quantidade, 0));

const corDaExecucao = {
    nao_iniciado: 'bg-slate-400',
    em_andamento: 'bg-blue-500',
    paralisado: 'bg-red-500',
    concluido: 'bg-green-500',
};

async function carregar() {
    try {
        dados.value = (await api.get('/dashboard')).data;
    } catch (e) {
        erro.value = e.message;
    } finally {
        carregando.value = false;
    }
}

onMounted(carregar);
</script>

<template>
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-petroleo">Painel</h1>
        <p class="text-sm text-slate-500">Situação financeira e prazos dos convênios em andamento.</p>

        <p v-if="carregando" class="mt-8 text-sm text-slate-500">Carregando indicadores…</p>
        <div v-else-if="erro" role="alert" class="mt-6 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ erro }}</div>

        <template v-else>
            <div
                v-if="dados.resumo.convenios_com_excesso_contratado > 0"
                role="alert"
                class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
            >
                <strong>{{ dados.resumo.convenios_com_excesso_contratado }}</strong>
                {{ dados.resumo.convenios_com_excesso_contratado === 1 ? 'convênio tem' : 'convênios têm' }}
                contratos somando mais do que o valor disponível (saldo negativo).
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="cartao p-5">
                    <dt class="text-sm text-slate-500">Convênios em andamento</dt>
                    <dd class="mt-2 text-3xl font-semibold tracking-tight">{{ dados.resumo.convenios_ativos }}</dd>
                </div>
                <div class="cartao p-5">
                    <dt class="text-sm text-slate-500">Valor da carteira</dt>
                    <dd class="mt-2 text-3xl font-semibold tracking-tight">{{ formatarMoeda(dados.resumo.valor_total) }}</dd>
                    <p class="mt-1 text-xs text-slate-500">repasse + contrapartida</p>
                </div>
                <div class="cartao p-5">
                    <dt class="text-sm text-slate-500">Contratado</dt>
                    <dd class="mt-2 text-3xl font-semibold tracking-tight">{{ formatarMoeda(dados.resumo.total_contratado) }}</dd>
                    <div
                        class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100"
                        role="img"
                        :aria-label="`${dados.resumo.percentual_contratado}% do valor já contratado`"
                    >
                        <div class="h-full rounded-full bg-brand-500" :style="{ width: Math.min(100, dados.resumo.percentual_contratado) + '%' }" />
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ dados.resumo.percentual_contratado }}% da carteira</p>
                </div>
                <!-- O saldo é o número que o gestor mais consulta: único cartão escuro do painel. -->
                <div class="rounded-lg bg-petroleo p-5 text-white shadow-cartao-alto ring-1 ring-white/10">
                    <dt class="text-sm text-slate-300">Saldo disponível</dt>
                    <dd class="mt-2 text-3xl font-semibold tracking-tight" :class="dados.resumo.saldo_disponivel < 0 ? 'text-red-300' : 'text-menta'">
                        {{ formatarMoeda(dados.resumo.saldo_disponivel) }}
                    </dd>
                    <p class="mt-1 text-xs text-slate-400">carteira menos o contratado</p>
                </div>
            </dl>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <section class="cartao p-5">
                    <h2 class="font-semibold">Convênios por etapa</h2>
                    <ul class="mt-4 space-y-3">
                        <li v-for="etapa in dados.por_etapa" :key="etapa.status">
                            <div class="flex items-baseline justify-between text-sm">
                                <span>{{ etapa.label }}</span>
                                <span class="text-slate-500">
                                    <span class="font-medium text-slate-900">{{ etapa.quantidade }}</span>
                                    · {{ formatarMoeda(etapa.valor_total) }}
                                </span>
                            </div>
                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full"
                                    :class="corDaEtapa[etapa.status]"
                                    :style="{ width: (etapa.quantidade / maiorQuantidade) * 100 + '%' }"
                                />
                            </div>
                        </li>
                    </ul>
                </section>

                <section class="cartao p-5">
                    <h2 class="font-semibold">Prazos críticos</h2>
                    <p class="text-xs text-slate-500">Vencidos e com vencimento nos próximos 90 dias.</p>

                    <ul class="mt-3 divide-y divide-slate-100">
                        <li v-for="p in dados.prazos_criticos" :key="p.convenio_id + p.tipo_prazo" class="flex items-center justify-between gap-3 py-2 text-sm">
                            <div class="min-w-0">
                                <RouterLink :to="{ name: 'convenio', params: { id: p.convenio_id } }" class="font-medium hover:text-brand-700 hover:underline">
                                    {{ p.numero_convenio }}
                                </RouterLink>
                                <p class="truncate text-xs text-slate-500">{{ p.tipo_prazo_label }} · {{ formatarData(p.data_prazo) }}</p>
                            </div>
                            <span class="shrink-0 rounded px-1.5 py-0.5 text-xs font-medium" :class="situacaoPrazo(p.dias).classes">
                                {{ situacaoPrazo(p.dias).texto }}
                            </span>
                        </li>
                        <li v-if="!dados.prazos_criticos.length" class="py-6 text-center text-sm text-slate-400">
                            Nenhum prazo crítico. Tudo em dia.
                        </li>
                    </ul>
                </section>
            </div>

            <section class="mt-6 cartao p-4">
                <h2 class="font-semibold">Contratos por situação de execução</h2>

                <p v-if="!totalContratos" class="mt-3 text-sm text-slate-400">Nenhum contrato vinculado ainda.</p>
                <template v-else>
                    <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-slate-100" role="img" aria-label="Distribuição dos contratos por situação de execução">
                        <div
                            v-for="c in dados.contratos_por_execucao"
                            :key="c.status"
                            :class="corDaExecucao[c.status]"
                            :style="{ width: (c.quantidade / totalContratos) * 100 + '%' }"
                        />
                    </div>
                    <ul class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                        <li v-for="c in dados.contratos_por_execucao" :key="c.status" class="flex items-center gap-2">
                            <span class="size-2.5 rounded-full" :class="corDaExecucao[c.status]" />
                            {{ c.label }}: <span class="font-medium">{{ c.quantidade }}</span>
                        </li>
                    </ul>
                </template>
            </section>
        </template>
    </div>
</template>
