# CTS Convênios — colocar e manter em produção

Este guia leva do servidor vazio ao sistema no ar. Tudo o que depende do **domínio** (que ainda não
foi registrado) está reunido na seção [Quando o domínio existir](#quando-o-dominio-existir).

## Como o sistema roda

```
Internet ──► Caddy (HTTPS) ──► Nginx ──► PHP-FPM (app) ──► PostgreSQL
                                              │
                          queue-worker ◄──────┼──────► Redis
                          scheduler   ◄───────┘
```

| Serviço | O que faz |
|---|---|
| `caddy` | Porta de entrada. Com domínio, emite e renova o certificado HTTPS sozinho. |
| `web` | Nginx: entrega os arquivos (front-end, imagens) e chama o PHP. |
| `app` | O sistema (Laravel/PHP-FPM). |
| `queue-worker` | Envia os alertas por e-mail (fila no Redis). |
| `scheduler` | Roda o Motor de Alertas todo dia às 07:00 (Brasília) e a limpeza de tokens. |
| `database` | PostgreSQL 15. Não é exposto fora da rede interna. |
| `redis` | Cache e fila. Exige senha e também não é exposto. |

Volumes (dados que sobrevivem a novas versões): `cts_postgres_data` (banco), `cts_storage` (documentos
enviados e logs), `cts_redis_data`, `caddy_data` (certificados).

## Requisitos do servidor

- Linux (Ubuntu 22.04/24.04 ou similar) com **Docker Engine 24+** e o plugin **Docker Compose v2**.
- 2 GB de RAM e 20 GB de disco para começar (o disco cresce com os documentos enviados).
- Portas **80** e **443** abertas para a internet. **Nenhuma outra** (o banco e o Redis ficam fechados).
- Acesso SSH por chave (desative login por senha).

## Primeira instalação

```bash
git clone https://github.com/AugustoCastilhoDev/cts-convenios.git
cd cts-convenios

cp .env.production.example .env.production
nano .env.production        # preencha os campos vazios (veja abaixo)
```

**O que preencher no `.env.production`**

| Variável | Como obter |
|---|---|
| `APP_KEY` | Gere com o comando abaixo e **guarde uma cópia em local seguro**. |
| `DB_PASSWORD`, `REDIS_PASSWORD` | Senhas longas e únicas: `openssl rand -hex 24`. |
| `APP_URL` | O endereço público. Sem domínio ainda: `http://IP-DO-SERVIDOR`. |
| `RESEND_API_KEY`, `MAIL_FROM_ADDRESS` | Da conta do Resend (depende do domínio verificado). |
| `CONTATO_DESTINO` | E-mail que recebe os pedidos de demonstração da landing page. |
| `ALERTAS_REDIRECIONAR_PARA` | **Deixe vazio.** Preenchido, todos os alertas vão só para esse e-mail. |

Gerar a `APP_KEY` (depois de construir as imagens, ou use qualquer PHP):

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml build
docker compose --env-file .env.production -f docker-compose.prod.yml run --rm --no-deps -e APP_KEY=base64:temporaria app php artisan key:generate --show
```

Cole o resultado (`base64:...`) em `APP_KEY=`.

**Subir tudo** (constrói, migra o banco e liga os serviços):

```bash
./scripts/deploy.sh
```

**Criar o primeiro Administrador Interno** (pede a senha duas vezes; mínimo de 10 caracteres):

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan admin:criar seu@email.com --nome="Seu Nome"
```

No **primeiro login** esse administrador é levado a ativar a **verificação em duas etapas** (app autenticador
no celular: Google Authenticator, Microsoft Authenticator, Authy ou gerenciador de senhas) e a guardar os 8
códigos de recuperação. Sem isso o sistema não abre: é obrigatório para o super administrador e para o
administrador da prefeitura (`DOIS_FATORES_OBRIGATORIO=true` no `.env.production`; só o desenvolvimento usa `false`).

> Nunca rode `db:seed` em produção: ele cria usuários de demonstração com senha `password`. O
> sistema recusa (o seeder para com erro), mas não conte com isso como única barreira.

Depois, entre em `/app/login`, e pelo painel do administrador cadastre a primeira **prefeitura** e o
primeiro **Gestor**.

**Conferir que está tudo bem**

```bash
curl -fsS http://SEU-ENDERECO/up                # deve responder 200
docker compose --env-file .env.production -f docker-compose.prod.yml ps   # tudo "Up"/"healthy"
docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan email:testar seu@email.com
docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan alertas:processar --dry-run
```

## Atualizar para uma versão nova

```bash
./scripts/deploy.sh
```

O script baixa o código, constrói as imagens, aplica as migrações **na imagem nova** e só então troca
os containers. Se a migração falhar, a versão antiga continua no ar.

## Backup e restauração

**Backup** (banco + documentos enviados):

```bash
./scripts/backup.sh                         # grava em ./backups (mantém 14 dias)
```

Agende para todo dia (`crontab -e`):

```
30 2 * * * cd /caminho/do/projeto && ./scripts/backup.sh >> backups/backup.log 2>&1
```

> **Um backup no mesmo servidor não protege contra a perda do servidor.** Configure a cópia para a
> nuvem (abaixo) e, uma vez por trimestre, **teste restaurar** em uma máquina à parte. Backup nunca
> testado é esperança, não backup.

### Cópia para a nuvem (Cloudflare R2)

Com as variáveis `BACKUP_S3_*` preenchidas, o `backup.sh` envia cada backup para um bucket **já
criptografado no servidor** (AES-256, com a `BACKUP_SENHA`): o bucket nunca guarda dados pessoais
em texto aberto. Sem elas, o script só avisa que o backup ficou local. Se o envio falhar, o backup
local é feito mesmo assim e o script termina com **erro** (o cron ou o monitor percebe).

**Configurar (uma vez):**

1. No painel da Cloudflare: **R2 Object Storage** (pode pedir um cartão para ativar; confira a cota
   gratuita e os preços no site deles) e **Create bucket** (ex.: `cts-backups`). Deixe **privado**.
2. Na página do R2 copie o **Account ID**. Em **Manage API tokens > Create API token**, permissão
   **Object Read & Write**, limitada **só a esse bucket**. Guarde o *Access Key ID* e o *Secret Access
   Key* (o segredo só aparece uma vez).
3. Gere a senha de criptografia e **guarde uma cópia fora do servidor** (gerenciador de senhas), como a
   `APP_KEY`: sem ela ninguém consegue ler os backups da nuvem.
   ```bash
   openssl rand -base64 32
   ```
4. No `.env.production`:
   ```
   BACKUP_S3_ENDPOINT=https://SEU_ACCOUNT_ID.r2.cloudflarestorage.com
   BACKUP_S3_BUCKET=cts-backups
   BACKUP_S3_ACCESS_KEY_ID=...
   BACKUP_S3_SECRET_ACCESS_KEY=...
   BACKUP_SENHA=...
   ```
5. Rode `./scripts/backup.sh` uma vez e confira com `./scripts/backup-nuvem.sh listar`.

Na nuvem os backups ficam 30 dias (`BACKUP_NUVEM_RETENCAO_DIAS`); no servidor, 14 (`RETENCAO_DIAS`).
Os arquivos ficam com a extensão `.enc`. O envio usa o rclone dentro de um container (nada a instalar
além do Docker e do `openssl`).

> Limite conhecido: o token que envia também pode apagar. Quem invadir o servidor conseguiria apagar
> os backups da nuvem. Para reduzir esse risco, faça de vez em quando uma cópia manual para outro lugar.

**Restaurar a partir da nuvem** (servidor novo ou perda de dados):

```bash
./scripts/backup-nuvem.sh listar                                     # escolha os arquivos
./scripts/backup-nuvem.sh baixar banco-AAAA-MM-DD_HHMMSS.sql.gz.enc  # baixa e descriptografa em backups/restaurar/
./scripts/backup-nuvem.sh baixar documentos-AAAA-MM-DD_HHMMSS.tar.gz.enc
./scripts/restaurar.sh backups/restaurar/banco-AAAA-MM-DD_HHMMSS.sql.gz
```

Os documentos voltam pelo comando "Restaurar os documentos" (abaixo), usando o arquivo de
`backups/restaurar/`. Já foi ensaiado de ponta a ponta: apagando os dados e os backups locais e
recuperando tudo só pela nuvem.

**Restaurar o banco** (de um backup local):

```bash
./scripts/restaurar.sh backups/banco-AAAA-MM-DD_HHMMSS.sql.gz
```

**Restaurar os documentos** (num servidor novo, depois de subir o sistema uma vez):

```bash
docker run --rm -v cts_convenios_prod_cts_storage:/dados -v "$PWD/backups:/backup" alpine \
  tar xzf /backup/documentos-AAAA-MM-DD_HHMMSS.tar.gz -C /dados
docker compose --env-file .env.production -f docker-compose.prod.yml restart app queue-worker scheduler
```

## Dia a dia

```bash
C="docker compose --env-file .env.production -f docker-compose.prod.yml"
$C logs -f app queue-worker scheduler        # acompanhar erros e envios
$C ps                                        # saúde dos serviços
$C exec app php artisan queue:failed         # e-mails que falharam depois de todas as tentativas
$C exec app php artisan alertas:processar    # roda o Motor de Alertas agora (é idempotente)
$C restart queue-worker                      # depois de trocar RESEND_API_KEY, por exemplo
```

Os alertas saem todo dia às 07:00 (Brasília). Se o servidor ficou desligado nessa hora, o Motor
recupera o atraso na próxima varredura (o alerta sai uma vez, no marco mais próximo).

## Segurança do servidor (fora do código)

- Firewall liberando só **22, 80 e 443** (`ufw allow 22,80,443/tcp && ufw enable`).
- Atualizações automáticas de segurança do sistema (`unattended-upgrades`) e `fail2ban` para o SSH.
- O arquivo `.env.production` só legível pelo dono (`chmod 600 .env.production`).
- Trocar as senhas e a `RESEND_API_KEY` se alguém que os conhecia sair da equipe.
- A `APP_KEY` não muda depois que o sistema está em uso. Ela também protege o segredo do 2FA e os códigos de
  recuperação gravados no banco: **sem a mesma `APP_KEY`, uma restauração de backup deixa todos sem 2FA funcionando**.

### Verificação em duas etapas (2FA): quando alguém perde o celular

- **Gestor ou fiscal** (2FA opcional): o administrador da prefeitura clica em **Redefinir 2FA** na lista de usuários (pede a senha dele).
- **Administrador da prefeitura**: só o super administrador redefine (mesmo botão). Um administrador não desliga a proteção do colega.
- **Super administrador**: só pelo servidor. Sem tela e sem API, de propósito:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan admin:redefinir-2fa seu@email.com
```

Em todos os casos as sessões da pessoa caem e, onde o 2FA é obrigatório, ela configura o app de novo no próximo acesso.
O comando registra a ação no log da aplicação (o Auditing ignora o console). Tenha **pelo menos dois** super
administradores, ou os códigos de recuperação do único em lugar seguro.

### Senha temporária

A senha gerada ao criar a conta (ou em "Redefinir senha") vale **7 dias** (`SENHA_TEMPORARIA_VALIDADE_DIAS`). Vencida, o
login é recusado e a lista de usuários mostra "senha temporária vencida": o administrador usa **Redefinir senha** para gerar outra.

<a id="quando-o-dominio-existir"></a>
## Quando o domínio existir

Este é o único bloco que fica **pendente** hoje. Em ordem:

1. **Registrar o domínio** e apontar o DNS (registro **A**) do endereço escolhido, ex.
   `cts.seudominio.com.br`, para o IP do servidor.
2. No `.env.production`:
   - `SITE_ADDRESS=cts.seudominio.com.br` (liga o HTTPS automático)
   - `ACME_EMAIL=seu@email.com`
   - `APP_URL=https://cts.seudominio.com.br`
3. `./scripts/deploy.sh`. O Caddy emite o certificado na primeira visita (leva segundos).
4. **E-mail dos alertas**, no Resend: adicionar o domínio, criar os registros **SPF, DKIM** e
   **DMARC** que ele mostra no DNS e esperar aparecer "Verified". Depois:
   - `MAIL_FROM_ADDRESS="alertas@seudominio.com.br"`
   - garantir `ALERTAS_REDIRECIONAR_PARA=` **vazio**
   - reiniciar: `$C restart app queue-worker scheduler`
5. **Testar a entrega** enviando `email:testar` para caixas reais: Gmail, Outlook e, principalmente,
   uma caixa institucional `.gov.br`. Comece com DMARC em `p=none`, observe os relatórios e só
   depois endureça para `p=quarantine`. Um domínio novo pode cair em spam nos primeiros dias:
   peça aos primeiros usuários para marcarem "não é spam".
6. Conferir a landing page: o endereço canônico e o `robots.txt` já usam o `APP_URL`.

## Checklist antes de anunciar

- [ ] `APP_DEBUG=false`, `APP_ENV=production`, `ALERTAS_REDIRECIONAR_PARA` vazio.
- [ ] `APP_KEY` guardada fora do servidor.
- [ ] Backup diário agendado **e uma restauração testada**.
- [ ] Administrador criado com senha forte; senha de teste `password` não existe no banco.
- [ ] `DOIS_FATORES_OBRIGATORIO=true` e o 2FA do super administrador ativado (com os códigos de recuperação guardados fora do servidor).
- [ ] E-mail de alerta recebido em caixa `.gov.br` (e fora do spam).
- [ ] HTTPS ativo (cadeado) e `/up` respondendo.
- [ ] Firewall com só 22, 80 e 443.
- [ ] Texto jurídico revisado por advogado, campos "a preencher" resolvidos em `/privacidade` e `/termos`, e `TEXTO_JURIDICO_REVISADO=true`.

## Pendências que não são de código

- **Política de privacidade e termos de uso** (a landing coleta nome, e-mail e telefone; o sistema
  trata dados de servidores públicos). Peça revisão de quem entende de LGPD antes de vender.
- **Contrato/SLA** com as prefeituras: disponibilidade, suporte e o que acontece com os dados se o
  contrato acabar.
- **Rodapé da landing**: já mostra razão social e CNPJ. Falta um e-mail de contato oficial (`EMPRESA_EMAIL_CONTATO`).
- **Páginas `/privacidade` e `/termos`**: minuta pronta. Preencha no `.env.production` `EMPRESA_EMAIL_CONTATO`, `EMPRESA_ENCARREGADO_NOME`, `EMPRESA_ENCARREGADO_EMAIL`, `EMPRESA_HOSPEDAGEM` e `EMPRESA_FORO`. Depois da revisão jurídica, `TEXTO_JURIDICO_REVISADO=true` remove o aviso de minuta.

## Rotinas automáticas do scheduler

| Quando | Comando | O que faz |
|---|---|---|
| Todo dia, 07:00 (Brasília) | `alertas:processar` | Motor de Alertas: gera e envia os alertas de prazo (90/60/30/15 dias e vencidos). |
| Todo dia, 03:00 | `contatos:limpar` | Apaga pedidos de contato da landing além do prazo de guarda (`CONTATO_RETENCAO_MESES`, padrão 12). É o prazo citado na Política de Privacidade: se mudar um, mude o outro (o texto usa o valor configurado). |
| Todo dia | `sanctum:prune-expired` | Limpa tokens de login vencidos. |

Os pedidos de contato ficam na tela **Administração > Pedidos de contato** (só o administrador da plataforma).
