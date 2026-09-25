<script setup>
import QRCode from 'qrcode';
import { computed } from 'vue';

// O QR Code é desenhado aqui, no navegador (SVG): o segredo do 2FA nunca é enviado a um serviço de terceiros
// que gere a imagem, e a página não precisa liberar imagens externas na política de segurança de conteúdo.
const props = defineProps({
    texto: { type: String, required: true },
    rotulo: { type: String, default: 'QR Code para o app autenticador' },
});

const desenho = computed(() => {
    const { size, data } = QRCode.create(props.texto, { errorCorrectionLevel: 'M' }).modules;
    let caminho = '';

    for (let linha = 0; linha < size; linha++) {
        for (let coluna = 0; coluna < size; coluna++) {
            if (data[linha * size + coluna]) {
                caminho += `M${coluna} ${linha}h1v1h-1z`;
            }
        }
    }

    return { tamanho: size, caminho };
});
</script>

<template>
    <!-- Margem branca de 2 módulos ao redor: os leitores precisam dela, inclusive no tema escuro. -->
    <svg
        :viewBox="`-2 -2 ${desenho.tamanho + 4} ${desenho.tamanho + 4}`"
        role="img"
        :aria-label="rotulo"
        shape-rendering="crispEdges"
        class="size-52 rounded-md border border-slate-200 bg-white"
    >
        <rect :x="-2" :y="-2" :width="desenho.tamanho + 4" :height="desenho.tamanho + 4" fill="#fff" />
        <path :d="desenho.caminho" fill="#000" />
    </svg>
</template>
