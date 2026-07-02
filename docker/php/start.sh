#!/bin/bash
set -e

cd /var/www/html

# Wait for MySQL
echo ">>> Waiting for MySQL..."
until php -r "new PDO('mysql:host=mysql;port=3306;dbname=turtle', 'turtle', 'turtle');" 2>/dev/null; do
    sleep 1
done

# Run schema on first boot only
if [ -f database/schema.sql ] && [ ! -f storage/.db_initialized ]; then
    echo ">>> Setting up database..."
    bash database/migrate.sh
    touch storage/.db_initialized
    echo ">>> Database tables created!"
fi

# Configure git safe directory for mounted repo
git config --global --add safe.directory /var/www/html 2>/dev/null || true
git config --global --add safe.directory /var/www/turtle 2>/dev/null || true

# Ensure storage/upload directories and .git are writable by www-data
mkdir -p storage/uploads/leases storage/uploads/property_photos storage/logs storage/framework www/assets/uploads/logo
chown -R www-data:www-data storage/uploads storage/logs storage/framework www/assets .git 2>/dev/null || true
chmod -R 777 storage/uploads storage/logs storage/framework www/assets .git 2>/dev/null || true

# Start queue worker in background
php -r "
require '/var/www/html/www/autoload.php';
require '/var/www/html/www/functions.php';
\$running = true;
while (\$running) {
    try {
        \$notifications = \App\Core\Database::fetchAll(
            'SELECT * FROM notifications WHERE read_at IS NULL AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) ORDER BY created_at ASC LIMIT 10',
            []
        );
    } catch (\Throwable \$e) {
        error_log('Queue worker: ' . \$e->getMessage());
    }
    sleep(5);
}
" &
QUEUE_PID=$!
echo ">>> Queue worker started (PID: $QUEUE_PID)"

echo ">>> Turtle is ready!"

exec apache2-foreground
