# CTS Convênios

Sistema (SaaS) para prefeituras de pequeno porte acompanharem **convênios, emendas parlamentares e contratos** e
**não perderem prazos**. Um motor de alertas avisa por e-mail 90, 60, 30 e 15 dias antes de cada vencimento (e
depois dele), para que a prefeitura não fique inadimplente no **CADIN** por uma prestação de contas esquecida.

> Nenhum prazo de convênio perdido. Nenhuma inadimplência no CADIN.

## O problema

Uma prefeitura pequena costuma controlar convênios com planilhas e memória de quem está no cargo. Vigência,
prestação de contas e contrapartida vencem sem ninguém ver, o município fica impedido de receber novos recursos e
o prejuízo aparece meses depois. O CTS Convênios centraliza esses prazos e transforma o vencimento em aviso, com
tempo de agir.

## O que o sistema faz

- **Motor de alertas de prazo**: varre diariamente vigência e prestação de contas e envia e-mails na régua 90/60/30/15
  dias e de "prazo vencido", sem repetir avisos. Também há um sino de alertas dentro do sistema.
- **Painel** com o retrato da carteira: valores, prazos por faixa de urgência, filtro por secretaria.
- **Kanban de convênios** por etapa (proposta, em análise, aprovado, em execução, prestação de contas, finalizado), com detalhe do
  convênio, **contratos vinculados** (saldo e percentual contratado) e **documentos** anexados.
- **Exportações** para o controle interno e o TCE: carteira em CSV/PDF e ficha do convênio.
- **Auditoria** de quem alterou o quê e quando, consultável e exportável por prefeitura.
- **Administração da plataforma**: cadastro de prefeituras e usuários, pedidos de contato vindos da landing page.
- **Landing page** pública (`/`) com formulário de contato, e páginas de Política de Privacidade e Termos de uso.

### Perfis de acesso

| Perfil | O que faz |
| --- | --- |
| **Super administrador** | Equipe da plataforma. Vê todas as prefeituras, gerencia prefeituras e contas, é o único que exclui registros. |
| **Administrador da prefeitura** | Pessoa de confiança do município. Trabalha nos convênios como gestor e gerencia as contas e a auditoria da própria prefeitura. |
| **Gestor de convênios** | Servidor que lança convênios, prazos, contratos e documentos. |
| **Fiscal de controle interno** | Só leitura e exportação de relatórios. |

## Arquitetura

- **Multi-tenant** por `tenant_id` em toda tabela de negócio. O isolamento vale em duas camadas: um *global scope*
  automático nos Models e verificação redundante nas Policies.
- **API REST** em Laravel consumida por uma **SPA Vue** (`/app`), com autenticação por token (Sanctum, 12 h).
- **Camada de serviço**: os controllers só orquestram; regra de negócio e escrita no banco ficam em *Services*.
  Toda entrada passa por *Form Requests* e toda saída por *API Resources*.
- **Laravel 13 com atributos nativos do PHP** (`#[Fillable]`, `#[Authorize]`, `#[Middleware]`...) no lugar de
  propriedades e chamadas manuais.
- **Chaves UUID** nas entidades de domínio, **soft deletes** e **auditoria** em todas as alterações
  (`owen-it/laravel-auditing`).
- **Filas e agendador** próprios: alertas, backup e limpeza de dados antigos (LGPD) rodam sozinhos.

## Stack

| Camada | Tecnologia |
| --- | --- |
| Back-end | PHP 8.4, Laravel 13, Sanctum, owen-it/laravel-auditing, DomPDF |
| Front-end | Vue 3, Vue Router, Pinia, Tailwind CSS 4, Vite |
| Banco e cache | PostgreSQL 15, Redis 7 |
| Infraestrutura | Docker Compose (Nginx, PHP-FPM, fila, agendador), Caddy com HTTPS em produção |
| E-mail | Resend |
| Backup | `pg_dump` diário, criptografado (AES-256) e enviado para Cloudflare R2 |
| Testes | PHPUnit, Vitest, Playwright |
| CI | GitHub Actions (testes, estilo com Pint, build, ponta a ponta, imagens), Dependabot |

