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
    mysql -h mysql -u turtle -pturtle turtle --skip-ssl < database/schema.sql 2>/dev/null || {
        echo ">>> Trying root user..."
        mysql -h mysql -u root -proot turtle --skip-ssl < database/schema.sql 2>&1
    }

    touch storage/.db_initialized
    echo ">>> Database tables created!"
fi

# Migrations (run after schema is guaranteed to exist)
echo ">>> Running migrations..."
mysql -h mysql -u turtle -pturtle turtle --skip-ssl -e "ALTER TABLE users MODIFY COLUMN role ENUM('admin','landlord','property_manager','maintenance','tenant') NOT NULL DEFAULT 'tenant';" 2>/dev/null || \
mysql -h mysql -u root -proot turtle --skip-ssl -e "ALTER TABLE users MODIFY COLUMN role ENUM('admin','landlord','property_manager','maintenance','tenant') NOT NULL DEFAULT 'tenant';" 2>/dev/null || true
mysql -h mysql -u turtle -pturtle turtle --skip-ssl -e "ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT '' AFTER email;" 2>/dev/null || true
mysql -h mysql -u turtle -pturtle turtle --skip-ssl -e "ALTER TABLE properties ADD COLUMN landlord_id INT NOT NULL DEFAULT 1 AFTER id;" 2>/dev/null || true
mysql -h mysql -u turtle -pturtle turtle --skip-ssl -e "ALTER TABLE properties ADD FOREIGN KEY (landlord_id) REFERENCES users(id);" 2>/dev/null || true
mysql -h mysql -u turtle -pturtle turtle --skip-ssl -e "ALTER TABLE leases ADD COLUMN tenant_id INT DEFAULT NULL AFTER property_id;" 2>/dev/null || true
mysql -h mysql -u turtle -pturtle turtle --skip-ssl -e "ALTER TABLE leases ADD FOREIGN KEY (tenant_id) REFERENCES users(id);" 2>/dev/null || true

# Property photos table
mysql -h mysql -u turtle -pturtle turtle --skip-ssl -e "CREATE TABLE IF NOT EXISTS property_photos (id INT AUTO_INCREMENT PRIMARY KEY, property_id INT NOT NULL, file_path VARCHAR(500) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(100) DEFAULT '', is_main TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE, INDEX idx_property (property_id), INDEX idx_main (property_id, is_main)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;" 2>/dev/null || true

# Settings table for version tracking
mysql -h mysql -u turtle -pturtle turtle --skip-ssl -e "CREATE TABLE IF NOT EXISTS settings (\`key\` VARCHAR(100) PRIMARY KEY, \`value\` TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;" 2>/dev/null || true
mysql -h mysql -u turtle -pturtle turtle --skip-ssl -e "INSERT IGNORE INTO settings (\`key\`, \`value\`) VALUES ('app_version', '0.0.0'), ('last_update_check', ''), ('latest_version', ''), ('update_channel', 'stable');" 2>/dev/null || true

# Configure git safe directory for mounted repo
git config --global --add safe.directory /var/www/html 2>/dev/null || true

APP_VER=$(cd /var/www/html && (git describe --tags 2>/dev/null || git log --oneline -1 --format=%h 2>/dev/null || echo "0.0.0") | sed 's/^v//')
mysql -h mysql -u turtle -pturtle turtle --skip-ssl -e "UPDATE settings SET \`value\` = '${APP_VER}' WHERE \`key\` = 'app_version';" 2>/dev/null || true

# Ensure storage directories exist and are writable by www-data
mkdir -p storage/uploads/leases storage/logs storage/framework
chown -R www-data:www-data storage/uploads storage/logs storage/framework 2>/dev/null || true
chmod -R 777 storage/uploads storage/logs storage/framework 2>/dev/null || true

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
