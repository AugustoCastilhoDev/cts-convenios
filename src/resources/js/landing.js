import '../css/app.css';

// Formulário de contato da página inicial: envia para /api/contato sem recarregar a página.
const formulario = document.getElementById('form-contato');
const status = document.getElementById('contato-status');

function mostrarStatus(texto, sucesso) {
    status.textContent = texto;
    status.className = `rounded-md px-3 py-2 text-sm ${sucesso
        ? 'border border-green-200 bg-green-50 text-green-800'
        : 'border border-red-200 bg-red-50 text-red-700'}`;
}

function limparErros() {
    status.className = 'hidden';
    formulario.querySelectorAll('[data-erro]').forEach((el) => {
        el.textContent = '';
        el.classList.add('hidden');
    });
}

formulario?.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    limparErros();

    const botao = formulario.querySelector('button[type="submit"]');
    const dados = Object.fromEntries(new FormData(formulario));
    dados.aceite = formulario.elements.aceite.checked;

    botao.disabled = true;
    botao.textContent = 'Enviando…';

    try {
        const resposta = await fetch('/api/contato', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(dados),
        });
        const corpo = await resposta.json().catch(() => ({}));

        if (resposta.ok) {
            formulario.reset();
            mostrarStatus(corpo.message ?? 'Recebemos seu contato.', true);
        } else if (resposta.status === 422) {
            Object.entries(corpo.errors ?? {}).forEach(([campo, mensagens]) => {
                const destino = formulario.querySelector(`[data-erro="${campo}"]`);
                if (destino) {
                    destino.textContent = mensagens[0];
                    destino.classList.remove('hidden');
                }
            });
            mostrarStatus('Confira os campos destacados e tente de novo.', false);
        } else if (resposta.status === 429) {
            mostrarStatus('Você já enviou vários pedidos. Tente novamente mais tarde.', false);
        } else {
            mostrarStatus('Não foi possível enviar agora. Tente novamente em instantes.', false);
        }
    } catch {
        mostrarStatus('Sem conexão com o servidor. Verifique sua internet e tente de novo.', false);
    } finally {
        botao.disabled = false;
        botao.textContent = 'Solicitar demonstração';
    }
});
