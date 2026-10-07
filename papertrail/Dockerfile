# syntax=docker/dockerfile:1

FROM node:20-alpine AS assets
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js postcss.config.js tailwind.config.js ./
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --no-autoloader

COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY routes ./routes
COPY artisan ./
RUN composer dump-autoload --no-dev --optimize \
    && php artisan package:discover --ansi

FROM php:8.2-apache AS runtime
WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        zip \
    && a2enmod rewrite headers expires \
    && sed -ri -e 's!Listen 80!Listen 8080!g' /etc/apache2/ports.conf \
    && rm -rf /var/lib/apt/lists/*

RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=0'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=10000'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/papertrail-entrypoint

RUN chmod +x /usr/local/bin/papertrail-entrypoint \
    && mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS -H "Host: papertrail-to.online" http://127.0.0.1:8080/health || exit 1

ENTRYPOINT ["papertrail-entrypoint"]
CMD ["apache2-foreground"]
