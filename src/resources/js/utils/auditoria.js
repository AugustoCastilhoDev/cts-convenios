export const tiposRegistro = [
    { valor: 'convenio', titulo: 'Convênio' },
    { valor: 'contrato', titulo: 'Contrato' },
    { valor: 'arquivo', titulo: 'Documento' },
    { valor: 'prefeitura', titulo: 'Prefeitura' },
    { valor: 'usuario', titulo: 'Usuário' },
];

export const eventos = [
    { valor: 'created', titulo: 'Criado' },
    { valor: 'updated', titulo: 'Alterado' },
    { valor: 'deleted', titulo: 'Excluído' },
    { valor: 'restored', titulo: 'Restaurado' },
];

const rotulosCampos = {
    numero_convenio: 'Número',
    orgao_concedente: 'Órgão concedente',
    objeto: 'Objeto',
    secretaria: 'Secretaria',
    valor_repasse: 'Repasse',
    valor_contrapartida: 'Contrapartida',
    status: 'Etapa',
    data_assinatura: 'Assinatura',
    data_vigencia_fim: 'Fim da vigência',
    prazo_prestacao_contas: 'Prestação de contas',
    numero_contrato: 'Nº do contrato',
    empresa_contratada: 'Empresa',
    valor_contratado: 'Valor contratado',
    status_execucao: 'Execução',
    tipo_documento: 'Tipo do documento',
    nome_original: 'Arquivo',
    tamanho_bytes: 'Tamanho (bytes)',
    razao_social: 'Razão social',
    cnpj: 'CNPJ',
    active: 'Ativo',
    name: 'Nome',
    email: 'E-mail',
    role: 'Papel',
    tenant_id: 'Prefeitura',
    deleted_at: 'Exclusão',
};

export function rotuloDoCampo(campo) {
    return rotulosCampos[campo] ?? campo;
}

export function valorLegivel(valor) {
    if (valor === null || valor === undefined || valor === '') {
        return '—';
    }

    if (typeof valor === 'boolean') {
        return valor ? 'sim' : 'não';
    }

    return typeof valor === 'object' ? JSON.stringify(valor) : String(valor);
}

export function tituloDoTipo(tipo) {
    return tiposRegistro.find((t) => t.valor === tipo)?.titulo ?? tipo;
}

export function tituloDoEvento(evento) {
    return eventos.find((e) => e.valor === evento)?.titulo ?? evento;
}
