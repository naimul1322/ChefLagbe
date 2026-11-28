#!/bin/bash
set -e

PORT=${PORT:-10000}

# Configure Apache cleanly for Render's assigned PORT and Laravel public/ directory
echo "Listen ${PORT}" > /etc/apache2/ports.conf
cat <<EOF > /etc/apache2/sites-available/000-default.conf
<VirtualHost *:${PORT}>
    ServerName localhost
    DocumentRoot /var/www/html/public
    <Directory /var/www/html/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

cd /var/www/html

# Ensure .env exists (Render containers don't have .env by default)
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Generate APP_KEY safely if not set in environment
if [ -z "${APP_KEY:-}" ]; then
    GENERATED_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
    export APP_KEY="$GENERATED_KEY"
    if grep -q "^APP_KEY=" .env; then
        sed -i "s|^APP_KEY=.*|APP_KEY=${GENERATED_KEY}|" .env
    else
        echo "APP_KEY=${GENERATED_KEY}" >> .env
    fi
fi

# Automatically align APP_URL and Sanctum stateful domains with Render's live URL
if [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    export APP_URL="${RENDER_EXTERNAL_URL}"
fi
if [ -n "${RENDER_EXTERNAL_HOSTNAME:-}" ]; then
    export SANCTUM_STATEFUL_DOMAINS="${RENDER_EXTERNAL_HOSTNAME},localhost,localhost:8000,127.0.0.1,127.0.0.1:8000"
fi

# Ensure SQLite database and storage directories exist
mkdir -p database storage/framework/sessions storage/framework/views \
    storage/framework/cache/data storage/logs storage/app/public storage/app/private bootstrap/cache
touch database/database.sqlite

# Ensure public storage symlink exists
php artisan storage:link --force || true

# Clear cached configs so Render environment variables apply cleanly
php artisan config:clear
php artisan cache:clear || true

# Run migrations and seed demo data
php artisan migrate --force --seed

# Ensure Apache (www-data) owns database and storage AFTER root ran migrations/seeding
chown -R www-data:www-data /var/www/html/database \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache \
    /var/www/html/.env

chmod -R 775 /var/www/html/database \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

echo "ChefLagbe is starting on port ${PORT}..."

exec apache2-foreground
