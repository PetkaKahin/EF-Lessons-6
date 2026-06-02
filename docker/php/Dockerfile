FROM php:8.4-fpm-bookworm

ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install \
        bcmath \
        pcntl \
        pdo_pgsql \
        pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd --gid ${GID} laravel \
    && useradd --uid ${UID} --gid laravel --shell /bin/bash --create-home laravel \
    && sed -i 's/user = www-data/user = laravel/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/group = www-data/group = laravel/' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html
