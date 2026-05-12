FROM php:8.4-zts

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libssl-dev \
        libsqlite3-dev \
        libbrotli-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_mysql pdo_sqlite zip

RUN pecl install parallel \
    && docker-php-ext-enable parallel

RUN yes '' | pecl install swoole \
    && docker-php-ext-enable swoole

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
