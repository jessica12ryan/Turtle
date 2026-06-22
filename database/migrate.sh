#!/bin/bash
# Database migrations — safe to run from updates (no root needed)
set -e

cd /var/www/html

MYSQL_CMD="mysql -h mysql -u turtle -pturtle turtle --skip-ssl"
MYSQL_ROOT="mysql -h mysql -u root -proot turtle --skip-ssl"

run_sql() {
    $MYSQL_CMD -e "$1" 2>/dev/null || $MYSQL_ROOT -e "$1" 2>/dev/null || true
}

# Run full schema (idempotent)
if [ -f database/schema.sql ]; then
    $MYSQL_CMD < database/schema.sql 2>/dev/null || $MYSQL_ROOT < database/schema.sql 2>/dev/null || true
fi

# Incremental migrations
run_sql "ALTER TABLE users MODIFY COLUMN role ENUM('admin','landlord','property_manager','maintenance','tenant') NOT NULL DEFAULT 'tenant';"
run_sql "ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT '' AFTER email;"
run_sql "ALTER TABLE properties ADD COLUMN landlord_id INT NOT NULL DEFAULT 1 AFTER id;"
run_sql "ALTER TABLE properties ADD FOREIGN KEY (landlord_id) REFERENCES users(id);"
run_sql "ALTER TABLE leases ADD COLUMN tenant_id INT DEFAULT NULL AFTER property_id;"
run_sql "ALTER TABLE leases ADD FOREIGN KEY (tenant_id) REFERENCES users(id);"
run_sql "CREATE TABLE IF NOT EXISTS property_photos (id INT AUTO_INCREMENT PRIMARY KEY, property_id INT NOT NULL, file_path VARCHAR(500) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(100) DEFAULT '', is_main TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE, INDEX idx_property (property_id), INDEX idx_main (property_id, is_main)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
run_sql "ALTER TABLE property_tenant ADD COLUMN lease_start DATE DEFAULT NULL AFTER moved_out_at;"
run_sql "ALTER TABLE property_tenant ADD COLUMN lease_end DATE DEFAULT NULL AFTER lease_start;"
run_sql "ALTER TABLE property_tenant ADD COLUMN move_out_date DATE DEFAULT NULL AFTER lease_end;"
run_sql "INSERT IGNORE INTO settings (\`key\`, \`value\`) VALUES ('timezone', 'America/New_York'), ('ntp_server', 'time.gov'), ('last_ntp_check', ''), ('last_ntp_status', '');"
run_sql "CREATE TABLE IF NOT EXISTS resources (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, url VARCHAR(500) NOT NULL, description TEXT DEFAULT '', created_by INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY (created_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"

# Update version
APP_VER=$(cd /var/www/html && (git describe --tags 2>/dev/null || git log --oneline -1 --format=%h 2>/dev/null || echo "0.0.0") | sed 's/^v//')
run_sql "INSERT INTO settings (\`key\`, \`value\`) VALUES ('app_version', '${APP_VER}') ON DUPLICATE KEY UPDATE \`value\` = '${APP_VER}';"
