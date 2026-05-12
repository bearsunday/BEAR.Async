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

# parallel and swoole conflict on PHP shutdown when both loaded, so install
# them but route their INI files into opt-in directories. Composer scripts
# select the right one via PHP_INI_SCAN_DIR=':...'.
RUN pecl install parallel \
    && mkdir -p /usr/local/etc/php/conf.d-parallel \
    && echo "extension=parallel" > /usr/local/etc/php/conf.d-parallel/docker-php-ext-parallel.ini

RUN yes '' | pecl install swoole \
    && mkdir -p /usr/local/etc/php/conf.d-swoole \
    && echo "extension=swoole" > /usr/local/etc/php/conf.d-swoole/docker-php-ext-swoole.ini

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
