# syntax=docker/dockerfile:1.7

FROM composer:2 AS vendor
WORKDIR /app

RUN apk add --no-cache icu-libs \
    && apk add --no-cache --virtual .composer-build-deps \
        $PHPIZE_DEPS \
        icu-dev \
    && docker-php-ext-install intl \
    && apk del .composer-build-deps

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY app app
COPY bootstrap bootstrap
COPY config config
COPY database database
COPY routes routes
COPY artisan artisan
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache
RUN composer dump-autoload --no-dev --optimize

FROM node:24-alpine AS assets
WORKDIR /app

COPY package.json package-lock.json vite.config.js ./
COPY resources resources
COPY public public
RUN npm ci --ignore-scripts
RUN npm run build

FROM php:8.3-fpm-alpine AS runtime
WORKDIR /var/www/html

RUN apk add --no-cache \
        bash \
        curl \
        icu-libs \
        libpq \
        libzip \
        oniguruma \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        postgresql-dev \
    && docker-php-ext-install \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY docker/php-production.ini /usr/local/etc/php/conf.d/zz-production.ini

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=10s --start-period=20s --retries=3 CMD php artisan system:health --scope=live --format=json || exit 1

CMD ["php-fpm"]
