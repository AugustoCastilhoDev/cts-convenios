<script setup>
import { onMounted, ref } from 'vue';
import { api, ApiError, baixarBlob } from '../services/api';
import { useAuthStore } from '../stores/auth';
import { formatarTamanho } from '../utils/format';

const props = defineProps({ convenioId: { type: String, required: true } });

const auth = useAuthStore();

// Espelha StoreArquivoConvenioRequest: a validação de verdade é a do servidor,
// aqui só evitamos enviar 20 MB para receber uma recusa óbvia.
const EXTENSOES = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'xml'];
const TAMANHO_MAXIMO = 20 * 1024 * 1024;

const tipos = [
    { valor: 'termo_assinatura', titulo: 'Termo de Assinatura' },
    { valor: 'extrato', titulo: 'Extrato' },
    { valor: 'nota_fiscal', titulo: 'Nota Fiscal' },
    { valor: 'outro', titulo: 'Outro' },
];

const arquivos = ref([]);
const paginaAtual = ref(1);
const ultimaPagina = ref(1);
const carregando = ref(true);
const erro = ref('');

const tipoEscolhido = ref('termo_assinatura');
const arquivoEscolhido = ref(null);
const campoArquivo = ref(null);
const enviando = ref(false);
const erroEnvio = ref('');
const baixandoId = ref(null);

async function carregar(pagina = 1) {
    try {
        const resposta = await api.get(`/convenios/${props.convenioId}/arquivos`, { page: pagina });
        arquivos.value = pagina === 1 ? resposta.data : [...arquivos.value, ...resposta.data];
        paginaAtual.value = resposta.meta.current_page;
        ultimaPagina.value = resposta.meta.last_page;
    } catch (e) {
        erro.value = e.message;
    } finally {
        carregando.value = false;
    }
}

function escolherArquivo(evento) {
    erroEnvio.value = '';
    const arquivo = evento.target.files[0] ?? null;

    if (arquivo) {
        const extensao = arquivo.name.split('.').pop().toLowerCase();

        if (!EXTENSOES.includes(extensao)) {
            erroEnvio.value = `Tipo não permitido. Envie: ${EXTENSOES.join(', ')}.`;
            limparSelecao();
            return;
        }

        if (arquivo.size > TAMANHO_MAXIMO) {
            erroEnvio.value = 'O arquivo passa do limite de 20 MB.';
            limparSelecao();
            return;
        }
    }

    arquivoEscolhido.value = arquivo;
}

function limparSelecao() {
    arquivoEscolhido.value = null;
    if (campoArquivo.value) {
        campoArquivo.value.value = '';
    }
}

async function enviar() {
    if (!arquivoEscolhido.value) {
        return;
    }

    enviando.value = true;
    erroEnvio.value = '';

    const formulario = new FormData();
    formulario.append('tipo_documento', tipoEscolhido.value);
    formulario.append('arquivo', arquivoEscolhido.value);

    try {
        await api.post(`/convenios/${props.convenioId}/arquivos`, formulario);
        limparSelecao();
        await carregar(1);
    } catch (e) {
        erroEnvio.value = e instanceof ApiError && e.status === 422
            ? (Object.values(e.errors)[0]?.[0] ?? e.message)
            : e.message;
    } finally {
        enviando.value = false;
    }
}

async function baixar(arquivo) {
    baixandoId.value = arquivo.id;
    erro.value = '';

    try {
        const blob = await baixarBlob(`/convenios/${props.convenioId}/arquivos/${arquivo.id}/download`);
        const endereco = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = endereco;
        link.download = arquivo.nome_original;
        link.click();
        URL.revokeObjectURL(endereco);
    } catch (e) {
        erro.value = `Não foi possível baixar o arquivo: ${e.message}`;
    } finally {
        baixandoId.value = null;
    }
}

