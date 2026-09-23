# Roadmap — CTS Convênios

> Última atualização: 2026-09-23
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

## Pendências conhecidas (não esquecidas, só adiadas)

- [ ] Login não bloqueia usuário cujo tenant está `active = false`.
- [ ] Comando `php artisan admin:criar` para criar o primeiro Administrador Interno em produção sem depender do `DatabaseSeeder` (que tem senha fixa `password` — inaceitável fora de dev).
- [ ] SMTP ainda não configurado (`MAIL_MAILER=log`) — adiado a pedido do usuário, necessário antes do Módulo 3 funcionar de verdade.

## Próximos passos (em ordem sugerida)

1. **Gestão de usuários** — CRUD de `users` restrito a Administrador Interno (criar Gestor/Fiscal de uma prefeitura). Inclui o comando `admin:criar` da pendência acima.
2. **Motor de Alertas (Módulo 3 — o diferencial do produto)**:
   - Job agendado (Laravel Scheduler) varrendo `convenios.data_vigencia_fim` diariamente.
   - Régua de 90/60/30/15 dias.
   - Notificações por e-mail (precisa do SMTP configurado antes) e WhatsApp (gateway a definir).
   - Fila via Redis (`QUEUE_CONNECTION=redis` já configurado).
3. **Front-end Vue.js 3 + TailwindCSS (Módulo 2)**:
   - Kanban de convênios por status.
   - Dashboard com indicadores financeiros (saldo disponível já vem pronto da API).
   - Tela de repositório de arquivos.
4. **Auditoria/relatórios para o Fiscal de Controle Interno**: endpoint de exportação (a ability `export` já existe na `ConvenioPolicy`, falta o Controller/formato de exportação — CSV/PDF).
5. **Preparação para produção**: revisar `APP_DEBUG`, gerar `APP_KEY` novo, secrets fora do `.env` versionado, CI rodando a suíte de testes a cada push.

## Armadilhas conhecidas (para não repetir)

- **BuildKit quebra com acento no caminho** (`CTS Convênios`). Sempre use `DOCKER_BUILDKIT=0 COMPOSE_DOCKER_CLI_BUILD=0` antes de `docker compose build`.
- **Git Bash converte paths absolutos Linux** em comandos `docker`/`docker compose exec`. Use `MSYS_NO_PATHCONV=1` quando o comando incluir um path tipo `/var/www/html`.
- **Ownership dos arquivos**: rodar `composer` via a imagem oficial `composer:2` (root) deixa arquivos com dono `root`, e o `app-server` roda como `appuser` (não-root). Depois de instalar pacotes assim, rode:
  `docker compose exec -u root app-server chown -R appuser:appuser /var/www/html` (com `MSYS_NO_PATHCONV=1` na frente, no Git Bash).
- **NUNCA rode testes (`RefreshDatabase`) contra o Postgres de desenvolvimento** — já aconteceu uma vez por causa de uma variável de ambiente duplicada no `docker-compose.yml` e apagou os dados do seeder. Os testes devem sempre resolver para SQLite (`config('database.default')` deve imprimir `sqlite` durante `php artisan test`). Se algum dia voltar a apontar para `pgsql`, **pare e investigue antes de rodar testes** — depois rode `php artisan db:seed --force` para repopular o dev.
- **`owen-it/laravel-auditing` não audita nada rodado via `artisan`/`tinker`/seeders** por padrão (`audit.console => false`) — isso é proposital do pacote, não bug. Só audita requisições HTTP reais.

## Comandos essenciais para retomar

```powershell
# Subir o ambiente
docker compose up -d

# Rodar migrations/seeder (se o banco estiver zerado)
docker compose exec app-server php artisan migrate --force
docker compose exec app-server php artisan db:seed --force

# Rodar a suíte de testes
docker compose exec app-server php artisan test

# Corrigir permissões depois de instalar algo via composer:2
docker compose exec -u root app-server chown -R appuser:appuser /var/www/html
```

Login de teste: `gestor@municipio-exemplo.gov.br` / `password` (senha igual para admin/gestor/fiscal).
