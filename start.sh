#!/bin/bash
set -e

# Render uses PORT env variable (default 10000)
PORT=${PORT:-10000}

# Update Apache to listen on Render's PORT
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT}/" /etc/apache2/sites-available/000-default.conf

# Ensure SQLite database file exists and is writable
mkdir -p /var/www/html/database
touch /var/www/html/database/database.sqlite

# Ensure storage directories exist
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

# Set permissions
chown -R www-data:www-data /var/www/html/database \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

chmod -R 775 /var/www/html/database \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

cd /var/www/html

# Generate application key if not set
php artisan key:generate --force

# Clear cached configs so env vars from Render take effect
php artisan config:clear
php artisan cache:clear

# Run migrations and seed demo data
php artisan migrate --force --seed

echo "ChefLagbe is starting on port ${PORT}..."

# Start Apache in foreground
exec apache2-foreground
