# Roadmap — CTS Convênios

> Última atualização: 2026-09-23 (Motor de Alertas; sessão pausada)
> Este arquivo existe para retomar o desenvolvimento sem perder contexto entre sessões. Sempre que uma etapa for concluída, mova-a para "Concluído" com a data.

## Stack e decisões de arquitetura já validadas

- **PHP 8.4-FPM** (não 8.3 — o Laravel 13.17 real puxa componentes Symfony 8 que exigem PHP >= 8.4.1; "8.3+" da diretriz original permite isso).
- **Laravel 13.17**, usando os **Atributos nativos do PHP** (`#[Table]`, `#[Fillable]`, `#[Hidden]`, `#[UsePolicy]`, `#[UseResource]` nos Models; `#[Middleware]`, `#[Authorize]` nos Controllers; `#[StopOnFirstFailure]` nos Form Requests) em vez de propriedades protegidas legadas.
- **PostgreSQL 15**, multi-tenant via coluna `tenant_id` denormalizada em toda tabela transacional (inclusive tabelas filhas como `contratos_vinculados`/`arquivos_convenio`, mesmo não estando explícito no doc de schema original — decisão deliberada de defesa em profundidade).
- **UUID como chave primária** em todas as entidades de domínio (`tenants`, `convenios`, `contratos_vinculados`, `arquivos_convenio`). `users` continua com `id` bigint (padrão Laravel) — decisão consciente para não precisar coordenar mudança de tipo em 3 migrations já aplicadas (`users`, `personal_access_tokens`, `audits`).
- **Isolamento multi-tenant em duas camadas**: `TenantScope` (global scope automático) + verificação redundante nas Policies — a Policy funciona mesmo se algum código futuro usar `withoutGlobalScope` por engano.
- **owen-it/laravel-auditing v14**, com a migration de audits usando `uuidMorphs('auditable')` (não `morphs()` — o padrão do pacote gera `bigint`, incompatível com nossos Models UUID).
- **Sanctum** para autenticação via Bearer token (sem sessão/cookie stateful).
- **Testes usam SQLite em memória** (`phpunit.xml`), nunca o Postgres de desenvolvimento — ver "Armadilhas conhecidas" abaixo.

## Concluído

### Infraestrutura Docker (2026-09-22)
- `docker-compose.yml` com 4 serviços: `app-server` (PHP 8.4-FPM), `web-server` (Nginx), `database` (Postgres 15), `cache-workers` (Redis 7).
- `docker/php/Dockerfile` com `pdo_pgsql`, `pdo_sqlite` (só para testes), `redis` (PECL), `opcache`, `bcmath`, `intl`, etc.
- `docker/nginx/default.conf` como proxy reverso para PHP-FPM.

### Base do domínio (2026-09-22)
- Scaffold do Laravel 13 em `src/`.
- Enums: `StatusConvenio`, `StatusExecucaoContrato`, `UserRole`.
- Migrations: `tenants`, `convenios`, `contratos_vinculados`, `arquivos_convenio` (todas com `tenant_id`, soft deletes, índices para a futura varredura de vencimento).
- Models: `Tenant`, `Convenio`, `ContratoVinculado`, `ArquivoConvenio` com Attributes nativos, `Auditable`, relações, accessors (`saldo_disponivel`, `dias_para_vencimento`).
- `owen-it/laravel-auditing` configurado e validado via HTTP real (não apenas console, que o pacote ignora por padrão).
- Traduções `pt_BR` para `validation.php`, `auth.php`, `pagination.php`.

### Módulo 1 — Autenticação e RBAC (2026-09-22)
- Laravel Sanctum instalado (`php artisan install:api`).
- `tenant_id`/`role` adicionados a `users` (migration separada, não editou a migration original).
- `AuthController` (`login`/`me`/`logout`) com Form Request dedicado.
- Policies: `ConvenioPolicy`, `ContratoVinculadoPolicy`, `ArquivoConvenioPolicy`, `TenantPolicy` — Administrador Interno via `before()`, Gestor de Convênios (CRUD exceto delete), Fiscal de Controle Interno (somente leitura + export).
- `DatabaseSeeder` com 1 tenant fictício + 3 usuários de teste (`admin@ctsconvenios.com.br`, `gestor@municipio-exemplo.gov.br`, `fiscal@municipio-exemplo.gov.br` — senha `password`).

