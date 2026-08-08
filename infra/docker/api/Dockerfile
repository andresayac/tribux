FROM composer:2 AS composer

FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install --no-install-recommends --yes git libpq-dev libzip-dev unzip \
    && docker-php-ext-install pcntl pdo_pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /srv/tribux

COPY packages ./packages
COPY apps/api ./apps/api

WORKDIR /srv/tribux/apps/api

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 8080

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
