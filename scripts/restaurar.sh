#!/usr/bin/env bash
# ==========================================================================
# CTS Convênios — restaura o banco a partir de um backup gerado por backup.sh.
#
#   ./scripts/restaurar.sh backups/banco-2026-09-23_023000.sql.gz
#
# ATENÇÃO: substitui TODOS os dados atuais do banco pelos do backup. Os documentos enviados
# ficam em outro arquivo (documentos-*.tar.gz); para restaurá-los, veja docs/PRODUCAO.md.
# ==========================================================================
set -euo pipefail

cd "$(dirname "$0")/.."

ARQUIVO="${1:-}"
[ -n "$ARQUIVO" ] && [ -f "$ARQUIVO" ] || { echo "Uso: $0 caminho/do/banco-AAAA-MM-DD_HHMMSS.sql.gz" >&2; exit 1; }
[ -f .env.production ] || { echo "ERRO: falta o arquivo .env.production." >&2; exit 1; }

COMPOSE="docker compose --env-file .env.production -f docker-compose.prod.yml"
DB_USERNAME="$(grep -E '^DB_USERNAME=' .env.production | cut -d= -f2-)"
DB_DATABASE="$(grep -E '^DB_DATABASE=' .env.production | cut -d= -f2-)"

echo "Isto vai SUBSTITUIR todos os dados do banco '$DB_DATABASE' pelo conteúdo de $ARQUIVO."
read -r -p "Digite RESTAURAR para continuar: " RESPOSTA
[ "$RESPOSTA" = "RESTAURAR" ] || { echo "Cancelado."; exit 1; }

echo "==> Parando o sistema (o banco continua no ar)"
$COMPOSE stop caddy web app queue-worker scheduler

echo "==> Restaurando"
gunzip -c "$ARQUIVO" | $COMPOSE exec -T database psql -U "$DB_USERNAME" -d "$DB_DATABASE" -v ON_ERROR_STOP=1 --quiet

echo "==> Religando o sistema"
$COMPOSE up -d

echo "Restauração concluída."