### CRUD de Convênios (2026-09-22)
- `ConvenioController` (index/store/show/update/destroy) e `ContratoVinculadoController` (index/store/show/update, sem delete) aninhado em `/convenios/{convenio}/contratos`.
- Form Requests, Service Layer (`ConvenioService`, `ContratoVinculadoService`), API Resources (`ConvenioResource`, `ContratoVinculadoResource`).
- Autorização declarativa via `#[Authorize(...)]` nas rotas, incluindo o caso de passar o Model pai (`#[Authorize('create', [ContratoVinculado::class, 'convenio'])]`) para checar tenant antes de autorizar.
- `saldo_disponivel` calculado via `withSum()`/`loadSum()` (evita N+1).

### Testes automatizados (2026-09-22)
- 24 testes (PHPUnit) cobrindo login/logout, isolamento entre tenants, RBAC dos 3 papéis, validação de unicidade escopada por tenant, accessors do Model.
- 2 bugs reais capturados e corrigidos graças aos testes (ver "Bugs corrigidos" no histórico do projeto/commits).

### Upload/download de arquivos do convênio (2026-09-23)
- Endpoints aninhados: `GET/POST /convenios/{convenio}/arquivos`, `GET .../arquivos/{arquivo}/download`, `DELETE .../arquivos/{arquivo}`.
- Disco `local` privado (`storage/app/private/arquivos-convenio/{tenant_id}/{convenio_id}/{uuid}.ext`) — nada servido por URL pública, todo acesso passa pela Policy. Nome enviado pelo cliente vira só metadado (`nome_original`), nunca caminho em disco.
- Validação: `tipo_documento` via enum `TipoDocumentoConvenio` (termo_assinatura, extrato, nota_fiscal, outro); arquivo até 20 MB, `mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,xml` (detecção pelo conteúdo).
- Migration nova adiciona `nome_original`, `mime_type`, `tamanho_bytes`, `enviado_por` (FK `users`).
- `ArquivoConvenioPolicy::create()` agora recebe o `Convenio` da rota e exige tenant igual (mesmo padrão de `ContratoVinculadoPolicy`). Delete continua só Administrador Interno, e é soft delete (arquivo físico preservado para auditoria TCE).
- 9 testes novos (33 no total, todos passando) + smoke test via HTTP real (upload, download idêntico byte a byte, 401 sem token).

### E-mail via Resend (2026-09-23)
- `resend/resend-php` instalado; `MAIL_MAILER=resend` + `RESEND_API_KEY` no `src/.env` (nunca versionado). Comando `php artisan email:testar {destino}` mostra driver/remetente e envia mensagem de teste. Envio real validado (chegou, na pasta de spam).

### Gestão de usuários (2026-09-23)
- `GET/POST /users`, `GET/PUT /users/{user}` — exclusivo do Administrador Interno (`UserPolicy`). Cria só Gestor/Fiscal (papel e `tenant_id` definidos pelo `UserService`, fora do `#[Fillable]`); `tenant_id` não é editável. Contas de Administrador Interno não aparecem nem são editáveis pela API.
- Desativar (`active = false`) em vez de excluir: preserva autoria na auditoria. Desativar ou trocar a senha revoga todos os tokens.
- Middleware `conta.ativa` (`EnsureAccountIsActive`) em todas as rotas autenticadas: barra na hora tokens de usuário desativado **ou de prefeitura (`tenants.active`) inativa**. Login também bloqueia os dois casos.
- `php artisan admin:criar {email} {--nome=}` — único caminho para criar Administrador Interno; senha digitada oculta (mín. 10, letras e números), nada fixo no código.
- `User` agora é `Auditable` (password/remember_token excluídos em `config/audit.php`).
- **Bugs de auditoria encontrados e corrigidos:** (1) o resolver do pacote só consultava os guards `web`/`api`, então toda auditoria HTTP gravava `user_id` nulo — adicionado `sanctum` em `config/audit.php`; (2) `audits.auditable_id` era `uuid`, mas `users.id` é bigint, o que dava 500 ao auditar um User no Postgres — migration muda a coluna para `string(36)`.
- 14 testes novos (47 no total, todos passando) + smoke test HTTP real em Postgres.

