#!/bin/bash
set -e

cd /var/www/html

# Run database setup if schema file exists
if [ -f database/schema.sql ] && [ ! -f storage/.db_initialized ]; then
    echo ">>> Waiting for MySQL..."
    until php -r "new PDO('mysql:host=mysql;port=3306;dbname=turtle', 'turtle', 'turtle');" 2>/dev/null; do
        sleep 1
    done

    echo ">>> Setting up database..."
    mysql -h mysql -u turtle -pturtle turtle --ssl=0 < database/schema.sql 2>/dev/null || {
        echo ">>> Trying root user..."
        mysql -h mysql -u root -proot turtle --ssl=0 < database/schema.sql
    }

    if [ -f database/seed.sql ]; then
        echo ">>> Seeding database..."
        mysql -h mysql -u turtle -pturtle turtle --ssl=0 < database/seed.sql 2>/dev/null || {
            mysql -h mysql -u root -proot turtle --ssl=0 < database/seed.sql
        }
    fi

    touch storage/.db_initialized
    echo ">>> Database ready!"
fi

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
