<script setup>
// Ícones de traço (24x24), desenhados com formas simples para não depender de uma biblioteca.
defineProps({ nome: { type: String, required: true } });
</script>

<template>
    <svg
        class="size-5 shrink-0"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.75"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <!-- painel -->
        <template v-if="nome === 'painel'">
            <rect x="3" y="3" width="7" height="9" rx="1.5" />
            <rect x="14" y="3" width="7" height="5" rx="1.5" />
            <rect x="14" y="12" width="7" height="9" rx="1.5" />
            <rect x="3" y="16" width="7" height="5" rx="1.5" />
        </template>
        <!-- convênios (colunas do kanban) -->
        <template v-if="nome === 'convenios'">
            <rect x="3" y="4" width="5" height="16" rx="1.5" />
            <rect x="9.5" y="4" width="5" height="10" rx="1.5" />
            <rect x="16" y="4" width="5" height="13" rx="1.5" />
        </template>
        <!-- prefeituras (prédio) -->
        <template v-if="nome === 'prefeituras'">
            <path d="M4 21V9l8-5 8 5v12" />
            <path d="M2 21h20" />
            <path d="M9 21v-6h6v6" />
            <path d="M9 11h.01M15 11h.01" />
        </template>
        <!-- usuários -->
        <template v-if="nome === 'usuarios'">
            <circle cx="9" cy="8" r="3.5" />
            <path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6" />
            <path d="M16 4.6a3.5 3.5 0 0 1 0 6.8" />
            <path d="M18 14.3c2.2.6 3.5 2.4 3.5 5.2" />
        </template>
        <!-- auditoria (escudo com marca) -->
        <template v-if="nome === 'auditoria'">
            <path d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6l8-3z" />
            <path d="M8.5 12l2.5 2.5 4.5-5" />
        </template>
        <!-- chave (alterar senha) -->
        <template v-if="nome === 'chave'">
            <circle cx="8" cy="15" r="4" />
            <path d="M11 12l9-9M16 7l3 3M14 9l2 2" />
        </template>
        <!-- sino (alertas) -->
        <template v-if="nome === 'sino'">
            <path d="M6 9a6 6 0 0 1 12 0c0 5 2 6.5 2 6.5H4S6 14 6 9z" />
            <path d="M10 19a2 2 0 0 0 4 0" />
        </template>
        <!-- envelope (cobrança/e-mail) -->
        <template v-if="nome === 'envelope'">
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <path d="M3.5 7l8.5 6 8.5-6" />
        </template>
        <!-- sair -->
        <template v-if="nome === 'sair'">
            <path d="M14 4h5a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-5" />
            <path d="M10 8l-4 4 4 4M6 12h11" />
        </template>
        <!-- menu (hambúrguer) -->
        <template v-if="nome === 'menu'">
            <path d="M4 6h16M4 12h16M4 18h16" />
        </template>
        <!-- fechar -->
        <template v-if="nome === 'fechar'">
            <path d="M6 6l12 12M18 6L6 18" />
        </template>
        <!-- recolher/expandir a barra lateral -->
        <template v-if="nome === 'recolher'">
            <rect x="3" y="4" width="18" height="16" rx="2" />
            <path d="M9 4v16" />
        </template>
    </svg>
</template>