### Motor de Alertas de prazo — Módulo 3 (2026-09-23)
- **Prazos monitorados:** fim da vigência e prazo de prestação de contas (o que leva ao CADIN). Régua 90/60/30/15 dias + aviso único de "prazo vencido" (janela de 30 dias após o prazo). Tudo em `config/alertas.php`.
- **Varredura diária** `alertas:processar` (07:00 America/Sao_Paulo, `withoutOverlapping` + `onOneServer`), com `--dry-run` que lista sem gravar. `AlertaPrazoService` faz a lógica; `EnviarAlertaPrazo` (job na fila Redis, 4 tentativas com backoff, `ShouldBeUnique`) envia o e-mail Markdown `AlertaPrazoConvenio`.
- **Idempotência:** tabela `alertas_prazo` (única por convênio + tipo + data do prazo + marco). Agendador parado não perde alerta (sai no marco mais próximo); prazo alterado reinicia a régua; alerta obsoleto (prazo mudou/convênio finalizado antes do envio) é cancelado, não enviado; sem destinatários fica pendente e sai quando houver alguém.
- **Destinatários:** Gestores e Fiscais ativos da prefeitura. Prefeitura inativa é ignorada. Vigência não alerta em "Prestação de Contas"; "Finalizado" nunca alerta.
- **Histórico:** `GET /convenios/{convenio}/alertas` (somente leitura; quem vê o convênio vê os alertas).
- **Fuso:** `APP_TIMEZONE` = `America/Sao_Paulo` (config/app.php): datas e horários de todo o sistema seguem Brasília. Timestamps antigos do banco de dev foram gravados em UTC (só afeta dados de teste).
- **Docker:** novos serviços `queue-worker` e `scheduler` (mesma imagem `cts-convenios-app`). Depois de mudar um Job em dev: `docker compose restart queue-worker`.
- **Segurança em dev:** `ALERTAS_REDIRECIONAR_PARA` (em `src/.env`) desvia TODOS os alertas para um e-mail só e marca o assunto com `[TESTE]` — os dados de teste têm e-mails fictícios e enviar para eles queima a reputação do domínio. **Em produção deve ficar vazio.**
- 29 testes novos (77 no total) + teste real de ponta a ponta (worker enviou os 2 alertas, 2ª execução não duplicou).

