# ==========================================================================
# CTS Convênios — app-server
# PHP 8.4-FPM preparado para Laravel 13 (pdo_pgsql, redis, opcache, zip, bcmath)
#
# Nota: o Laravel 13 (via componentes Symfony 8 que ele carrega) exige
# PHP >= 8.4.1 em tempo de execução. 8.4 satisfaz a diretriz "PHP 8.3+".
# ==========================================================================
FROM php:8.4-fpm

ARG UID=1000
ARG GID=1000

# ---- Dependências de sistema necessárias para compilar as extensões PHP ----
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        zip \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
        libsqlite3-dev \
        supervisor \
    && rm -rf /var/lib/apt/lists/*

# ---- Extensões nativas do PHP exigidas pelo Laravel 13 + Postgres ----
# pdo_sqlite: só para a suíte de testes (PHPUnit usa SQLite em memória por
# padrão) — nunca usado como driver de produção, que é sempre pdo_pgsql.
RUN docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pdo_sqlite \
        pgsql \
        zip \
        bcmath \
        intl \
        opcache \
        mbstring \
        xml \
        exif \
        pcntl

# ---- Extensão Redis via PECL ----
RUN pecl install redis \
    && docker-php-ext-enable redis

# ---- Composer (instalado a partir da imagem oficial) ----
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ---- Configurações de PHP e OPcache para o ambiente ----
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-cts-convenios.ini

# ---- Usuário não-root alinhado ao usuário do host (evita problemas de permissão em volumes) ----
RUN groupadd -g ${GID} appuser \
    && useradd -u ${UID} -g appuser -m -s /bin/bash appuser

WORKDIR /var/www/html

USER appuser

EXPOSE 9000

CMD ["php-fpm"]
