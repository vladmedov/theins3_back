# syntax=docker/dockerfile:1
# Многостадийная сборка: базовый PHP-FPM → vendor (Composer) → production.
#
# Nova / приватные репозитории: auth.json не входит в контекст (.dockerignore).
# Передайте секрет при сборке:
#   docker buildx build --secret id=composer_auth,src=dev/auth.json --target production -t app .
# Локально: в dev/docker-compose.dev.xml для app/scheduler настроен secrets.composer_auth (dev/auth.json).

# ——— Базовый образ: PHP-FPM + расширения (то, что нужно в рантайме) ———
FROM php:8.3-fpm-bookworm AS php-base

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libmagickwand-dev \
    && docker-php-ext-configure zip \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_pgsql zip exif opcache \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd \
    && pecl config-set preferred_state beta \
    && pecl install imagick redis \
    && docker-php-ext-enable imagick redis \
    && rm -rf /var/lib/apt/lists/*

# Загрузки и тяжёлые операции (как на бою)
RUN { \
        echo 'upload_max_filesize = 50M'; \
        echo 'post_max_size = 55M'; \
        echo 'memory_limit = 1024M'; \
        echo 'max_execution_time = 36000'; \
        echo 'max_input_time = 36000'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

# OPcache для production-образа (код в контейнере не меняется без пересборки)
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.revalidate_freq=0'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# ——— Composer: ставим зависимости (в образ попадёт только vendor) ———
FROM php-base AS vendor

RUN apt-get update && apt-get install -y --no-install-recommends git \
    && rm -rf /var/lib/apt/lists/*

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

COPY . .

RUN --mount=type=secret,id=composer_auth,target=/root/.composer/auth.json \
    composer install --no-dev --optimize-autoloader --prefer-dist --no-progress

# ——— Production ———
FROM php-base AS production

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV HOME=/var/www/html/storage
ENV XDG_CONFIG_HOME=/var/www/html/storage/.config

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor

# storage в .dockerignore — в образе создаём скелет (данные через volume в рантайме)
RUN mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    /var/www/html/storage/.config/psysh \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

USER www-data

CMD ["php-fpm"]