### Front-end Vue.js 3 — base e Kanban (2026-09-23)
- SPA em `src/resources/js` (Vue 3 + Vue Router + Pinia + Tailwind 4), servida por um shell Blade (`resources/views/app.blade.php`) com rota catch-all em `routes/web.php` (tudo fora de `/api/*` cai no Vue Router).
- Cliente HTTP em `services/api.js` (fetch + Bearer token do Sanctum guardado em `localStorage`; 401 derruba a sessão e volta ao login).
- Tela de login, layout autenticado e **Kanban** de convênios por etapa com arrastar e soltar (só para quem pode editar; atualização otimista com rollback se a API recusar).
- **Detalhe do convênio** (`/convenios/:id`): resumo financeiro, prazos, contratos vinculados (com cadastro inline) e histórico de alertas; **formulário de criar/editar** convênio em modal (erros de validação por campo). Kanban ganhou o botão "Novo convênio" e o número do card é link para o detalhe.
- Pendências do front-end resolvidas: Administrador Interno escolhe a prefeitura ao criar convênio (novo `GET /api/tenants`, só admin); contratos editáveis na própria linha da tabela; Kanban com rolagem por encaixe no celular e seletor "Mover para…" em cada card (o arrastar do HTML5 não funciona em toque).
- **Painel (dashboard)** em `/` (tela inicial após o login) alimentado por `GET /api/dashboard` (`DashboardService`, escopo por prefeitura): convênios em andamento, valor da carteira, contratado (% da carteira), saldo disponível, alerta de convênios com contratos acima do valor disponível, convênios por etapa, prazos críticos (vencidos + próximos 90 dias, mesmas regras do Motor de Alertas) e contratos por situação de execução. "Carteira" = convênios não finalizados. O Kanban passou para `/convenios`.
- **Documentos do convênio** (seção no detalhe): lista com tipo, tamanho e data; envio com tipo do documento (só Gestor/admin; validação prévia de extensão e 20 MB no navegador, a do servidor continua valendo); download autenticado (busca o arquivo com o token e entrega como Blob, pois link comum não leva o Bearer); botão Excluir só para o Administrador Interno, com confirmação; "Ver mais" quando passa de 15 arquivos.
- **Exportação para o Fiscal** (`RelatorioConvenioService`, pacote `barryvdh/laravel-dompdf`): `GET /api/convenios/exportar?formato=csv|pdf` (carteira; aceita `status` e `busca`) e `GET /api/convenios/{id}/ficha` (PDF completo: dados, financeiro, contratos, documentos, alertas e trilha de auditoria). CSV em UTF-8 com BOM, separador `;` e vírgula decimal (abre direto no Excel pt-BR) e proteção contra injeção de fórmula; PDFs com subconjunto de fontes (~25 KB). Quem vê o convênio exporta, sempre dentro da própria prefeitura; cada exportação vai para o log (`Relatório exportado`, com user_id). Na tela: botão "Exportar carteira" no Kanban e "Relatório → Ficha completa" no detalhe. O `Convenio::filtrar()` é o filtro único da listagem e da exportação.
- **Painel do Administrador Interno** (menu visível só para o admin; as rotas `/admin/*` são barradas para os demais):
  - **Prefeituras**: cadastro/edição/desativação (`POST/PUT /api/tenants`; CNPJ validado pelos dígitos verificadores e guardado com máscara, sem duplicar; cadastros antigos com CNPJ fictício continuam editáveis; sem exclusão — desativar preserva o histórico e derruba o acesso dos usuários na hora). Listagem com contagem de usuários/convênios (`GET /api/tenants?todas=1`).
  - **Usuários**: lista com filtros (prefeitura, papel, busca), criação do primeiro Gestor/Fiscal de uma prefeitura, edição, ativar/desativar e troca de senha (usa a API `/api/users` já existente).
  - **Auditoria** (`GET /api/audits`, só admin, `AuditPolicy`): filtros por tipo de registro, evento, período e ID; mostra quem, quando, IP e o antes/depois de cada campo; senhas nunca são registradas. O detalhe do convênio ganhou "Ver auditoria" (abre filtrado) e "Excluir" (só admin, com confirmação; exclusão lógica).
  - **Alterar senha** (`PUT /api/me/password`, qualquer perfil): exige a senha atual e desconecta os outros dispositivos — resolve a antiga pendência do admin.
