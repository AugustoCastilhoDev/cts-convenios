<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth motion-reduce:scroll-auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CTS Convênios — gestão de convênios e alertas de prazo para prefeituras</title>
    <meta name="description" content="Acompanhe convênios, emendas e contratos da prefeitura e receba alertas por e-mail e no sistema 90, 60, 30 e 15 dias antes de cada vencimento, antes que vire inadimplência no CADIN.">
    <link rel="canonical" href="{{ url('/') }}">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:title" content="CTS Convênios — o prazo do convênio não passa em branco">
    <meta property="og:description" content="Gestão de convênios, emendas e contratos para prefeituras, com alertas antes de cada vencimento.">
    <meta property="og:url" content="{{ url('/') }}">
    <meta name="theme-color" content="#0a2a33">
    @vite(['resources/css/app.css', 'resources/js/landing.js'])
</head>
<body class="bg-white text-slate-900 antialiased">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-md focus:bg-white focus:px-3 focus:py-2 focus:text-petroleo">Ir para o conteúdo</a>

    {{-- Cabeçalho --}}
    <header class="bg-petroleo text-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <a href="/" class="flex items-center gap-3" aria-label="CTS Convênios — página inicial">
                <span class="grid size-9 place-items-center rounded-lg bg-brand-500 text-sm font-bold ring-1 ring-white/20">CTS</span>
                <span class="text-lg font-semibold tracking-tight">CTS Convênios</span>
            </a>
            <nav class="hidden items-center gap-7 text-sm text-slate-300 md:flex" aria-label="Seções da página">
                <a href="#como-funciona" class="hover:text-white">Como funciona</a>
                <a href="#recursos" class="hover:text-white">Recursos</a>
                <a href="#para-quem" class="hover:text-white">Para quem</a>
                <a href="#seguranca" class="hover:text-white">Segurança</a>
            </nav>
            <div class="flex items-center gap-3 text-sm">
                <a href="/app/login" class="rounded-md px-3 py-2 font-medium text-white hover:bg-white/10">Entrar</a>
                <a href="#contato" class="hidden rounded-md bg-ouro px-4 py-2 font-semibold text-petroleo hover:brightness-105 sm:block">Solicitar demonstração</a>
            </div>
        </div>
    </header>

    <main id="conteudo">
        {{-- Abertura --}}
        <section class="overflow-hidden bg-petroleo pb-28 text-white sm:pb-32">
            <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 pt-10 sm:px-6 sm:pt-16 lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
                <div>
                    <div class="mb-6 h-1 w-14 rounded-full bg-ouro"></div>
                    <h1 class="text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-6xl">O prazo do convênio não passa em branco.</h1>
                    <p class="mt-6 max-w-xl text-lg text-slate-300">
                        O CTS Convênios acompanha convênios, emendas e contratos da prefeitura e avisa a equipe por e-mail e no sistema
                        90, 60, 30 e 15 dias antes de cada vencimento — antes que vire inadimplência no CADIN.
                    </p>
                    <div class="mt-9 flex flex-wrap gap-3">
                        <a href="#contato" class="rounded-md bg-ouro px-6 py-3 font-semibold text-petroleo hover:brightness-105">Solicitar demonstração</a>
                        <a href="/app/login" class="rounded-md border border-white/30 px-6 py-3 font-medium hover:bg-white/10">Entrar no sistema</a>
                    </div>
                </div>

                {{-- Captura real do sistema (dados fictícios) --}}
                <figure class="lg:-mr-24">
                    <div class="overflow-hidden rounded-xl bg-canvas shadow-2xl ring-1 ring-white/15">
                        <div class="flex items-center gap-1.5 bg-slate-200 px-4 py-2.5" aria-hidden="true">
                            <span class="size-2.5 rounded-full bg-slate-400"></span><span class="size-2.5 rounded-full bg-slate-400"></span><span class="size-2.5 rounded-full bg-slate-400"></span>
                        </div>
                        <img src="/img/produto-painel.webp" width="1440" height="860" fetchpriority="high"
                             alt="Painel do CTS Convênios com valor da carteira, saldo disponível, convênios por etapa e prazos críticos"
                             class="block h-auto w-full">
                    </div>
                    <figcaption class="mt-3 text-sm text-slate-400">Painel do sistema, com dados fictícios de demonstração.</figcaption>
                </figure>
            </div>
        </section>

        {{-- A régua de prazos: o coração do produto --}}
        <section id="como-funciona" class="relative z-10 -mt-16 px-4 sm:-mt-20 sm:px-6">
            <div class="cartao mx-auto max-w-6xl p-6 sm:p-10">
                <h2 class="max-w-2xl text-2xl font-semibold tracking-tight text-petroleo sm:text-3xl">Cada prazo é vigiado por uma régua de avisos</h2>
                <p class="mt-3 max-w-2xl text-slate-600">
                    Você cadastra a vigência e a prestação de contas. O sistema confere todos os dias e avisa a equipe a cada marco, por e-mail e no sino do sistema.
                </p>

                <ol class="mt-10 grid gap-6 sm:grid-cols-5 sm:gap-0">
                    <li class="border-l-4 border-l-brand-500 pl-4 sm:border-t-4 sm:border-t-brand-500 sm:border-l-0 sm:pt-4 sm:pl-0 sm:pr-4">
                        <p class="text-5xl font-semibold tracking-tight text-petroleo">90</p>
                        <p class="text-sm font-medium text-slate-500">dias antes</p>
                        <p class="mt-2 text-sm text-slate-600">Tempo de sobra para planejar a execução.</p>
                    </li>
                    <li class="border-l-4 border-l-yellow-400 pl-4 sm:border-t-4 sm:border-t-yellow-400 sm:border-l-0 sm:pt-4 sm:pl-0 sm:pr-4">
                        <p class="text-5xl font-semibold tracking-tight text-petroleo">60</p>
                        <p class="text-sm font-medium text-slate-500">dias antes</p>
                        <p class="mt-2 text-sm text-slate-600">Hora de conferir contratos e pendências.</p>
                    </li>
                    <li class="border-l-4 border-l-amber-500 pl-4 sm:border-t-4 sm:border-t-amber-500 sm:border-l-0 sm:pt-4 sm:pl-0 sm:pr-4">
                        <p class="text-5xl font-semibold tracking-tight text-petroleo">30</p>
                        <p class="text-sm font-medium text-slate-500">dias antes</p>
                        <p class="mt-2 text-sm text-slate-600">Atenção: comece a fechar a prestação de contas.</p>
                    </li>
                    <li class="border-l-4 border-l-orange-500 pl-4 sm:border-t-4 sm:border-t-orange-500 sm:border-l-0 sm:pt-4 sm:pl-0 sm:pr-4">
                        <p class="text-5xl font-semibold tracking-tight text-petroleo">15</p>
                        <p class="text-sm font-medium text-slate-500">dias antes</p>
                        <p class="mt-2 text-sm text-slate-600">Urgente: o prazo está encostando.</p>
                    </li>
                    <li class="border-l-4 border-l-red-600 pl-4 sm:border-t-4 sm:border-t-red-600 sm:border-l-0 sm:pt-4 sm:pl-0">
                        <p class="text-5xl font-semibold tracking-tight text-red-700">Vencido</p>
                        <p class="text-sm font-medium text-slate-500">e depois</p>
                        <p class="mt-2 text-sm text-slate-600">O aviso segue por 30 dias, com o risco de inadimplência à vista.</p>
                    </li>
                </ol>
            </div>
        </section>

        {{-- O problema, dito como é --}}
        <section class="px-4 py-20 sm:px-6 sm:py-28">
            <div class="mx-auto grid max-w-6xl gap-10 lg:grid-cols-2">
                <h2 class="text-3xl font-semibold tracking-tight text-balance text-petroleo sm:text-4xl">Em prefeitura pequena, o controle costuma viver na memória de uma pessoa.</h2>
                <div class="space-y-5 text-lg text-slate-600">
                    <p>Convênios chegam de vários órgãos, cada um com sua vigência, seus contratos e suas prestações de contas. Boa parte mora em planilhas e pastas soltas.</p>
                    <p>Quando essa pessoa sai de férias — ou da prefeitura — o prazo passa em branco. E pendência em prestação de contas pode gerar inadimplência e travar novos repasses.</p>
                    <p class="font-medium text-petroleo">O CTS tira o prazo da memória e coloca no sistema, à vista de toda a equipe.</p>
                </div>
            </div>
        </section>

        {{-- Recursos --}}
        <section id="recursos" class="bg-canvas px-4 py-20 sm:px-6 sm:py-28">
            <div class="mx-auto max-w-6xl">
                <h2 class="max-w-3xl text-3xl font-semibold tracking-tight text-balance text-petroleo sm:text-4xl">Tudo o que a equipe usa no dia a dia, num lugar só</h2>

                <div class="mt-14 grid items-center gap-10 lg:grid-cols-[minmax(0,6fr)_minmax(0,5fr)]">
                    <img src="/img/produto-kanban.webp" width="1440" height="860" loading="lazy"
                         alt="Quadro Kanban com os convênios organizados por etapa, com faixa de cor pela urgência do prazo"
                         class="h-auto w-full rounded-xl shadow-cartao-alto ring-1 ring-borda">
                    <div>
                        <h3 class="text-2xl font-semibold tracking-tight text-petroleo">Cada convênio na sua etapa</h3>
                        <p class="mt-4 text-lg text-slate-600">
                            Do rascunho à finalização, os convênios ficam num quadro por etapa. Arraste para avançar. A faixa lateral de cada cartão
                            muda de cor conforme o prazo aperta, e você enxerga de longe o que precisa de atenção.
                        </p>
                    </div>
                </div>

                <div class="mt-20 grid items-center gap-10 lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
                    <div class="lg:order-1">
                        <h3 class="text-2xl font-semibold tracking-tight text-petroleo">O saldo de cada convênio, sem conta de cabeça</h3>
                        <p class="mt-4 text-lg text-slate-600">
                            O painel soma o valor da carteira, o que já foi contratado e o saldo disponível, e lista os prazos críticos.
                            Se os contratos de um convênio passarem do valor disponível, o sistema destaca na hora.
                        </p>
                    </div>
                    <img src="/img/produto-painel.webp" width="1440" height="860" loading="lazy"
                         alt="Painel financeiro com valor da carteira, contratado e saldo disponível"
                         class="h-auto w-full rounded-xl shadow-cartao-alto ring-1 ring-borda lg:order-2">
                </div>

                <dl class="mt-24 grid gap-x-12 gap-y-10 sm:grid-cols-2">
                    <div>
                        <dt class="text-lg font-semibold text-petroleo">Contratos ligados ao convênio</dt>
                        <dd class="mt-2 text-slate-600">Cadastre as empresas e os valores contratados. A situação de cada contrato (não iniciado, em andamento, paralisado ou concluído) aparece no painel.</dd>
                    </div>
                    <div>
                        <dt class="text-lg font-semibold text-petroleo">Documentos junto do processo</dt>
                        <dd class="mt-2 text-slate-600">Termo de assinatura, extratos e notas fiscais anexados ao convênio (PDF, imagem, Word, Excel ou XML, até 20 MB), com download controlado por perfil.</dd>
                    </div>
                    <div>
                        <dt class="text-lg font-semibold text-petroleo">Relatórios para o controle interno</dt>
                        <dd class="mt-2 text-slate-600">A carteira em planilha (CSV) ou PDF e a ficha completa de cada convênio, com contratos, documentos, alertas e o histórico de alterações.</dd>
                    </div>
                    <div>
                        <dt class="text-lg font-semibold text-petroleo">Trilha de auditoria</dt>
                        <dd class="mt-2 text-slate-600">Toda criação, alteração e exclusão fica registrada: quem fez, quando e o que mudou. Serve de base para responder ao tribunal de contas.</dd>
                    </div>
                    <div>
                        <dt class="text-lg font-semibold text-petroleo">Avisos em dois canais</dt>
                        <dd class="mt-2 text-slate-600">O e-mail chega para a equipe da prefeitura e o sino no sistema mostra o que ainda vale, com os dias sempre atualizados e o controle de lido e não lido.</dd>
                    </div>
                    <div>
                        <dt class="text-lg font-semibold text-petroleo">Perfis de acesso</dt>
                        <dd class="mt-2 text-slate-600">O gestor lança e edita; o controle interno consulta e exporta. Cada pessoa enxerga só a sua prefeitura.</dd>
                    </div>
                </dl>
            </div>
        </section>

        {{-- Para quem --}}
        <section id="para-quem" class="px-4 py-20 sm:px-6 sm:py-28">
            <div class="mx-auto max-w-6xl">
                <h2 class="max-w-3xl text-3xl font-semibold tracking-tight text-balance text-petroleo sm:text-4xl">Feito para quem responde pelos convênios</h2>

                <div class="mt-12 divide-y divide-borda border-y border-borda">
                    <div class="grid gap-3 py-8 md:grid-cols-[minmax(0,3fr)_minmax(0,7fr)]">
                        <h3 class="text-xl font-semibold text-petroleo">Gestor de convênios</h3>
                        <p class="text-slate-600">Lança convênios, contratos e documentos, acompanha o saldo e recebe os avisos antes de cada vencimento. Menos planilha, menos susto.</p>
                    </div>
                    <div class="grid gap-3 py-8 md:grid-cols-[minmax(0,3fr)_minmax(0,7fr)]">
                        <h3 class="text-xl font-semibold text-petroleo">Controle interno</h3>
                        <p class="text-slate-600">Consulta tudo sem poder alterar, exporta relatórios e vê o histórico de cada mudança. O trabalho de auditoria começa com os dados já organizados.</p>
                    </div>
                    <div class="grid gap-3 py-8 md:grid-cols-[minmax(0,3fr)_minmax(0,7fr)]">
                        <h3 class="text-xl font-semibold text-petroleo">Gabinete e secretarias</h3>
                        <p class="text-slate-600">Enxergam o painel com a carteira, os saldos e os prazos críticos para decidir o que priorizar, sem pedir posição por mensagem.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Segurança --}}
        <section id="seguranca" class="bg-canvas px-4 py-20 sm:px-6 sm:py-28">
            <div class="mx-auto grid max-w-6xl gap-10 lg:grid-cols-2">
                <div>
                    <h2 class="text-3xl font-semibold tracking-tight text-balance text-petroleo sm:text-4xl">Dados de prefeitura pedem cuidado. Aqui está o que já existe.</h2>
                    <p class="mt-5 text-lg text-slate-600">Sem promessa vaga: são recursos que o sistema já tem hoje.</p>
                </div>
                <ul class="divide-y divide-borda border-y border-borda text-slate-700">
                    <li class="py-5"><strong class="text-petroleo">Cada prefeitura enxerga só os próprios dados.</strong> O isolamento é aplicado em todas as consultas, não só na tela.</li>
                    <li class="py-5"><strong class="text-petroleo">Nada some sem deixar rastro.</strong> Gestores e fiscais não apagam registros; a exclusão passa pela equipe da plataforma e fica na trilha de auditoria.</li>
                    <li class="py-5"><strong class="text-petroleo">Acesso protegido.</strong> Limite de tentativas de login, sessão que expira após 12 horas e desligamento imediato de quem sai da equipe.</li>
                    <li class="py-5"><strong class="text-petroleo">Arquivos com dono.</strong> Os documentos só são baixados por quem tem acesso ao convênio, nunca por link aberto.</li>
                </ul>
            </div>
        </section>

        {{-- Contato --}}
        <section id="contato" class="bg-petroleo px-4 py-20 text-white sm:px-6 sm:py-28">
            <div class="mx-auto grid max-w-6xl gap-12 lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
                <div>
                    <div class="mb-6 h-1 w-14 rounded-full bg-ouro"></div>
                    <h2 class="text-3xl font-semibold tracking-tight sm:text-4xl">Veja o sistema com os dados da sua prefeitura</h2>
                    <p class="mt-5 max-w-md text-lg text-slate-300">
                        Deixe seus dados e mostramos o CTS Convênios funcionando, com a régua de prazos aplicada a convênios como os seus.
                    </p>
                    <p class="mt-6 max-w-md text-sm text-slate-400">
                        Usamos suas informações apenas para retornar este contato. Você pode pedir a exclusão a qualquer momento respondendo ao nosso e-mail.
                    </p>
                </div>

                <form id="form-contato" class="cartao space-y-5 p-6 text-slate-900 sm:p-8" novalidate>
                    {{-- Campo-armadilha: escondido de pessoas; robôs costumam preenchê-lo. --}}
                    <div class="absolute -left-[9999px]" aria-hidden="true">
                        <label for="website">Não preencha este campo</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="nome" class="text-sm font-medium">Nome</label>
                            <input id="nome" name="nome" required autocomplete="name" class="mt-1 w-full rounded-md border border-borda bg-white px-3 py-2.5 shadow-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none">
                            <p class="mt-1 hidden text-xs text-red-600" data-erro="nome"></p>
                        </div>
                        <div>
                            <label for="cargo" class="text-sm font-medium">Cargo <span class="font-normal text-slate-500">(opcional)</span></label>
                            <input id="cargo" name="cargo" autocomplete="organization-title" class="mt-1 w-full rounded-md border border-borda bg-white px-3 py-2.5 shadow-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none">
                            <p class="mt-1 hidden text-xs text-red-600" data-erro="cargo"></p>
                        </div>
                    </div>

                    <div>
                        <label for="municipio" class="text-sm font-medium">Município e UF</label>
                        <input id="municipio" name="municipio" required placeholder="Ex.: Exemplópolis/SP" class="mt-1 w-full rounded-md border border-borda bg-white px-3 py-2.5 shadow-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none">
                        <p class="mt-1 hidden text-xs text-red-600" data-erro="municipio"></p>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="email" class="text-sm font-medium">E-mail</label>
                            <input id="email" name="email" type="email" required autocomplete="email" class="mt-1 w-full rounded-md border border-borda bg-white px-3 py-2.5 shadow-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none">
                            <p class="mt-1 hidden text-xs text-red-600" data-erro="email"></p>
                        </div>
                        <div>
                            <label for="telefone" class="text-sm font-medium">Telefone <span class="font-normal text-slate-500">(opcional)</span></label>
                            <input id="telefone" name="telefone" type="tel" autocomplete="tel" placeholder="(00) 00000-0000" class="mt-1 w-full rounded-md border border-borda bg-white px-3 py-2.5 shadow-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none">
                            <p class="mt-1 hidden text-xs text-red-600" data-erro="telefone"></p>
                        </div>
                    </div>

                    <div>
                        <label for="mensagem" class="text-sm font-medium">Quer contar mais? <span class="font-normal text-slate-500">(opcional)</span></label>
                        <textarea id="mensagem" name="mensagem" rows="3" placeholder="Quantos convênios vocês acompanham hoje? Como controlam os prazos?" class="mt-1 w-full rounded-md border border-borda bg-white px-3 py-2.5 shadow-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none"></textarea>
                        <p class="mt-1 hidden text-xs text-red-600" data-erro="mensagem"></p>
                    </div>

                    <div>
                        <label class="flex items-start gap-3 text-sm text-slate-700">
                            <input type="checkbox" name="aceite" value="1" class="mt-0.5 size-4 rounded border-borda">
                            <span>Concordo que a Castilho Soluções Digitais use meus dados para retornar este contato.</span>
                        </label>
                        <p class="mt-1 hidden text-xs text-red-600" data-erro="aceite"></p>
                    </div>

                    <div id="contato-status" role="status" aria-live="polite" class="hidden rounded-md px-3 py-2 text-sm"></div>

                    <button type="submit" class="w-full rounded-md bg-brand-700 px-5 py-3 font-semibold text-white shadow-sm hover:bg-brand-800 disabled:opacity-60">Solicitar demonstração</button>
                </form>
            </div>
        </section>
    </main>

    <footer class="bg-petroleo-claro px-4 py-8 text-sm text-slate-300 sm:px-6">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4">
            <p>© {{ date('Y') }} Castilho Soluções Digitais. Todos os direitos reservados.</p>
            <a href="/app/login" class="font-medium text-white hover:underline">Entrar no sistema</a>
        </div>
    </footer>
</body>
</html>
