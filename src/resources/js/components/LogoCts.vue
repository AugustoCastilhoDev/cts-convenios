<script setup>
import { useId } from 'vue';

/*
 * Logotipo CTS Convênios — SVG puro, sem fontes ou imagens externas.
 *
 * Conceito: um ESCUDO (segurança, a fortaleza do "Castilho") guardando um pequeno RELÓGIO (o prazo,
 * Cronos) sobre TRÊS BARRAS CRESCENTES (o fluxo e a evolução das verbas). O degradê do contorno
 * vai do azul tecnológico (blue-500) ao verde de adimplência (emerald-500), e as barras alternam
 * as duas cores. Pensado para fundo escuro: use classes de altura para dimensionar (h-9, h-11...).
 *
 * O texto herda a fonte da página (Instrument Sans). Há uma cópia deste desenho para a página
 * pública em resources/views/components/logo-cts.blade.php: mudou aqui, mude lá também.
 */
defineProps({
    // Só o ícone (menu recolhido, favicon, avatar).
    compacto: { type: Boolean, default: false },
});

// Cada instância precisa do seu id: um SVG dentro de um elemento oculto (display:none) não
// resolve o degradê, e ids repetidos fariam a segunda cópia usar a do elemento escondido.
const id = `cts-degrade-${useId()}`;
</script>

<template>
    <svg
        :viewBox="compacto ? '0 0 48 48' : '0 0 168 48'"
        class="h-10 w-auto"
        role="img"
        aria-label="CTS Convênios"
        xmlns="http://www.w3.org/2000/svg"
    >
        <defs>
            <linearGradient :id="id" x1="6" y1="4" x2="42" y2="44" gradientUnits="userSpaceOnUse">
                <stop offset="0" style="stop-color: var(--color-blue-500, #3b82f6)" />
                <stop offset="1" style="stop-color: var(--color-emerald-500, #10b981)" />
            </linearGradient>
        </defs>

        <!-- Escudo -->
        <path
            d="M24 3.5 L40.5 9.5 V23 C40.5 33.5 33.5 41.5 24 44.5 C14.5 41.5 7.5 33.5 7.5 23 V9.5 Z"
            class="fill-blue-500/15"
            :stroke="`url(#${id})`"
            stroke-width="2.4"
            stroke-linejoin="round"
        />

        <!-- Relógio: o prazo -->
        <circle cx="24" cy="15.5" r="3.4" class="fill-none stroke-slate-200" stroke-width="1.5" />
        <path d="M24 13.6 V15.5 L25.4 16.4" class="fill-none stroke-slate-200" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" />

        <!-- Barras crescentes: o fluxo financeiro -->
        <rect x="15.2" y="28" width="5" height="8" rx="1.2" class="fill-blue-500" />
        <rect x="21.5" y="24" width="5" height="12" rx="1.2" class="fill-teal-500" />
        <rect x="27.8" y="20.5" width="5" height="15.5" rx="1.2" class="fill-emerald-500" />

        <template v-if="!compacto">
            <text x="54" y="26" font-size="25" font-weight="700" letter-spacing="1" class="fill-white">CTS</text>
            <text x="54.5" y="38.5" font-size="10" font-weight="400" letter-spacing="3.1" class="fill-slate-300">CONVÊNIOS</text>
        </template>
    </svg>
</template>
