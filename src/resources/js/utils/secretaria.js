// Secretarias municipais (espelha App\Enums\Secretaria). Para incluir outra: acrescente o case
// no enum do PHP e uma linha aqui. As classes são strings completas para o Tailwind enxergá-las.
export const secretarias = [
    { valor: 'saude', titulo: 'Saúde', classes: 'bg-emerald-100 text-emerald-800' },
    { valor: 'educacao', titulo: 'Educação', classes: 'bg-orange-100 text-orange-800' },
    { valor: 'obras', titulo: 'Obras', classes: 'bg-blue-100 text-blue-800' },
    { valor: 'administracao', titulo: 'Administração', classes: 'bg-violet-100 text-violet-800' },
    { valor: 'assistencia_social', titulo: 'Assistência Social', classes: 'bg-pink-100 text-pink-800' },
    { valor: 'outra', titulo: 'Outra', classes: 'bg-slate-200 text-slate-700' },
];

/** Valor de filtro para convênios ainda não classificados (o servidor entende "sem_secretaria"). */
export const SEM_SECRETARIA = 'sem_secretaria';

const semSecretaria = { valor: null, titulo: 'Sem secretaria', classes: 'bg-slate-100 text-slate-500' };

/** Título e cores do badge de uma secretaria; convênio sem classificação recebe o cinza neutro. */
export function infoSecretaria(valor) {
    return secretarias.find((s) => s.valor === valor) ?? semSecretaria;
}
