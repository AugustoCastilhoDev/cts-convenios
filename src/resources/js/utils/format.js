const moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

export function formatarMoeda(valor) {
    return moeda.format(valor ?? 0);
}

/** Recebe 'YYYY-MM-DD' e devolve 'DD/MM/YYYY' sem passar por Date (evita deslocar o dia por fuso). */
export function formatarData(data) {
    if (!data) {
        return '—';
    }

    const [ano, mes, dia] = data.slice(0, 10).split('-');

    return `${dia}/${mes}/${ano}`;
}

/**
 * Situação do prazo de vigência, alinhada à régua do Motor de Alertas
 * (90/60/30/15 dias): quanto menos dias, mais forte a cor.
 */
export function situacaoPrazo(dias) {
    if (dias === null || dias === undefined) {
        return null;
    }

    if (dias < 0) {
        return { texto: `Vencido há ${Math.abs(dias)} d`, classes: 'bg-red-100 text-red-800' };
    }

    const texto = dias === 0 ? 'Vence hoje' : `${dias} d para vencer`;

    if (dias <= 15) {
        return { texto, classes: 'bg-red-100 text-red-800' };
    }

    if (dias <= 30) {
        return { texto, classes: 'bg-orange-100 text-orange-800' };
    }

    if (dias <= 90) {
        return { texto, classes: 'bg-amber-100 text-amber-800' };
    }

    return { texto, classes: 'bg-slate-100 text-slate-600' };
}

export function formatarTamanho(bytes) {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(0)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} MB`;
}

/**
 * Faixa colorida na lateral do cartão: quanto mais perto do vencimento, mais forte
 * (mesma régua de situacaoPrazo). Convênios finalizados ou sem prazo ficam neutros.
 */
export function faixaDoPrazo(dias, status) {
    if (status === 'finalizado' || dias === null || dias === undefined) {
        return 'border-l-slate-300';
    }

    if (dias <= 15) {
        return 'border-l-red-500';
    }

    if (dias <= 30) {
        return 'border-l-orange-500';
    }

    if (dias <= 90) {
        return 'border-l-amber-400';
    }

    return 'border-l-emerald-500';
}

/** Frase para o sino de alertas: "vence em 15 dias", "venceu há 3 dias"... */
export function textoDoPrazo(dias) {
    if (dias < 0) {
        return `venceu há ${Math.abs(dias)} ${Math.abs(dias) === 1 ? 'dia' : 'dias'}`;
    }

    if (dias === 0) {
        return 'vence hoje';
    }

    return dias === 1 ? 'vence amanhã' : `vence em ${dias} dias`;
}

/** Cor do ponto de urgência (mesma régua de faixaDoPrazo e situacaoPrazo). */
export function corDoPrazo(dias) {
    if (dias <= 15) {
        return 'bg-red-500';
    }

    if (dias <= 30) {
        return 'bg-orange-500';
    }

    return 'bg-amber-400';
}

/** 50 -> "50%", 33.3 -> "33,3%" (no máximo uma casa decimal, vírgula como separador). */
export function formatarPercentual(valor) {
    return `${new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 }).format(valor ?? 0)}%`;
}
