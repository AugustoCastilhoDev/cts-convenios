<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api, ApiError } from '../services/api';
import { useAuthStore } from '../stores/auth';
import { formatarMoeda, formatarData, situacaoPrazo } from '../utils/format';
import { statusContrato } from '../utils/status';
import ArquivosConvenio from '../components/ArquivosConvenio.vue';
import BotaoExportar from '../components/BotaoExportar.vue';
import ConvenioFormModal from '../components/ConvenioFormModal.vue';

const route = useRoute();
const auth = useAuthStore();

const convenio = ref(null);
const alertas = ref([]);
const carregando = ref(true);
const erro = ref('');
const editando = ref(false);

const novoContrato = reactive({ numero_contrato: '', empresa_contratada: '', valor_contratado: '', status_execucao: 'nao_iniciado' });
const errosContrato = ref({});
const salvandoContrato = ref(false);

const situacaoAlerta = {
    enviado: 'bg-green-100 text-green-800',
    pendente: 'bg-amber-100 text-amber-800',
    cancelado: 'bg-slate-100 text-slate-600',
};

async function carregar() {
    // Só a primeira carga esconde a página; recargas (após salvar) atualizam por cima.
    if (!convenio.value) {
        carregando.value = true;
    }
    erro.value = '';

    try {
        const [dadosConvenio, dadosAlertas] = await Promise.all([
            api.get(`/convenios/${route.params.id}`),
            api.get(`/convenios/${route.params.id}/alertas`),
        ]);
        convenio.value = dadosConvenio.data;
        alertas.value = dadosAlertas.data;
    } catch (e) {
        erro.value = e instanceof ApiError && e.status === 404
            ? 'Convênio não encontrado.'
            : e.message;
    } finally {
        carregando.value = false;
    }
}

async function adicionarContrato() {
    salvandoContrato.value = true;
    errosContrato.value = {};

    try {
        await api.post(`/convenios/${convenio.value.id}/contratos`, novoContrato);
        Object.assign(novoContrato, { numero_contrato: '', empresa_contratada: '', valor_contratado: '', status_execucao: 'nao_iniciado' });
        // Recarrega: o saldo disponível é calculado no servidor.
        await carregar();
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) {
            errosContrato.value = e.errors;
        } else {
            erro.value = e.message;
        }
    } finally {
        salvandoContrato.value = false;
    }
}

// Edição de um contrato já cadastrado, na própria linha da tabela.
const contratoEditandoId = ref(null);
const edicao = reactive({ numero_contrato: '', empresa_contratada: '', valor_contratado: '', status_execucao: 'nao_iniciado' });
const errosEdicao = ref({});
const salvandoEdicao = ref(false);

function iniciarEdicao(contrato) {
    contratoEditandoId.value = contrato.id;
    errosEdicao.value = {};
    Object.assign(edicao, {
        numero_contrato: contrato.numero_contrato,
        empresa_contratada: contrato.empresa_contratada,
        valor_contratado: contrato.valor_contratado,
        status_execucao: contrato.status_execucao,
    });
}

async function salvarEdicao() {
    salvandoEdicao.value = true;
    errosEdicao.value = {};

    try {
        await api.put(`/convenios/${convenio.value.id}/contratos/${contratoEditandoId.value}`, edicao);
        contratoEditandoId.value = null;
        await carregar();
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) {
            errosEdicao.value = e.errors;
        } else {
            erro.value = e.message;
        }
    } finally {
        salvandoEdicao.value = false;
    }
}

function convenioSalvo() {
    editando.value = false;
    carregar();
}

onMounted(carregar);

const campo = 'mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 focus:outline-none';
</script>

