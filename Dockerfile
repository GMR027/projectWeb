FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libxml2-dev libonig-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" mysqli gd zip mbstring exif \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-projectweb.ini
RUN a2enmod rewrite headers

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /usr/bin/composer /usr/bin/composer
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && rm -f /usr/bin/composer \
    && mkdir -p public/uploads public/exportaciones storage/archivos storage/fichas storage/import \
    && chown -R www-data:www-data public/uploads public/exportaciones storage

EXPOSE 80
