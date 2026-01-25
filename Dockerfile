FROM php:8.4-zts

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    && docker-php-ext-install zip pdo pdo_mysql

# Install parallel extension
RUN pecl install parallel && docker-php-ext-enable parallel

# Install swoole extension
RUN pecl install swoole && docker-php-ext-enable swoole

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
