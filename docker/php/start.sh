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
    mysql -h mysql -u turtle -pturtle turtle --ssl=0 < database/schema.sql 2>/dev/null || {
        echo ">>> Trying root user..."
        mysql -h mysql -u root -proot turtle --ssl=0 < database/schema.sql
    }

    touch storage/.db_initialized
    echo ">>> Database tables created!"
fi

# Migrate ENUM to support 'admin' role (always runs, idempotent)
mysql -h mysql -u turtle -pturtle turtle --ssl=0 -e "ALTER TABLE users MODIFY COLUMN role ENUM('admin','landlord','property_manager','maintenance','tenant') NOT NULL DEFAULT 'tenant';" 2>/dev/null || \
mysql -h mysql -u root -proot turtle --ssl=0 -e "ALTER TABLE users MODIFY COLUMN role ENUM('admin','landlord','property_manager','maintenance','tenant') NOT NULL DEFAULT 'tenant';"

# Ensure storage directories exist
mkdir -p storage/uploads/leases storage/logs storage/framework

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
