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
- O Node roda **no Windows (host)**, não nos containers: `npm run build` (gera `public/build`, ignorado no git) ou `npm run dev` (Vite em :5173) dentro de `src/`.

## Pendências conhecidas (não esquecidas, só adiadas)

- [ ] Alertas por WhatsApp: decisão (2026-09-23) de usar só e-mail por enquanto, sem plataforma de WhatsApp contratada. Retomar quando houver gateway; o Motor já separa a geração do alerta (`AlertaPrazoService`) do envio (`EnviarAlertaPrazo`), então um novo canal entra como outro job/notificação.
- [ ] E-mail em produção: hoje envia via Resend com domínio provisório (`offerjetshop.net`, de outro projeto) — só para dev. Ao registrar o domínio do CTS: verificar no Resend, trocar `MAIL_FROM_ADDRESS`, DMARC em `p=quarantine` após estabilizar, e testar entrega em caixas institucionais (`.gov.br`, Outlook), pois o primeiro teste caiu em spam no Gmail (reputação de domínio novo + texto puro; SPF/DKIM/DMARC estavam corretos).

## Próximos passos (em ordem sugerida)

O front-end do Módulo 2 (Kanban, painel, detalhe, contratos e documentos), a exportação do Fiscal e o painel do Administrador estão completos. Refinamentos possíveis depois: testes automatizados do front-end (Vitest) e ajustes visuais.

1. **Preparação para produção**: revisar `APP_DEBUG`, gerar `APP_KEY` novo, secrets fora do `.env` versionado, CI rodando a suíte de testes a cada push.

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

Login de teste: `gestor@municipio-exemplo.gov.br` / `password` (senha igual para admin/gestor/fiscal).
