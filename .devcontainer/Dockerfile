FROM mcr.microsoft.com/devcontainers/php:1-8.3-bookworm

USER root

RUN rm -f /etc/apt/sources.list.d/yarn.list /etc/apt/sources.list.d/yarn.list.save \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        ca-certificates \
        postgresql-client \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
        libonig-dev \
        bash \
        jq \
    && docker-php-ext-install \
        pdo_pgsql \
        intl \
        zip \
        bcmath \
        gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get update \
    && apt-get install -y nodejs \
    && corepack enable \
    && corepack prepare pnpm@11.1.2 --activate \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

USER vscode

WORKDIR /workspaces/dz-saas-commerce
