#!/bin/sh
# ==========================================================================
# CTS Convênios — ponto de entrada dos containers PHP de produção
# (php-fpm, worker da fila e agendador usam o mesmo).
# ==========================================================================
set -e

cd /var/www/html

# Falha cedo e com mensagem clara se faltar o essencial, em vez de subir quebrado.
if [ -z "$APP_KEY" ]; then
    echo "ERRO: APP_KEY vazia. Gere com: docker compose run --rm app php artisan key:generate --show" >&2
    exit 1
fi

if [ "$APP_ENV" != "production" ] || [ "$APP_DEBUG" = "true" ]; then
    echo "AVISO: APP_ENV deve ser 'production' e APP_DEBUG 'false' em produção." >&2
fi

# Cache de configuração, rotas, views e eventos: o Laravel para de ler arquivos a cada requisição.
# Feito na partida (e não no build) porque depende das variáveis de ambiente deste ambiente.
php artisan optimize --no-interaction >/dev/null

exec "$@"
