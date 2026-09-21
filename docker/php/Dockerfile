FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        icu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        intl \
        zip \
        gd \
        opcache \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

EXPOSE 9000

CMD ["php-fpm"]