- **Identidade visual e menu lateral** (2026-09-23): as cores agora são tokens em `src/resources/css/app.css` (`petroleo` para o menu e o cartão de saldo, `brand-*` teal para ações e links, `canvas` cinza-azulado para o fundo, `ouro` só na marca do item ativo); trocar a paleta é mexer nesse arquivo. O padrão de cartão é a classe `cartao` (branco, borda e sombra em camadas). Menu lateral fixo no desktop, recolhível para só ícones (preferência lembrada no navegador) e gaveta no celular; itens de Administração só para o admin. Kanban: coluna com faixa colorida da etapa, cartão com faixa lateral pela urgência do prazo (vermelho ≤15 d, laranja ≤30, âmbar ≤90, verde acima), cartão inteiro clicável. Painel: o saldo disponível é o único cartão escuro.
- **Sino de alertas no sistema** (2026-09-23): segundo canal além do e-mail, no canto superior direito (barra escura no celular). Lê os mesmos alertas do Motor (`alertas_prazo`), sem gerar nada novo: `GET /api/notificacoes` (+ `POST /api/notificacoes/{id}/lida` e `/notificacoes/lidas`), regras no `NotificacaoAlertaService`. Mostra um alerta por prazo (o mais recente: chegou o de 30 dias, some o de 60), dos últimos 60 dias, com os dias recalculados para hoje; esconde o que ficou desatualizado (prazo prorrogado, convênio fora dos status monitorados, cancelado ou excluído) pela mesma regra do e-mail (`AlertaPrazoService::estaObsoleto`). "Lido" é individual (tabela `alerta_prazo_leituras`). Só Gestor e Fiscal (`AlertaPrazoPolicy`); o admin não tem sino. A tela consulta a cada 60 s e ao voltar para a aba, e o título da aba mostra `(N)`. Ideia futura: mesmo painel serve de base para outros canais (WhatsApp) sem mudar o Motor.
- **Endurecimento para produção** (2026-09-23): login limitado a 5 tentativas/min por e-mail+IP (429 com mensagem em português) e API a 240/min; tokens Sanctum expiram em 12 h (`SANCTUM_EXPIRATION_MINUTOS`) com limpeza diária agendada; cabeçalhos de segurança + CSP (`CabecalhosDeSeguranca`; a CSP só vale fora do ambiente `local`, pois o Laravel Boost injeta um script de depuração em dev); proxies confiáveis (`TRUSTED_PROXIES`) para a auditoria gravar o IP real atrás do Caddy; `por_pagina` limitado a 200 (`Paginacao::porPagina`); `DatabaseSeeder` recusa rodar em produção (o `db:seed --force` contornaria a confirmação do artisan). Estilo do código: `pint --test` passa nos 146 arquivos.
- **Landing page + formulário de contato** (2026-09-23): página pública em `/` (Blade + Tailwind, sem o pacote do Vue; a "régua de prazos" 90/60/30/15/vencido como peça central, capturas reais do sistema em `public/img`, dados fictícios). O sistema (Vue) passou a viver em `/app` (Vue Router com base `/app/`); endereços antigos (`/login`, `/convenios/...`, `/admin/...`) redirecionam. `/app` e `/api` ficam fora dos buscadores (`robots.txt` + `noindex`). As duas páginas são **sem estado** (sem sessão, sem cookie, sem CSRF: não precisam de aviso de cookies). `POST /api/contato` (público, 5/hora por IP, campo-armadilha, aceite LGPD gravado com data e IP) grava em `contatos_comerciais` e avisa `CONTATO_DESTINO` por e-mail pela fila. Não há tela para ver os pedidos: consulte a tabela ou o e-mail (candidato a próximo item do painel do admin).
- **`php artisan demo:popular`** cria 8 convênios fictícios realistas na prefeitura de demonstração (`--limpar` remove; recusa rodar em produção). Serve para demonstrações comerciais e capturas de tela.
- **Infraestrutura de produção** (2026-09-23): `docker/prod/Dockerfile` (multi-estágio: dependências sem pacotes de dev, assets do Vite, imagem `app` e imagem `web`), `docker-compose.prod.yml` (Caddy com HTTPS automático via `SITE_ADDRESS`, Nginx, PHP-FPM, worker da fila, agendador, Postgres e Redis com senha, sem portas expostas), `.env.production.example`, `scripts/deploy.sh` (migra na imagem nova antes de trocar os containers), `scripts/backup.sh` / `scripts/restaurar.sh`, CI no GitHub Actions (testes, Pint, build do front, build das imagens) e o manual `docs/PRODUCAO.md`. `.gitattributes` força LF nos scripts e configs Docker (CRLF do Windows quebraria o `#!/bin/sh`).
- O Node roda **no Windows (host)**, não nos containers: `npm run build` (gera `public/build`, ignorado no git) ou `npm run dev` (Vite em :5173) dentro de `src/`.

## Painel, Kanban e marca — entregue (pedido de 2026-09-23)

Pedido do product owner, executado na ordem abaixo. Tudo entregue e verificado no navegador; o que ficou como simulação ou pendente está explícito nos itens e na análise.

### Etapa 1 — Back-end
- [x] **1.1 Migration `secretaria`** em `convenios`: coluna `string` (enum `App\Enums\Secretaria` no Model, regra do projeto: string + cast), **nullable** (há convênios antigos sem classificação), com índice `(tenant_id, secretaria)`. Valores: Saúde, Educação, Obras, Administração, Assistência Social e Outra. Para incluir outra secretaria: um `case` no enum + uma linha em `resources/js/utils/secretaria.js` (sem migration).
- [x] **1.2 Model e API**: `secretaria` no `#[Fillable]` do `Convenio` e cast para o enum; `ConvenioResource` devolve `secretaria` (+ `secretaria_label`), `valor_contratado` e `percentual_comprometido` (contratado ÷ (repasse + contrapartida) × 100, uma casa decimal, 0 se o valor total for 0, pode passar de 100). Também: filtro `Convenio::daSecretaria()`, validação nos requests, secretaria nas exportações (CSV, PDF, ficha) e no rótulo da auditoria, e `demo:popular` classificado.

