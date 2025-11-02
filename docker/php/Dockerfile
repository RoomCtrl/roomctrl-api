FROM php:8.4-fpm

# Instalacja zależności systemowych
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libpq-dev \
    libicu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        intl \
        opcache \
        zip \
        ctype \
        iconv \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalacja Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Konfiguracja PHP
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/custom.ini $PHP_INI_DIR/conf.d/custom.ini

# Ustawienie katalogu roboczego
WORKDIR /var/www/html

# Użytkownik www-data
RUN chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