Padrão de código: PSR-12 (Laravel Pint).

## Segurança e LGPD

- **2FA** por app autenticador (TOTP), obrigatório para os administradores, com códigos de recuperação.
- Senhas com regra única (10+ caracteres, letras e números; em produção, recusa senhas vazadas).
  Contas novas recebem uma **senha temporária gerada pelo sistema**, mostrada uma vez, com validade de 7 dias e troca
  obrigatória no primeiro acesso. Há "Esqueci minha senha" com link de uso único.
- Limite de tentativas no login, no 2FA e nos formulários públicos; tokens com validade curta.
- Cabeçalhos de segurança (CSP, HSTS), exportações protegidas contra injeção de fórmulas em planilhas.
- Dados pessoais mínimos, com prazo de guarda dos pedidos de contato e política de privacidade publicada.
- Verificação automática de vulnerabilidades nas dependências (`composer audit` e `npm audit`).

## Como rodar em desenvolvimento

Requisitos: Docker (Compose), Node.js 22+ e, para os testes de ponta a ponta, o Google Chrome.
O PHP e o Composer rodam dentro dos containers.

```bash
# 1. Configuração (ajuste as senhas do banco se quiser)
cp .env.example .env
cp src/.env.example src/.env

# 2. Subir os serviços e preparar o banco
docker compose up -d
docker compose exec app-server composer install
docker compose exec app-server php artisan key:generate
docker compose exec app-server php artisan migrate --force
docker compose exec app-server php artisan db:seed --force   # só em desenvolvimento: cria contas de demonstração

# 3. Front-end (dentro de src/)
cd src
npm install
npm run build          # ou: npm run dev
```

- Sistema: <http://localhost:8000/app/> · Landing page: <http://localhost:8000/>
- Contas de demonstração (só em desenvolvimento): `admin@ctsconvenios.com.br`, `gestor@municipio-exemplo.gov.br` e
  `fiscal@municipio-exemplo.gov.br`, todas com a senha `password`.
- Dados fictícios para explorar: `docker compose exec app-server php artisan demo:popular`.

> Nunca rode `db:seed` em produção: as contas de demonstração usam a senha `password`.

### Testes

```bash
docker compose exec app-server php artisan test    # PHP (SQLite em memória, nunca o banco de desenvolvimento)
cd src && npm test                                  # Vitest (front-end)
cd src && npm run test:e2e                          # Playwright: precisa do sistema no ar em localhost:8000
docker compose exec app-server vendor/bin/pint      # estilo (PSR-12)
```

## Estrutura do repositório

```
├── src/                     Aplicação Laravel + SPA Vue
│   ├── app/                 Models, Services, Policies, Controllers, Requests, Resources, comandos
│   ├── resources/js/        SPA (views, componentes, stores, testes Vitest)
│   ├── e2e/                 Testes de ponta a ponta (Playwright)
│   └── tests/               Testes PHP
├── docker/                  Dockerfiles e configuração do Nginx/PHP/Postgres
├── docker-compose.yml       Ambiente de desenvolvimento
├── docker-compose.prod.yml  Produção (HTTPS automático, logs com rotação)
├── scripts/                 Deploy, backup, cópia para a nuvem e restauração
├── docs/PRODUCAO.md         Guia de produção: instalação, backup, 2FA, checklist
└── ROADMAP.md               Estado atual, decisões, pendências e armadilhas conhecidas
```

## Documentação

- [`ROADMAP.md`](ROADMAP.md): o que já foi entregue, decisões de arquitetura, pendências e próximos passos.
- [`docs/PRODUCAO.md`](docs/PRODUCAO.md): como colocar no ar, atualizar, fazer e restaurar backup, e o que fazer quando alguém perde o celular do 2FA.

## Status

Em desenvolvimento ativo, com os módulos principais concluídos (carteira de convênios, motor de alertas, painel,
administração, auditoria, 2FA, backup). Faltam os passos de colocar no ar: domínio, servidor e revisão jurídica dos textos.
Detalhes no [ROADMAP](ROADMAP.md).

## Licença

Software proprietário de **Castilho Tech Soluções Digitais Ltda**. Todos os direitos reservados.
