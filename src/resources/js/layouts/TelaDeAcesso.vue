<script setup>
import LogoCts from '../components/LogoCts.vue';
</script>

<template>
    <!-- Moldura das telas de quem ainda não está usando o sistema: entrar, esqueci a senha, definir nova senha. -->
    <main class="grid min-h-screen bg-canvas lg:grid-cols-[minmax(0,5fr)_minmax(0,4fr)]">
        <section class="hidden flex-col justify-between bg-petroleo p-12 text-white lg:flex">
            <LogoCts class="h-12 w-auto" />

            <div>
                <div class="mb-6 h-1 w-14 rounded-full bg-ouro" />
                <h1 class="max-w-lg text-4xl leading-tight font-semibold tracking-tight">
                    Nenhum prazo de convênio perdido. Nenhuma inadimplência no CADIN.
                </h1>
                <p class="mt-5 max-w-md text-lg text-slate-300">
                    Acompanhe convênios, emendas e contratos da prefeitura e receba alertas por e-mail
                    90, 60, 30 e 15 dias antes de cada vencimento.
                </p>
            </div>

            <div class="text-sm text-slate-400">Castilho Soluções Digitais</div>
        </section>

        <section class="flex items-center justify-center p-6">
            <div class="w-full max-w-sm">
                <!-- No celular o painel escuro some: a marca vem aqui em cima. -->
                <div class="mb-6 flex justify-center rounded-lg bg-petroleo py-4 lg:hidden">
                    <LogoCts class="h-10 w-auto" />
                </div>
                <slot />
            </div>
        </section>
    </main>
</template>
