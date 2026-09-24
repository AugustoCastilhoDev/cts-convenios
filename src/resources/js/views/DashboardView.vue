<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../services/api';
import CobrancaSimuladaModal from '../components/CobrancaSimuladaModal.vue';
import Icone from '../components/Icone.vue';
import SeloAdimplencia from '../components/SeloAdimplencia.vue';
import { useAuthStore } from '../stores/auth';
import { formatarMoeda, formatarData, situacaoPrazo } from '../utils/format';
import { SEM_SECRETARIA, secretarias } from '../utils/secretaria';
import { statusConvenio } from '../utils/status';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const dados = ref(null);
const carregando = ref(true); // primeira carga: a página ainda não tem nada para mostrar
const atualizando = ref(false); // troca de filtro: mantém os números antigos na tela, esmaecidos
const erro = ref('');
const cobrancaAlvo = ref(null);

// O filtro fica na URL (?secretaria=saude): atualizar a página ou compartilhar o link mantém a visão.
const filtro = ref(String(route.query.secretaria ?? ''));

// A barra de cada etapa usa a mesma cor das colunas do Kanban.
const corDaEtapa = Object.fromEntries(statusConvenio.map((s) => [s.status, s.barra]));

const maiorQuantidade = computed(() => Math.max(1, ...(dados.value?.por_etapa ?? []).map((e) => e.quantidade)));
const totalContratos = computed(() => (dados.value?.contratos_por_execucao ?? []).reduce((soma, c) => soma + c.quantidade, 0));
const semConvenios = computed(() => dados.value && dados.value.resumo.convenios_ativos === 0 && dados.value.por_etapa.every((e) => e.quantidade === 0));

const corDaExecucao = {
    nao_iniciado: 'bg-slate-400',
    em_andamento: 'bg-blue-500',
    paralisado: 'bg-red-500',
    concluido: 'bg-green-500',
};

// Se a pessoa troca o filtro rápido, só a resposta da última consulta vale (as antigas podem chegar depois).
let consultaAtual = 0;

async function carregar() {
    const numero = ++consultaAtual;
    atualizando.value = dados.value !== null;
    erro.value = '';

    try {
        const resposta = await api.get('/dashboard', { secretaria: filtro.value });

        if (numero === consultaAtual) {
            dados.value = resposta.data;
        }
    } catch (e) {
        if (numero === consultaAtual) {
            erro.value = e.message;
        }
    } finally {
        if (numero === consultaAtual) {
            carregando.value = false;
            atualizando.value = false;
        }
    }
}

watch(filtro, (valor) => {
    router.replace({ query: valor ? { secretaria: valor } : {} });
    carregar();
});

onMounted(carregar);

const nomeDoFiltro = computed(() => {
    if (filtro.value === SEM_SECRETARIA) {
        return 'sem secretaria';
    }

    return secretarias.find((s) => s.valor === filtro.value)?.titulo ?? '';
});

const campo = 'rounded-md border border-borda bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none';
</script>

<template>
    <div>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-petroleo">Painel</h1>
                <p class="text-sm text-slate-500">
                    Situação financeira e prazos dos convênios em andamento<template v-if="nomeDoFiltro"> — <strong class="text-slate-700">{{ nomeDoFiltro }}</strong></template>.
                </p>
            </div>

            <div>
                <label for="filtro-secretaria" class="block text-xs text-slate-500">Filtrar por Secretaria</label>
                <select id="filtro-secretaria" v-model="filtro" :class="[campo, 'mt-1 min-w-52']">
                    <option value="">Todas as secretarias</option>
                    <option v-for="s in secretarias" :key="s.valor" :value="s.valor">{{ s.titulo }}</option>
                    <option :value="SEM_SECRETARIA">Sem secretaria</option>
                </select>
            </div>
        </div>

        <p v-if="carregando" class="mt-8 text-sm text-slate-500">Carregando indicadores…</p>
        <div v-else-if="erro && !dados" role="alert" class="mt-6 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ erro }}</div>

        <template v-else>
            <div v-if="erro" role="alert" class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ erro }}</div>

            <!-- O município inteiro: não muda com o filtro de secretaria -->
            <div class="mt-4">
                <SeloAdimplencia :regularidade="dados.regularidade" />
            </div>

            <div class="transition-opacity" :class="{ 'opacity-50': atualizando }" :aria-busy="atualizando">
                <div
                    v-if="dados.resumo.convenios_com_excesso_contratado > 0"
                    role="alert"
                    class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
                >
                    <strong>{{ dados.resumo.convenios_com_excesso_contratado }}</strong>
                    {{ dados.resumo.convenios_com_excesso_contratado === 1 ? 'convênio tem' : 'convênios têm' }}
                    contratos somando mais do que o valor disponível (saldo negativo).
                </div>

                <p v-if="semConvenios" class="mt-4 rounded-md border border-dashed border-borda px-3 py-2 text-sm text-slate-500">
                    Nenhum convênio {{ nomeDoFiltro ? `em "${nomeDoFiltro}"` : 'cadastrado' }}.
                </p>

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

                    <section id="prazos-criticos" class="cartao scroll-mt-20 p-5">
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
                                <div class="flex shrink-0 items-center gap-2">
                                    <span class="rounded px-1.5 py-0.5 text-xs font-medium" :class="situacaoPrazo(p.dias).classes">
                                        {{ situacaoPrazo(p.dias).texto }}
                                    </span>
                                    <!-- Ação rápida (simulada por enquanto): quem lança e edita convênios cobra o fiscal -->
                                    <button
                                        v-if="auth.podeEditar"
                                        type="button"
                                        class="rounded-md p-1.5 text-slate-500 hover:bg-slate-100 hover:text-brand-700"
                                        :aria-label="`Cobrar o fiscal sobre o convênio ${p.numero_convenio}`"
                                        :title="`Cobrar o fiscal (simulação)`"
                                        @click="cobrancaAlvo = p"
                                    >
                                        <Icone nome="envelope" class="size-4" />
                                    </button>
                                </div>
                            </li>
                            <li v-if="!dados.prazos_criticos.length" class="py-6 text-center text-sm text-slate-400">
                                Nenhum prazo crítico. Tudo em dia.
                            </li>
                        </ul>
                    </section>
                </div>

                <section class="mt-6 cartao p-5">
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
            </div>
        </template>

        <CobrancaSimuladaModal v-if="cobrancaAlvo" :prazo="cobrancaAlvo" @fechar="cobrancaAlvo = null" />
    </div>
</template>
