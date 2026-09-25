<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { api } from '../../services/api';
import BotaoExportar from '../../components/BotaoExportar.vue';
import Paginacao from '../../components/Paginacao.vue';
import { formatarDataHora } from '../../utils/format';

const filtros = reactive({ busca: '', situacao: '', de: '', ate: '' });

const pedidos = ref([]);
const meta = ref(null);
const carregando = ref(true);
const erro = ref('');
const pagina = ref(1);
const ocupado = ref(null); // id do pedido com uma ação em andamento

async function carregar() {
    carregando.value = true;
    erro.value = '';

    try {
        const resposta = await api.get('/contatos', { ...filtros, busca: filtros.busca.trim(), page: pagina.value });
        pedidos.value = resposta.data;
        meta.value = resposta.meta;
    } catch (e) {
        erro.value = e.message;
    } finally {
        carregando.value = false;
    }
}

function filtrar() {
    pagina.value = 1;
    carregar();
}

function limpar() {
    Object.assign(filtros, { busca: '', situacao: '', de: '', ate: '' });
    filtrar();
}

function irParaPagina(numero) {
    pagina.value = numero;
    carregar();
}

async function alternarRespondido(pedido) {
    ocupado.value = pedido.id;
    erro.value = '';

    try {
        const resposta = await api.put(`/contatos/${pedido.id}`, { respondido: !pedido.respondido_em });
        Object.assign(pedido, resposta.data);
    } catch (e) {
        erro.value = e.message;
    } finally {
        ocupado.value = null;
    }
}

async function excluir(pedido) {
    const aviso = `Excluir definitivamente o pedido de ${pedido.nome} (${pedido.municipio})? Não há como desfazer.`;

    if (!window.confirm(aviso)) {
        return;
    }

    ocupado.value = pedido.id;
    erro.value = '';

    try {
        await api.delete(`/contatos/${pedido.id}`);
        // Se era o último da página, volta uma página.
        if (pedidos.value.length === 1 && pagina.value > 1) {
            pagina.value -= 1;
        }
        await carregar();
    } catch (e) {
        erro.value = e.message;
    } finally {
        ocupado.value = null;
    }
}

// O arquivo traz exatamente o que os filtros mostram na tela.
const opcoesExportar = computed(() => [{
    rotulo: 'Planilha (CSV)',
    path: '/contatos/exportar',
    params: { ...filtros, busca: filtros.busca.trim() },
}]);

onMounted(carregar);

const campo = 'rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none';
</script>

<template>
    <div>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-petroleo">Pedidos de contato</h1>
                <p class="text-sm text-slate-500">Quem pediu uma demonstração pela página inicial. Os pedidos são apagados sozinhos após o prazo de guarda da Política de Privacidade.</p>
            </div>
            <BotaoExportar rotulo="Exportar" :opcoes="opcoesExportar" @erro="erro = $event" />
        </div>

        <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="filtrar">
            <div>
                <label class="block text-xs text-slate-500" for="f-busca">Buscar</label>
                <input id="f-busca" v-model="filtros.busca" type="search" placeholder="Nome, município ou e-mail" :class="[campo, 'w-64']">
            </div>
            <div>
                <label class="block text-xs text-slate-500" for="f-situacao">Situação</label>
                <select id="f-situacao" v-model="filtros.situacao" :class="campo">
                    <option value="">Todas</option>
                    <option value="pendente">Pendentes</option>
                    <option value="respondido">Respondidos</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500" for="f-de">De</label>
                <input id="f-de" v-model="filtros.de" type="date" :class="campo">
            </div>
            <div>
                <label class="block text-xs text-slate-500" for="f-ate">Até</label>
                <input id="f-ate" v-model="filtros.ate" type="date" :class="campo">
            </div>
            <button type="submit" class="rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">Filtrar</button>
            <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50" @click="limpar">Limpar</button>
        </form>

        <div v-if="erro" role="alert" class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ erro }}</div>

        <ul class="mt-4 space-y-3 transition-opacity" :class="{ 'opacity-60': carregando }" :aria-busy="carregando">
            <li v-for="p in pedidos" :key="p.id" class="cartao p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-semibold">{{ p.nome }}<span v-if="p.cargo" class="font-normal text-slate-500"> · {{ p.cargo }}</span></h2>
                        <p class="text-sm text-slate-600">{{ p.municipio }}</p>
                    </div>
                    <span
                        class="rounded px-2 py-0.5 text-xs font-medium"
                        :class="p.respondido_em ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-900'"
                    >
                        {{ p.respondido_em ? `Respondido em ${formatarDataHora(p.respondido_em)}` : 'Pendente' }}
                    </span>
                </div>

                <p class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm">
                    <a :href="`mailto:${p.email}`" class="text-brand-700 hover:underline">{{ p.email }}</a>
                    <a v-if="p.telefone" :href="`tel:${p.telefone.replace(/[^0-9+]/g, '')}`" class="text-brand-700 hover:underline">{{ p.telefone }}</a>
                </p>

                <p v-if="p.mensagem" class="mt-3 rounded-md bg-slate-50 px-3 py-2 text-sm whitespace-pre-line text-slate-700">{{ p.mensagem }}</p>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-500">
                        Recebido em {{ formatarDataHora(p.created_at) }} · aceite em {{ formatarDataHora(p.aceite_em) }} · IP {{ p.ip ?? '—' }}
                    </p>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            :disabled="ocupado === p.id"
                            class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50 disabled:opacity-60"
                            @click="alternarRespondido(p)"
                        >
                            {{ p.respondido_em ? 'Reabrir' : 'Marcar como respondido' }}
                        </button>
                        <button
                            type="button"
                            :disabled="ocupado === p.id"
                            class="rounded-md border border-red-200 px-3 py-1.5 text-sm text-red-700 hover:bg-red-50 disabled:opacity-60"
                            @click="excluir(p)"
                        >
                            Excluir
                        </button>
                    </div>
                </div>
            </li>
            <li v-if="!pedidos.length && !carregando" class="cartao px-4 py-8 text-center text-sm text-slate-400">
                Nenhum pedido para os filtros escolhidos.
            </li>
        </ul>

        <Paginacao :meta="meta" @pagina="irParaPagina" />
    </div>
</template>