### Etapa 2 — Kanban (Vue 3 + Tailwind)
- [x] **2.1 Badge de secretaria** no topo de cada cartão (Saúde verde, Obras azul, Educação laranja, Administração violeta, Assistência Social rosa, Outra cinza; sem secretaria = cinza claro "Sem secretaria"). Também no detalhe do convênio; o formulário ganhou o seletor (obrigatório ao criar).
- [x] **2.2 Indicador de contratação** abaixo do "Saldo disponível": `Contratado: R$ 1.100,00 (50%)` (o detalhe também mostra o percentual).
- [x] **2.3 Colunas vazias com `opacity-60`** quando o contador é 0 (confirmado no navegador: 0,6 nas vazias e 1 na que tem convênio); voltam a 100% ao arrastar algo por cima ou passar o mouse (continuam sendo alvo de soltar). A busca também conta: filtrar por texto esmaece as colunas que ficam sem resultados.

### Etapa 3 — Painel (Vue 3)
- [x] **3.1 Indicador de adimplência do município** no topo: bolinha vermelha + "Atenção: Risco de Inadimplência (CADIN)" (com nº de prazos vencidos, maior atraso e link "ver prazos") se houver qualquer prazo vencido; bolinha verde + "Município Regular" se tudo estiver em dia. Calculado no servidor (`regularidade` em `GET /api/dashboard`) com as mesmas regras do Motor de Alertas (vigência e prestação de contas em convênios ainda monitorados; finalizados não contam). Traz a nota de que não substitui a consulta oficial.
- [x] **3.2 Filtro "Filtrar por Secretaria"** no cabeçalho do Painel (`GET /api/dashboard?secretaria=saude`; inclui "Todas" e "Sem secretaria"): cartões, etapas, prazos críticos e contratos recalculam só para a secretaria. O filtro fica na URL (`?secretaria=`), esmaece os números antigos enquanto consulta e descarta respostas atrasadas. O selo de adimplência **não** muda com o filtro.
- [x] **3.3 Botão de ação rápida em "Prazos críticos"** (ícone de envelope; só para quem edita convênios): abre o modal "Cobrança de prazo ao fiscal" com a mensagem que seria enviada e **simula** o disparo. **Nada é enviado** e o modal avisa isso duas vezes (prévia + resultado).

### Marca
- [x] **Logotipo "CTS Convênios"**: SVG inline (escudo com degradê azul→esmeralda guardando um pequeno relógio sobre três barras crescentes; "CTS" em negrito e "CONVÊNIOS" menor com espaçamento largo), sem fontes/imagens externas. Escolhida entre 3 variações a versão que reúne os três conceitos (segurança, prazo, fluxo) e ainda lê bem pequena. `LogoCts.vue` (menu lateral, barra do celular, login; `compacto` = só o ícone no menu recolhido), `<x-logo-cts>` em Blade (landing) e `public/favicon.svg`.

