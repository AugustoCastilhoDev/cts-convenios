# ==========================================================================
# CTS Convênios — imagem de PRODUÇÃO (multi-estágio)
#
# Diferente do Dockerfile de desenvolvimento: o código, as dependências
# (sem pacotes de dev) e os assets do front-end ficam DENTRO da imagem, sem
# bind mount. Dois alvos saem daqui:
#   app  -> PHP-FPM (também usado pelo worker da fila e pelo agendador)
#   web  -> Nginx com os arquivos públicos (assets e imagens)
#
# Construir a partir da raiz do repositório:
#   docker compose --env-file .env.production -f docker-compose.prod.yml build
# ==========================================================================

# --------------------------------------------------------------------------
# base: PHP 8.4-FPM com as extensões que o sistema usa (sem SQLite: só o
# ambiente de testes precisa dele).
# --------------------------------------------------------------------------
FROM php:8.4-fpm AS base

ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        unzip \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_pgsql pgsql zip bcmath intl opcache mbstring xml exif pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd -g ${GID} appuser \
    && useradd -u ${UID} -g appuser -m -s /bin/bash appuser

WORKDIR /var/www/html

# --------------------------------------------------------------------------
# vendor: dependências PHP de produção (sem pacotes de desenvolvimento).
# --------------------------------------------------------------------------
FROM base AS vendor

COPY src/composer.json src/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# --------------------------------------------------------------------------
# assets: compila o front-end (Vue + Tailwind) com o Vite.
# --------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /var/www/html

COPY src/package.json src/package-lock.json ./
RUN npm ci

# O Tailwind lê o vendor do Laravel (paginação) e as views para achar as classes usadas.
COPY --from=vendor /var/www/html/vendor ./vendor
COPY src/vite.config.js ./
COPY src/resources ./resources
COPY src/public ./public
RUN npm run build

# --------------------------------------------------------------------------
# app: PHP-FPM final
# --------------------------------------------------------------------------
FROM base AS app

COPY docker/prod/php.ini /usr/local/etc/php/conf.d/99-cts-convenios.ini
COPY docker/prod/php-fpm.conf /usr/local/etc/php-fpm.d/zz-cts.conf
COPY docker/prod/entrypoint.sh /usr/local/bin/cts-entrypoint
# O sed remove um eventual CRLF (arquivo editado no Windows) que faria o "#!/bin/sh" falhar.
RUN sed -i 's/\r$//' /usr/local/bin/cts-entrypoint && chmod +x /usr/local/bin/cts-entrypoint

COPY src/ ./
COPY --from=vendor /var/www/html/vendor ./vendor
COPY --from=assets /var/www/html/public/build ./public/build

# Autoload otimizado; os scripts (package:discover) rodam aqui, sem precisar de .env.
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && rm -rf tests \
    && mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R appuser:appuser storage bootstrap/cache

USER appuser

ENTRYPOINT ["cts-entrypoint"]
EXPOSE 9000
CMD ["php-fpm"]

# --------------------------------------------------------------------------
# web: Nginx só com os arquivos públicos; o PHP fica no serviço "app".
# --------------------------------------------------------------------------
FROM nginx:1.29-alpine AS web

COPY docker/prod/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public

EXPOSE 80