async function excluir(arquivo) {
    if (!window.confirm(`Excluir "${arquivo.nome_original}"? Esta ação não pode ser desfeita.`)) {
        return;
    }

    try {
        await api.delete(`/convenios/${props.convenioId}/arquivos/${arquivo.id}`);
        arquivos.value = arquivos.value.filter((a) => a.id !== arquivo.id);
    } catch (e) {
        erro.value = `Não foi possível excluir o arquivo: ${e.message}`;
    }
}

onMounted(() => carregar());

const campo = 'mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 focus:outline-none';
</script>

<template>
    <section class="mt-6 rounded-lg border border-slate-200 bg-white p-4">
        <h2 class="font-semibold">Documentos</h2>

        <div v-if="erro" role="alert" class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ erro }}</div>

        <p v-if="carregando" class="mt-3 text-sm text-slate-500">Carregando documentos…</p>

        <div v-else class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs text-slate-500">
                    <tr>
                        <th class="py-2 pr-4 font-medium">Arquivo</th>
                        <th class="py-2 pr-4 font-medium">Tipo</th>
                        <th class="py-2 pr-4 text-right font-medium">Tamanho</th>
                        <th class="py-2 pr-4 font-medium">Enviado em</th>
                        <th class="py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in arquivos" :key="a.id" class="border-t border-slate-100">
                        <td class="max-w-xs truncate py-2 pr-4 font-medium" :title="a.nome_original">{{ a.nome_original }}</td>
                        <td class="py-2 pr-4">{{ a.tipo_documento_label }}</td>
                        <td class="py-2 pr-4 text-right whitespace-nowrap">{{ formatarTamanho(a.tamanho_bytes) }}</td>
                        <td class="py-2 pr-4 whitespace-nowrap">{{ new Date(a.created_at).toLocaleDateString('pt-BR') }}</td>
                        <td class="py-2 text-right whitespace-nowrap">
                            <button :disabled="baixandoId === a.id" class="mr-3 text-blue-700 hover:underline disabled:opacity-60" @click="baixar(a)">
                                {{ baixandoId === a.id ? 'Baixando…' : 'Baixar' }}
                            </button>
                            <button v-if="auth.podeExcluir" class="text-red-700 hover:underline" @click="excluir(a)">Excluir</button>
                        </td>
                    </tr>
                    <tr v-if="!arquivos.length">
                        <td colspan="5" class="py-4 text-center text-slate-400">Nenhum documento enviado</td>
                    </tr>
                </tbody>
            </table>

            <button v-if="paginaAtual < ultimaPagina" class="mt-3 text-sm text-blue-700 hover:underline" @click="carregar(paginaAtual + 1)">
                Ver mais documentos
            </button>
        </div>

        <form v-if="auth.podeEditar" class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-[14rem_1fr_auto]" @submit.prevent="enviar">
            <div>
                <label class="text-xs text-slate-500" for="tipo-documento">Tipo do documento</label>
                <select id="tipo-documento" v-model="tipoEscolhido" :class="campo">
                    <option v-for="t in tipos" :key="t.valor" :value="t.valor">{{ t.titulo }}</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500" for="arquivo-convenio">Arquivo (PDF, imagem, Word, Excel ou XML — até 20 MB)</label>
                <input
                    id="arquivo-convenio"
                    ref="campoArquivo"
                    type="file"
                    :accept="EXTENSOES.map((e) => '.' + e).join(',')"
                    class="mt-1 block w-full rounded-md border border-slate-300 bg-white text-sm file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm"
                    @change="escolherArquivo"
                >
            </div>
            <button
                type="submit"
                :disabled="!arquivoEscolhido || enviando"
                class="self-end rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:opacity-50"
            >
                {{ enviando ? 'Enviando…' : 'Enviar' }}
            </button>
            <p v-if="erroEnvio" role="alert" class="text-sm text-red-600 sm:col-span-3">{{ erroEnvio }}</p>
        </form>
    </section>
</template>