### Análise do pedido (decisões e riscos)
1. **`valor_contratado` já existia como `total_contratado`** na API. Mantidos os dois nomes (o front atual usa `total_contratado`) para não quebrar nada; `valor_contratado` é o nome novo e explícito.
2. **`secretaria` nullable no banco e na API.** Tornar obrigatório quebraria o "arrastar" do Kanban (o `PUT` reenvia o convênio inteiro) e a edição de todos os convênios antigos. A tela de **criação** exige a escolha (governança); a API aceita vazio. Promover a obrigatória depois que os convênios existentes forem classificados.
3. **Filtro do Painel é feito no servidor** (`GET /api/dashboard?secretaria=saude`), não recalculado no navegador: uma única fonte de verdade para os números (o mesmo cálculo já usado nos relatórios), e a tela apenas consulta de novo quando o seletor muda. Inclui a opção "Sem secretaria" para achar convênios não classificados.
4. **O indicador de CADIN é do município inteiro e não muda com o filtro** (adimplência é do município, não de uma secretaria). Ele considera **apenas os prazos cadastrados no CTS** (vigência e prestação de contas vencidos em convênios ainda monitorados) — **não é consulta oficial ao CADIN/CAUC/SIAFI**. Por isso a tela traz uma nota explicando isso; "Município Regular" significa "sem prazo vencido no sistema", não uma certidão.
5. **O botão de cobrança é só simulação**, como pedido: nenhum e-mail é enviado e o modal avisa isso com clareza (não enganar o usuário). Para virar real faltam: um campo "fiscal responsável" por convênio (hoje não existe), endpoint próprio com autorização, limite de disparos e registro na auditoria.
6. Consequências em outros pontos: formulário de convênio e o "mover" do Kanban passam a carregar `secretaria`; exportações (CSV/PDF/ficha) e o rótulo da auditoria incluem a secretaria; `demo:popular` classifica a carteira fictícia.
8. **Pendências decorrentes** (para depois): (a) tornar `secretaria` obrigatória na API quando todos os convênios existentes estiverem classificados; (b) cobrança real ao fiscal (campo "fiscal responsável" por convênio + endpoint + limite + auditoria); (c) o Painel ainda não tem filtro por período nem o Kanban por secretaria (o pedido só cobria o Painel); (d) o indicador "Município Regular" poderia, no futuro, cruzar com uma consulta oficial (CAUC/CADIN) se houver integração.
7. **Logotipo**: o pedido usa `blue-500`/`emerald-500` (padrão do Tailwind), diferente do teal/ouro atual do sistema; seguido como pedido. O SVG é duplicado (Vue e Blade) porque a landing não carrega o Vue — se mudar o desenho, alterar os dois.

## Pendências conhecidas (não esquecidas, só adiadas)

- [ ] Alertas por WhatsApp: decisão (2026-09-23) de usar só e-mail por enquanto, sem plataforma de WhatsApp contratada. Retomar quando houver gateway; o Motor já separa a geração do alerta (`AlertaPrazoService`) do envio (`EnviarAlertaPrazo`), então um novo canal entra como outro job/notificação.
- [ ] **Domínio do CTS (única pendência para o go-live)**: ainda não registrado. Quando existir, seguir o bloco "Quando o domínio existir" de `docs/PRODUCAO.md`: DNS `A`, `SITE_ADDRESS`/`APP_URL`/`ACME_EMAIL`, verificar o domínio no Resend (SPF/DKIM/DMARC), trocar `MAIL_FROM_ADDRESS`, deixar `ALERTAS_REDIRECIONAR_PARA` vazio e testar entrega em caixas `.gov.br`/Outlook (o 1º teste caiu em spam no Gmail: reputação de domínio novo; SPF/DKIM/DMARC estavam corretos; DMARC começa em `p=none` e sobe para `p=quarantine`). Hoje o e-mail sai por um domínio provisório (`offerjetshop.net`, de outro projeto) — só para dev.
- [ ] **Texto jurídico**: política de privacidade, termos de uso e contrato/SLA com as prefeituras precisam de revisão de quem entende de LGPD; o rodapé da landing ainda não tem CNPJ/razão social/contato oficial.

## Próximos passos (em ordem sugerida)

O front-end do Módulo 2 (Kanban, painel, detalhe, contratos e documentos), a exportação do Fiscal e o painel do Administrador estão completos. Refinamentos possíveis depois: testes automatizados do front-end (Vitest) e ajustes visuais.

O que falta para ir ao ar é só o domínio e o texto jurídico (acima). Melhorias possíveis depois, sem ordem fixa:

- Tela no painel do admin para ver/exportar os pedidos de contato da landing (`contatos_comerciais`).
- Testes automatizados do front-end (Vitest) e um teste de ponta a ponta do fluxo principal.
- Monitoramento externo do `/up` e alerta se o servidor cair (ex.: UptimeRobot) e envio dos backups para fora do servidor.
- WhatsApp como terceiro canal de alerta, quando houver gateway contratado.

## Armadilhas conhecidas (para não repetir)

