<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../../services/api';
import Paginacao from '../../components/Paginacao.vue';
import { eventos, rotuloDoCampo, tiposRegistro, tituloDoEvento, tituloDoTipo, valorLegivel } from '../../utils/auditoria';

const route = useRoute();

// Vindo do detalhe de um convênio ("Ver auditoria"), já abre filtrado por ele.
const filtros = reactive({
    tipo: route.query.tipo ?? '',
    registro_id: route.query.registro_id ?? '',
    evento: '',
    de: '',
    ate: '',
});

const registros = ref([]);
const meta = ref(null);
const carregando = ref(true);
const erro = ref('');
const pagina = ref(1);

async function carregar() {
    carregando.value = true;
    erro.value = '';

    try {
        const resposta = await api.get('/audits', { ...filtros, registro_id: filtros.registro_id.trim(), page: pagina.value });
        registros.value = resposta.data;
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
    Object.assign(filtros, { tipo: '', registro_id: '', evento: '', de: '', ate: '' });
    filtrar();
}

function irParaPagina(numero) {
    pagina.value = numero;
    carregar();
}

/** Campos alterados: no "updated" só os que mudaram; no "created"/"deleted" os valores do registro. */
function alteracoes(registro) {
    const novos = registro.valores_novos ?? {};
    const antigos = registro.valores_antigos ?? {};
    const campos = [...new Set([...Object.keys(novos), ...Object.keys(antigos)])].filter((c) => c !== 'id');

    return campos.map((campo) => ({ campo: rotuloDoCampo(campo), antes: antigos[campo], depois: novos[campo] }));
}

function formatarDataHora(iso) {
    return new Date(iso).toLocaleString('pt-BR');
}

onMounted(carregar);

const campo = 'rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 focus:outline-none';
</script>

<template>
    <div>
        <h1 class="text-xl font-semibold">Auditoria</h1>
        <p class="text-sm text-slate-500">Quem alterou o quê, quando e de onde. Somente leitura; senhas nunca são registradas.</p>

        <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="filtrar">
            <div>
                <label class="block text-xs text-slate-500" for="f-tipo">Tipo de registro</label>
                <select id="f-tipo" v-model="filtros.tipo" :class="campo">
                    <option value="">Todos</option>
                    <option v-for="t in tiposRegistro" :key="t.valor" :value="t.valor">{{ t.titulo }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500" for="f-evento">Evento</label>
                <select id="f-evento" v-model="filtros.evento" :class="campo">
                    <option value="">Todos</option>
                    <option v-for="e in eventos" :key="e.valor" :value="e.valor">{{ e.titulo }}</option>
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
            <div>
                <label class="block text-xs text-slate-500" for="f-registro">ID do registro</label>
                <input id="f-registro" v-model="filtros.registro_id" placeholder="opcional" :class="[campo, 'w-64']">
            </div>
            <button type="submit" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">Filtrar</button>
            <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50" @click="limpar">Limpar</button>
        </form>

        <div v-if="erro" role="alert" class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ erro }}</div>

        <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white" :class="{ 'opacity-60': carregando }">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Data e hora</th>
                        <th class="px-4 py-2 font-medium">Usuário</th>
                        <th class="px-4 py-2 font-medium">Evento</th>
                        <th class="px-4 py-2 font-medium">Registro</th>
                        <th class="px-4 py-2 font-medium">Alterações</th>
                        <th class="px-4 py-2 font-medium">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in registros" :key="r.id" class="border-t border-slate-100 align-top">
                        <td class="px-4 py-2 whitespace-nowrap">{{ formatarDataHora(r.created_at) }}</td>
                        <td class="px-4 py-2">
                            <template v-if="r.usuario">
                                <span class="font-medium">{{ r.usuario.name }}</span>
                                <span class="block text-xs text-slate-500">{{ r.usuario.email }}</span>
                            </template>
                            <span v-else class="text-slate-500">Sistema</span>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ tituloDoEvento(r.evento) }}</td>
                        <td class="px-4 py-2">
                            <RouterLink v-if="r.tipo === 'convenio'" :to="{ name: 'convenio', params: { id: r.registro_id } }" class="text-blue-700 hover:underline">
                                {{ tituloDoTipo(r.tipo) }}
                            </RouterLink>
                            <span v-else>{{ tituloDoTipo(r.tipo) }}</span>
                            <span class="block max-w-40 truncate text-xs text-slate-500" :title="r.registro_id">{{ r.registro_id }}</span>
                        </td>
                        <td class="px-4 py-2">
                            <details v-if="alteracoes(r).length">
                                <summary class="cursor-pointer text-blue-700">{{ alteracoes(r).length }} {{ alteracoes(r).length === 1 ? 'campo' : 'campos' }}</summary>
                                <ul class="mt-1 space-y-0.5 text-xs">
                                    <li v-for="a in alteracoes(r)" :key="a.campo">
                                        <span class="font-medium">{{ a.campo }}:</span>
                                        <template v-if="r.evento === 'updated'">
                                            <span class="text-slate-500"> {{ valorLegivel(a.antes) }}</span> →
                                            <span>{{ valorLegivel(a.depois) }}</span>
                                        </template>
                                        <span v-else> {{ valorLegivel(a.depois ?? a.antes) }}</span>
                                    </li>
                                </ul>
                            </details>
                            <span v-else-if="r.evento === 'updated'" class="text-xs text-slate-500">Campos sensíveis (valores omitidos)</span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-slate-500">{{ r.ip ?? '—' }}</td>
                    </tr>
                    <tr v-if="!registros.length && !carregando">
                        <td colspan="6" class="px-4 py-6 text-center text-slate-400">Nenhum registro para os filtros escolhidos</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Paginacao :meta="meta" @pagina="irParaPagina" />
    </div>
</template>
