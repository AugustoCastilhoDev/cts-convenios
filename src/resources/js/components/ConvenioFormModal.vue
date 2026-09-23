<script setup>
import { onMounted, reactive, ref } from 'vue';
import { api, ApiError } from '../services/api';
import { useAuthStore } from '../stores/auth';
import { statusConvenio } from '../utils/status';

// convenio = null cria um novo; com um convênio, edita (a API é PUT com todos os campos).
const props = defineProps({ convenio: { type: Object, default: null } });
const emit = defineEmits(['salvo', 'fechar']);

const auth = useAuthStore();

// Administrador Interno não pertence a uma prefeitura: escolhe em qual criar o convênio.
const precisaEscolherPrefeitura = !props.convenio && auth.user?.role === 'administrador_interno';
const prefeituras = ref([]);

const form = reactive({
    tenant_id: '',
    numero_convenio: props.convenio?.numero_convenio ?? '',
    orgao_concedente: props.convenio?.orgao_concedente ?? '',
    objeto: props.convenio?.objeto ?? '',
    valor_repasse: props.convenio?.valor_repasse ?? 0,
    valor_contrapartida: props.convenio?.valor_contrapartida ?? 0,
    status: props.convenio?.status ?? 'proposta',
    data_assinatura: props.convenio?.data_assinatura ?? '',
    data_vigencia_fim: props.convenio?.data_vigencia_fim ?? '',
    prazo_prestacao_contas: props.convenio?.prazo_prestacao_contas ?? '',
});

onMounted(async () => {
    if (precisaEscolherPrefeitura) {
        prefeituras.value = (await api.get('/tenants')).data;
    }
});

const salvando = ref(false);
const erros = ref({});
const erroGeral = ref('');

async function salvar() {
    salvando.value = true;
    erros.value = {};
    erroGeral.value = '';

    // Datas vazias viram null: a API aceita "nullable", mas rejeita string vazia como data inválida.
    const payload = Object.fromEntries(
        Object.entries(form)
            .filter(([campo]) => campo !== 'tenant_id' || precisaEscolherPrefeitura)
            .map(([campo, valor]) => [campo, valor === '' ? null : valor]),
    );

    try {
        const resposta = props.convenio
            ? await api.put(`/convenios/${props.convenio.id}`, payload)
            : await api.post('/convenios', payload);
        emit('salvo', resposta.data);
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) {
            erros.value = e.errors;
        } else {
            erroGeral.value = e instanceof ApiError && e.status === 403
                ? 'Seu perfil não tem permissão para esta ação.'
                : e.message;
        }
    } finally {
        salvando.value = false;
    }
}

const campo = 'mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 focus:outline-none';
</script>

<template>
    <div class="fixed inset-0 z-20 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4" @mousedown.self="emit('fechar')">
        <form class="my-8 w-full max-w-2xl rounded-lg bg-white p-6 shadow-xl" @submit.prevent="salvar">
            <h2 class="text-lg font-semibold">{{ convenio ? 'Editar convênio' : 'Novo convênio' }}</h2>

            <div v-if="erroGeral" role="alert" class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ erroGeral }}
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div v-if="precisaEscolherPrefeitura" class="sm:col-span-2">
                    <label class="text-sm font-medium" for="prefeitura">Prefeitura</label>
                    <select id="prefeitura" v-model="form.tenant_id" required :class="campo">
                        <option value="" disabled>Selecione a prefeitura</option>
                        <option v-for="p in prefeituras" :key="p.id" :value="p.id">{{ p.razao_social }}</option>
                    </select>
                    <p v-if="erros.tenant_id" class="mt-1 text-xs text-red-600">{{ erros.tenant_id[0] }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium" for="numero">Número do convênio</label>
                    <input id="numero" v-model="form.numero_convenio" required :class="campo">
                    <p v-if="erros.numero_convenio" class="mt-1 text-xs text-red-600">{{ erros.numero_convenio[0] }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium" for="orgao">Órgão concedente</label>
                    <input id="orgao" v-model="form.orgao_concedente" required :class="campo">
                    <p v-if="erros.orgao_concedente" class="mt-1 text-xs text-red-600">{{ erros.orgao_concedente[0] }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="text-sm font-medium" for="objeto">Objeto</label>
                    <textarea id="objeto" v-model="form.objeto" required rows="3" :class="campo" />
                    <p v-if="erros.objeto" class="mt-1 text-xs text-red-600">{{ erros.objeto[0] }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium" for="repasse">Valor do repasse (R$)</label>
                    <input id="repasse" v-model="form.valor_repasse" type="number" min="0" step="0.01" required :class="campo">
                    <p v-if="erros.valor_repasse" class="mt-1 text-xs text-red-600">{{ erros.valor_repasse[0] }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium" for="contrapartida">Contrapartida (R$)</label>
                    <input id="contrapartida" v-model="form.valor_contrapartida" type="number" min="0" step="0.01" required :class="campo">
                    <p v-if="erros.valor_contrapartida" class="mt-1 text-xs text-red-600">{{ erros.valor_contrapartida[0] }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium" for="status">Etapa</label>
                    <select id="status" v-model="form.status" :class="campo">
                        <option v-for="s in statusConvenio" :key="s.status" :value="s.status">{{ s.titulo }}</option>
                    </select>
                    <p v-if="erros.status" class="mt-1 text-xs text-red-600">{{ erros.status[0] }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium" for="assinatura">Data de assinatura</label>
                    <input id="assinatura" v-model="form.data_assinatura" type="date" :class="campo">
                    <p v-if="erros.data_assinatura" class="mt-1 text-xs text-red-600">{{ erros.data_assinatura[0] }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium" for="vigencia">Fim da vigência</label>
                    <input id="vigencia" v-model="form.data_vigencia_fim" type="date" :class="campo">
                    <p v-if="erros.data_vigencia_fim" class="mt-1 text-xs text-red-600">{{ erros.data_vigencia_fim[0] }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium" for="prestacao">Prazo da prestação de contas</label>
                    <input id="prestacao" v-model="form.prazo_prestacao_contas" type="date" :class="campo">
                    <p v-if="erros.prazo_prestacao_contas" class="mt-1 text-xs text-red-600">{{ erros.prazo_prestacao_contas[0] }}</p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50" @click="emit('fechar')">
                    Cancelar
                </button>
                <button type="submit" :disabled="salvando" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:opacity-60">
                    {{ salvando ? 'Salvando…' : 'Salvar' }}
                </button>
            </div>
        </form>
    </div>
</template>
