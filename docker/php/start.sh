#!/bin/bash
set -e

cd /var/www/html

# Generate app key if not set
if ! grep -q "APP_KEY=base64" .env 2>/dev/null; then
    echo ">>> Generating app key..."
    php artisan key:generate --force
fi

# Install PHP dependencies if missing
if [ ! -d vendor ]; then
    echo ">>> Installing PHP dependencies..."
    composer install --no-interaction --prefer-dist
fi

# Run migrations and seed if needed
if ! php artisan migrate:status 2>/dev/null | grep -q "Ran"; then
    echo ">>> Waiting for database..."
    until php artisan db:show 2>/dev/null; do
        sleep 1
    done
    echo ">>> Running migrations and seeding..."
    php artisan migrate --seed --force
fi

# Start queue worker in background
php artisan queue:work --daemon --quiet &

echo ">>> Turtle is ready!"

exec apache2-foreground
