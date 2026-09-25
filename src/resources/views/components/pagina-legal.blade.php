@props(['titulo', 'descricao', 'caminho'])
<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth motion-reduce:scroll-auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} — CTS Convênios</title>
    <meta name="description" content="{{ $descricao }}">
    <link rel="canonical" href="{{ url($caminho) }}">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <meta name="theme-color" content="#0a2a33">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-slate-900 antialiased">
    <header class="bg-petroleo text-white">
        <div class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <a href="/" aria-label="CTS Convênios — página inicial"><x-logo-cts class="h-10 w-auto" /></a>
            <a href="/app/login" class="rounded-md px-3 py-2 text-sm font-medium hover:bg-white/10">Entrar</a>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-12 sm:px-6 sm:py-16">
        @unless (config('empresa.texto_revisado'))
            <p role="note" class="mb-8 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                <strong>Minuta em revisão.</strong> Este texto ainda não passou pela revisão jurídica final e pode mudar antes da contratação.
            </p>
        @endunless

        <h1 class="text-3xl font-semibold tracking-tight text-balance text-petroleo sm:text-4xl">{{ $titulo }}</h1>
        <p class="mt-2 text-sm text-slate-500">Última atualização: 25 de setembro de 2026</p>

        <div class="mt-8 leading-7 text-slate-700 [&_a]:text-brand-700 [&_a]:underline [&_h2]:mt-10 [&_h2]:mb-3 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-petroleo [&_li]:mt-2 [&_ol]:mt-3 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:mt-3 [&_strong]:text-slate-900 [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:pl-6">
            {{ $slot }}
        </div>
    </main>

    <footer class="bg-petroleo-claro px-4 py-8 text-sm text-slate-300 sm:px-6">
        <div class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-4">
            <p>© {{ date('Y') }} {{ config('empresa.razao_social') }}</p>
            <nav class="flex gap-5" aria-label="Documentos">
                <a href="/privacidade" class="hover:text-white hover:underline">Privacidade</a>
                <a href="/termos" class="hover:text-white hover:underline">Termos de uso</a>
                <a href="/" class="hover:text-white hover:underline">Início</a>
            </nav>
        </div>
    </footer>
</body>
</html>
