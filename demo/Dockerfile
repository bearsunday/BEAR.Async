FROM php:8.4-zts AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libssl-dev \
        libsqlite3-dev \
        libbrotli-dev \
        wrk \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pcntl pdo pdo_mysql pdo_sqlite zip

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /workspace/demo

FROM base AS parallel
RUN pecl install parallel \
    && docker-php-ext-enable parallel

FROM base AS swoole
RUN yes '' | pecl install swoole \
    && docker-php-ext-enable swoole
