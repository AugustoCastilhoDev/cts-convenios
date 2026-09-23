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