- **Testes com token no mesmo processo**: depois de um login (`Auth::once`) o Sanctum trata a chamada seguinte como sessão (token transitório), e depois de uma chamada por token o guard padrão vira `sanctum` e quebra o login seguinte. Em teste, chame `$this->app['auth']->forgetGuards()` (e `shouldUse('web')` antes de um login) entre elas. Em produção cada requisição é um processo novo, então não ocorre.
- **Python com barras invertidas no shell**: scripts `python - <<EOF` que editam PHP perdem as `\` de namespaces (viram erro de escape). Para editar arquivos PHP use a ferramenta Edit/Write, não Python inline.

- **BuildKit quebra com acento no caminho** (`CTS Convênios`). Sempre use `DOCKER_BUILDKIT=0 COMPOSE_DOCKER_CLI_BUILD=0` antes de `docker compose build`.
- **Git Bash converte paths absolutos Linux** em comandos `docker`/`docker compose exec`. Use `MSYS_NO_PATHCONV=1` quando o comando incluir um path tipo `/var/www/html`.
- **Ownership dos arquivos**: rodar `composer` via a imagem oficial `composer:2` (root) deixa arquivos com dono `root`, e o `app-server` roda como `appuser` (não-root). Depois de instalar pacotes assim, rode:
  `docker compose exec -u root app-server chown -R appuser:appuser /var/www/html` (com `MSYS_NO_PATHCONV=1` na frente, no Git Bash).
- **NUNCA rode testes (`RefreshDatabase`) contra o Postgres de desenvolvimento** — já aconteceu uma vez por causa de uma variável de ambiente duplicada no `docker-compose.yml` e apagou os dados do seeder. Os testes devem sempre resolver para SQLite (`config('database.default')` deve imprimir `sqlite` durante `php artisan test`). Se algum dia voltar a apontar para `pgsql`, **pare e investigue antes de rodar testes** — depois rode `php artisan db:seed --force` para repopular o dev.
- **O SQLite dos testes não valida tipos de coluna** (ex.: gravar `24` numa coluna `uuid` passa). Mudanças que envolvam auditoria, chaves ou tipos precisam de um smoke test HTTP real contra o Postgres — foi assim que os 2 bugs de auditoria acima apareceram.
- **`owen-it/laravel-auditing` não audita nada rodado via `artisan`/`tinker`/seeders** por padrão (`audit.console => false`) — isso é proposital do pacote, não bug. Só audita requisições HTTP reais.

- **Cache do PHP em dev (opcache)**: no Windows a pasta compartilhada com o Docker é lenta e conferir os arquivos a cada requisição custava ~2 s por página. Hoje `opcache.revalidate_freq = 30` (em `docker/php/php.ini`): as requisições levam ~0,2 s, mas uma edição em PHP pode levar até 30 s para valer **nas chamadas HTTP** (o artisan/testes não são afetados). Para valer na hora: `docker compose restart app-server` (o `kill` não existe na imagem). Em produção, usar `validate_timestamps = 0`.

- **`.dockerignore` do repositório vale para o contexto inteiro** (`context: .` nos dois Dockerfiles): ao excluir algo lá, confira que nenhum `COPY` do Dockerfile de produção precisa desse caminho.
- **Testes e `@vite`**: sem `public/build` (CI, máquina nova) qualquer teste que renderize `landing`/`app` falha com "Vite manifest not found". A base dos testes (`tests/TestCase.php`) chama `withoutVite()`; não remova.
- **Pint em pastas inteiras reformata arquivos antigos**; em mudanças pequenas rode só nos arquivos tocados (`vendor/bin/pint arquivo1 arquivo2`). O CI roda `pint --test` no projeto todo: mantenha-o passando.

## Comandos essenciais para retomar

```powershell
# Subir o ambiente
docker compose up -d

# Rodar migrations/seeder (se o banco estiver zerado)
docker compose exec app-server php artisan migrate --force
docker compose exec app-server php artisan db:seed --force

# Front-end (rodar no Windows, dentro de src/)
npm install
npm run build   # ou: npm run dev  (hot reload em http://localhost:5173, app em http://localhost:8000)

# Rodar a suíte de testes
docker compose exec app-server php artisan test

# Corrigir permissões depois de instalar algo via composer:2
docker compose exec -u root app-server chown -R appuser:appuser /var/www/html
```

Produção: veja `docs/PRODUCAO.md` (deploy, backup, restauração, checklist).
Sistema em desenvolvimento: `http://localhost:8000/app/` (a landing page é `http://localhost:8000/`).
Carteira de demonstração: `docker compose exec app-server php artisan demo:popular` (e `--limpar`).

Login de teste: `gestor@municipio-exemplo.gov.br` / `password` (senha igual para admin/gestor/fiscal).
