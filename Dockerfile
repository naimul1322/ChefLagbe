FROM php:8.4-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libzip-dev \
    libsqlite3-dev \
    && docker-php-ext-install pdo_mysql zip \
    && (php -m | grep -qi pdo_sqlite || docker-php-ext-install pdo_sqlite) \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for layer caching
COPY composer.json ./
RUN composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs

# Copy all project files
COPY . .

# Complete Composer autoload
RUN composer dump-autoload --optimize --no-dev

# Ensure required Laravel directories & SQLite file exist
RUN mkdir -p database storage/framework/sessions storage/framework/views \
    storage/framework/cache/data storage/logs storage/app/public storage/app/private bootstrap/cache && \
    touch database/database.sqlite && \
    php artisan storage:link || true

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 775 storage bootstrap/cache database

# Copy and set up startup script (strip Windows CRLF if present)
COPY start.sh /start.sh
RUN sed -i 's/\r$//' /start.sh && chmod +x /start.sh

EXPOSE 10000

CMD ["/start.sh"]
