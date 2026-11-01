# TaniKu - Laravel 10 + PHP 8.3 + SQLite (Apache)
FROM php:8.3-apache

# Install PHP extensions Laravel + uploads need
RUN apt-get update && apt-get install -y \
    git unzip libsqlite3-dev \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libcurl4-openssl-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_sqlite sqlite3 mbstring xml curl zip gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Apache needs to serve Laravel's public/ folder as DocumentRoot
RUN sed -i 's#/var/www/html#/var/www/html/public#g' /etc/apache2/sites-enabled/000-default.conf
RUN rm -f /var/www/html/index.html

# Drop in Laravel project
COPY . /var/www/html

# Fix storage symlink for uploaded files served via web
RUN ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage

# Permissions for Apache user (uid 33)
RUN chown -R www-data:www-data storage bootstrap/cache database public/uploads 2>/dev/null \
    && chmod -R 775 storage bootstrap/cache database 2>/dev/null \
    && chown -R www-data:www-data /var/www/html/public/storage 2>/dev/null

RUN a2enmod rewrite

# Install PHP deps (no dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/database/database.sqlite \
    CACHE_DRIVER=file \
    FILESYSTEM_DISK=local \
    SESSION_DRIVER=file \
    QUEUE_CONNECTION=sync

RUN php artisan key:generate --force 2>/dev/null || true
RUN php artisan config:clear 2>/dev/null || true
RUN php artisan route:clear 2>/dev/null || true

EXPOSE 80
CMD ["apache2-foreground"]
