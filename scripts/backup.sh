#!/usr/bin/env bash
# ==========================================================================
# CTS Convênios — backup do banco de dados e dos documentos enviados.
#
#   ./scripts/backup.sh                 # grava em ./backups
#   BACKUP_DIR=/mnt/disco ./scripts/backup.sh
#
# Agende no servidor (crontab -e), por exemplo todo dia às 02:30:
#   30 2 * * * cd /caminho/do/projeto && ./scripts/backup.sh >> backups/backup.log 2>&1
#
# IMPORTANTE: um backup que mora no mesmo servidor não protege contra a perda do servidor.
# Copie a pasta de backups para outro lugar (outro servidor, armazenamento em nuvem).
# ==========================================================================
set -euo pipefail

cd "$(dirname "$0")/.."

[ -f .env.production ] || { echo "ERRO: falta o arquivo .env.production." >&2; exit 1; }

BACKUP_DIR="${BACKUP_DIR:-./backups}"
RETENCAO_DIAS="${RETENCAO_DIAS:-14}"
COMPOSE="docker compose --env-file .env.production -f docker-compose.prod.yml"
AGORA="$(date +%Y-%m-%d_%H%M%S)"

# Lê usuário e banco do próprio .env.production, sem exportar segredos para o ambiente.
DB_USERNAME="$(grep -E '^DB_USERNAME=' .env.production | cut -d= -f2-)"
DB_DATABASE="$(grep -E '^DB_DATABASE=' .env.production | cut -d= -f2-)"

mkdir -p "$BACKUP_DIR"

echo "[$AGORA] Banco de dados..."
$COMPOSE exec -T database pg_dump -U "$DB_USERNAME" -d "$DB_DATABASE" --no-owner --clean --if-exists \
    | gzip > "$BACKUP_DIR/banco-$AGORA.sql.gz"

echo "[$AGORA] Documentos enviados..."
VOLUME="$($COMPOSE config --volumes | grep -x 'cts_storage')"
PROJETO="$($COMPOSE config | awk '/^name:/ {print $2}')"
docker run --rm \
    -v "${PROJETO}_${VOLUME}:/dados:ro" \
    -v "$(cd "$BACKUP_DIR" && pwd):/backup" \
    alpine tar czf "/backup/documentos-$AGORA.tar.gz" -C /dados app

# Confere que os arquivos gerados não estão vazios (backup vazio é pior que nenhum: dá falsa segurança).
for arquivo in "$BACKUP_DIR/banco-$AGORA.sql.gz" "$BACKUP_DIR/documentos-$AGORA.tar.gz"; do
    [ -s "$arquivo" ] || { echo "ERRO: $arquivo está vazio." >&2; exit 1; }
done

echo "[$AGORA] Removendo backups com mais de $RETENCAO_DIAS dias..."
find "$BACKUP_DIR" -maxdepth 1 -type f \( -name 'banco-*.sql.gz' -o -name 'documentos-*.tar.gz' \) -mtime "+$RETENCAO_DIAS" -delete

echo "[$AGORA] Backup concluído em $BACKUP_DIR"