<template>
    <div>
        <RouterLink :to="{ name: 'kanban' }" class="text-sm text-blue-700 hover:underline">← Voltar aos convênios</RouterLink>

        <p v-if="carregando" class="mt-6 text-sm text-slate-500">Carregando…</p>
        <div v-else-if="erro && !convenio" role="alert" class="mt-6 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ erro }}
        </div>

        <template v-else-if="convenio">
            <div v-if="erro" role="alert" class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ erro }}</div>

            <header class="mt-3 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Convênio {{ convenio.numero_convenio }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ convenio.orgao_concedente }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="rounded-full bg-slate-200 px-3 py-1 text-sm font-medium">{{ convenio.status_label }}</span>
                    <BotaoExportar
                        rotulo="Relatório"
                        :opcoes="[{ rotulo: 'Ficha completa (PDF)', path: `/convenios/${convenio.id}/ficha` }]"
                        @erro="erro = $event"
                    />
                    <button
                        v-if="auth.podeEditar"
                        class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50"
                        @click="editando = true"
                    >
                        Editar
                    </button>
                </div>
            </header>

            <p class="mt-4 max-w-3xl text-slate-700">{{ convenio.objeto }}</p>

            <dl class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <dt class="text-xs text-slate-500">Repasse</dt>
                    <dd class="mt-1 text-lg font-semibold">{{ formatarMoeda(convenio.valor_repasse) }}</dd>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <dt class="text-xs text-slate-500">Contrapartida</dt>
                    <dd class="mt-1 text-lg font-semibold">{{ formatarMoeda(convenio.valor_contrapartida) }}</dd>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <dt class="text-xs text-slate-500">Contratado</dt>
                    <dd class="mt-1 text-lg font-semibold">{{ formatarMoeda(convenio.total_contratado) }}</dd>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <dt class="text-xs text-slate-500">Saldo disponível</dt>
                    <dd class="mt-1 text-lg font-semibold" :class="convenio.saldo_disponivel < 0 ? 'text-red-700' : 'text-green-700'">
                        {{ formatarMoeda(convenio.saldo_disponivel) }}
                    </dd>
                </div>
            </dl>

            <section class="mt-6 rounded-lg border border-slate-200 bg-white p-4">
                <h2 class="font-semibold">Prazos</h2>
                <dl class="mt-3 grid gap-4 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-slate-500">Assinatura</dt>
                        <dd class="font-medium">{{ formatarData(convenio.data_assinatura) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Fim da vigência</dt>
                        <dd class="font-medium">
                            {{ formatarData(convenio.data_vigencia_fim) }}
                            <span
                                v-if="convenio.status !== 'finalizado' && situacaoPrazo(convenio.dias_para_vencimento)"
                                class="ml-2 rounded px-1.5 py-0.5 text-xs"
                                :class="situacaoPrazo(convenio.dias_para_vencimento).classes"
                            >
                                {{ situacaoPrazo(convenio.dias_para_vencimento).texto }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Prestação de contas</dt>
                        <dd class="font-medium">{{ formatarData(convenio.prazo_prestacao_contas) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="mt-6 rounded-lg border border-slate-200 bg-white p-4">
                <h2 class="font-semibold">Contratos vinculados</h2>

                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs text-slate-500">
                            <tr>
                                <th class="py-2 pr-4 font-medium">Contrato</th>
                                <th class="py-2 pr-4 font-medium">Empresa</th>
                                <th class="py-2 pr-4 text-right font-medium">Valor</th>
                                <th class="py-2 pr-4 font-medium">Execução</th>
                                <th class="py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="c in convenio.contratos_vinculados" :key="c.id">
                                <tr v-if="contratoEditandoId !== c.id" class="border-t border-slate-100">
                                    <td class="py-2 pr-4 font-medium">{{ c.numero_contrato }}</td>
                                    <td class="py-2 pr-4">{{ c.empresa_contratada }}</td>
                                    <td class="py-2 pr-4 text-right">{{ formatarMoeda(c.valor_contratado) }}</td>
                                    <td class="py-2 pr-4">{{ c.status_execucao_label }}</td>
                                    <td class="py-2 text-right">
                                        <button v-if="auth.podeEditar" class="text-blue-700 hover:underline" @click="iniciarEdicao(c)">Editar</button>
                                    </td>
                                </tr>
                                <tr v-else class="border-t border-slate-100 bg-slate-50 align-top">
                                    <td class="p-2">
                                        <input v-model="edicao.numero_contrato" :class="campo">
                                        <p v-if="errosEdicao.numero_contrato" class="mt-1 text-xs text-red-600">{{ errosEdicao.numero_contrato[0] }}</p>
                                    </td>
                                    <td class="p-2">
                                        <input v-model="edicao.empresa_contratada" :class="campo">
                                        <p v-if="errosEdicao.empresa_contratada" class="mt-1 text-xs text-red-600">{{ errosEdicao.empresa_contratada[0] }}</p>
                                    </td>
                                    <td class="p-2">
                                        <input v-model="edicao.valor_contratado" type="number" min="0" step="0.01" :class="campo">
                                        <p v-if="errosEdicao.valor_contratado" class="mt-1 text-xs text-red-600">{{ errosEdicao.valor_contratado[0] }}</p>
                                    </td>
                                    <td class="p-2">
                                        <select v-model="edicao.status_execucao" :class="campo">
                                            <option v-for="st in statusContrato" :key="st.valor" :value="st.valor">{{ st.titulo }}</option>
                                        </select>
                                    </td>
                                    <td class="p-2 text-right whitespace-nowrap">
                                        <button :disabled="salvandoEdicao" class="mr-3 font-medium text-blue-700 hover:underline disabled:opacity-60" @click="salvarEdicao">Salvar</button>
                                        <button class="text-slate-600 hover:underline" @click="contratoEditandoId = null">Cancelar</button>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="!convenio.contratos_vinculados?.length">
                                <td colspan="5" class="py-4 text-center text-slate-400">Nenhum contrato vinculado</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form v-if="auth.podeEditar" class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="adicionarContrato">
                    <div>
                        <input v-model="novoContrato.numero_contrato" placeholder="Nº do contrato" required :class="campo">
                        <p v-if="errosContrato.numero_contrato" class="mt-1 text-xs text-red-600">{{ errosContrato.numero_contrato[0] }}</p>
                    </div>
                    <div>
                        <input v-model="novoContrato.empresa_contratada" placeholder="Empresa contratada" required :class="campo">
                        <p v-if="errosContrato.empresa_contratada" class="mt-1 text-xs text-red-600">{{ errosContrato.empresa_contratada[0] }}</p>
                    </div>
                    <div>
                        <input v-model="novoContrato.valor_contratado" type="number" min="0" step="0.01" placeholder="Valor (R$)" required :class="campo">
                        <p v-if="errosContrato.valor_contratado" class="mt-1 text-xs text-red-600">{{ errosContrato.valor_contratado[0] }}</p>
                    </div>
                    <select v-model="novoContrato.status_execucao" :class="campo">
                        <option v-for="s in statusContrato" :key="s.valor" :value="s.valor">{{ s.titulo }}</option>
                    </select>
                    <button
                        type="submit"
                        :disabled="salvandoContrato"
                        class="mt-1 rounded-md bg-blue-700 px-3 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:opacity-60"
                    >
                        Adicionar contrato
                    </button>
                </form>
            </section>

            <ArquivosConvenio :convenio-id="convenio.id" />

            <section class="mt-6 rounded-lg border border-slate-200 bg-white p-4">
                <h2 class="font-semibold">Histórico de alertas</h2>
                <ul class="mt-3 divide-y divide-slate-100 text-sm">
                    <li v-for="a in alertas" :key="a.id" class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <span>
                            {{ a.tipo_prazo_label }} —
                            {{ a.marco === 'vencido' ? 'prazo vencido' : `${a.marco} dias antes` }}
                            <span class="text-slate-500">(prazo {{ formatarData(a.data_prazo) }})</span>
                        </span>
                        <span class="flex items-center gap-2">
                            <span v-if="a.enviado_em" class="text-xs text-slate-500">{{ new Date(a.enviado_em).toLocaleString('pt-BR') }}</span>
                            <span class="rounded px-1.5 py-0.5 text-xs font-medium capitalize" :class="situacaoAlerta[a.situacao]">{{ a.situacao }}</span>
                        </span>
                    </li>
                    <li v-if="!alertas.length" class="py-4 text-center text-slate-400">Nenhum alerta gerado ainda</li>
                </ul>
            </section>

            <ConvenioFormModal v-if="editando" :convenio="convenio" @salvo="convenioSalvo" @fechar="editando = false" />
        </template>
    </div>
</template>
