#!/usr/bin/env bash
# ==========================================================================
# CTS Convênios — publica uma versão nova em produção.
#
#   ./scripts/deploy.sh
#
# Faz, nesta ordem: baixa o código, constrói as imagens, sobe o banco, aplica as migrações
# na imagem NOVA e só então troca os containers. Se a migração falhar, a versão antiga
# continua no ar (nada foi trocado ainda).
# ==========================================================================
set -euo pipefail

cd "$(dirname "$0")/.."

[ -f .env.production ] || { echo "ERRO: falta o arquivo .env.production (copie de .env.production.example)." >&2; exit 1; }

COMPOSE="docker compose --env-file .env.production -f docker-compose.prod.yml"

echo "==> Baixando o código"
git pull --ff-only

echo "==> Construindo as imagens"
$COMPOSE build

echo "==> Garantindo banco e Redis no ar"
$COMPOSE up -d --wait database redis

echo "==> Aplicando as migrações (na imagem nova)"
$COMPOSE run --rm --no-deps app php artisan migrate --force

echo "==> Trocando os containers"
$COMPOSE up -d --remove-orphans

echo "==> Estado final"
$COMPOSE ps

echo "Pronto. Confira: curl -fsS \$(grep '^APP_URL=' .env.production | cut -d= -f2)/up"
