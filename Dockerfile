FROM php:8.4-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libzip-dev \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql zip \
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

# Configure Apache: point DocumentRoot to Laravel's public/ folder
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' \
        /etc/apache2/sites-available/000-default.conf && \
    sed -i 's|</VirtualHost>|<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n</VirtualHost>|g' \
        /etc/apache2/sites-available/000-default.conf

# Create SQLite database file
RUN mkdir -p database && touch database/database.sqlite

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 storage bootstrap/cache database

# Copy and set up startup script
COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 10000

CMD ["/start.sh"]
