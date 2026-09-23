// Ordem do fluxo de um convênio; as cores são strings completas para o Tailwind enxergá-las.
export const statusConvenio = [
    { status: 'proposta', titulo: 'Proposta', barra: 'bg-slate-400' },
    { status: 'em_analise', titulo: 'Em Análise', barra: 'bg-amber-400' },
    { status: 'aprovado', titulo: 'Aprovado', barra: 'bg-blue-500' },
    { status: 'em_execucao', titulo: 'Em Execução', barra: 'bg-indigo-500' },
    { status: 'prestacao_contas', titulo: 'Prestação de Contas', barra: 'bg-purple-500' },
    { status: 'finalizado', titulo: 'Finalizado', barra: 'bg-green-500' },
];

export const statusContrato = [
    { valor: 'nao_iniciado', titulo: 'Não Iniciado' },
    { valor: 'em_andamento', titulo: 'Em Andamento' },
    { valor: 'paralisado', titulo: 'Paralisado' },
    { valor: 'concluido', titulo: 'Concluído' },
];
