#!/bin/bash
set -e

cd /var/www/html

# Wait for MySQL
echo ">>> Waiting for MySQL..."
until php -r "new PDO('mysql:host=mysql;port=3306;dbname=turtle', 'turtle', 'turtle');" 2>/dev/null; do
    sleep 1
done

# Run schema/migrations — first boot creates tables, every boot runs incremental migrations (idempotent)
if [ -f database/schema.sql ]; then
    if [ ! -f storage/.db_initialized ]; then
        echo ">>> Setting up database (first boot)..."
        bash database/migrate.sh
        touch storage/.db_initialized
        echo ">>> Database tables created!"
    else
        echo ">>> Running incremental migrations..."
        bash database/migrate.sh || echo ">>> Migrations completed with warnings (see logs)"
    fi
fi

# Configure git safe directory for mounted repo
git config --global --add safe.directory /var/www/html 2>/dev/null || true
git config --global --add safe.directory /var/www/turtle 2>/dev/null || true

# Ensure writable dirs have correct permissions (least-privilege: no a+w on code)
mkdir -p storage/uploads/leases storage/uploads/property_photos storage/uploads/application_photos storage/uploads/ticket_files storage/logs storage/framework www/assets/uploads/logo
chmod 755 storage storage/uploads storage/uploads/* storage/logs storage/framework www/assets/uploads www/assets/uploads/logo 2>/dev/null || true
# Only make .git writable for pull; keep code read-only
chmod -R 775 storage storage/logs storage/framework 2>/dev/null || true
# Allow git to update tracked files without world-writable code
chown -R www-data:www-data storage www/assets/uploads 2>/dev/null || true
chmod 775 .git 2>/dev/null || true

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
