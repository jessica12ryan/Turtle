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
run_sql "ALTER TABLE users ADD COLUMN timezone VARCHAR(100) DEFAULT NULL AFTER remember_token;"
run_sql "ALTER TABLE property_tenant ADD COLUMN move_out_date DATE DEFAULT NULL AFTER lease_end;"
run_sql "ALTER TABLE ticket_comments ADD COLUMN is_system TINYINT(1) DEFAULT 0 AFTER is_internal;"
run_sql "CREATE TABLE IF NOT EXISTS ticket_files (id INT AUTO_INCREMENT PRIMARY KEY, ticket_id INT NOT NULL, comment_id INT DEFAULT NULL, file_path VARCHAR(500) NOT NULL, original_name VARCHAR(255) NOT NULL, size INT DEFAULT NULL, mime_type VARCHAR(100) DEFAULT NULL, uploaded_by INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE, FOREIGN KEY (comment_id) REFERENCES ticket_comments(id) ON DELETE SET NULL, FOREIGN KEY (uploaded_by) REFERENCES users(id), INDEX idx_ticket (ticket_id), INDEX idx_comment (comment_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
run_sql "INSERT IGNORE INTO settings (\`key\`, \`value\`) VALUES ('timezone', 'America/New_York'), ('ntp_server', 'time.gov'), ('last_ntp_check', ''), ('last_ntp_status', '');"
run_sql "CREATE TABLE IF NOT EXISTS resources (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, url VARCHAR(500) NOT NULL, description TEXT, created_by INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY (created_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
run_sql "CREATE TABLE IF NOT EXISTS role_permissions (role VARCHAR(50) NOT NULL, permission VARCHAR(100) NOT NULL, PRIMARY KEY (role, permission)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
# Only re-seed permissions if not in custom mode (preserves user customizations on updates)
PERM_MODE=$($MYSQL_CMD -N -e "SELECT \`value\` FROM settings WHERE \`key\` = 'permissions_mode';" 2>/dev/null || echo "")
if [ "$PERM_MODE" != "custom" ]; then
run_sql "DELETE FROM role_permissions WHERE 1=1;"
run_sql "INSERT INTO role_permissions (role, permission) VALUES
('landlord','home.access'),('landlord','properties.access'),('landlord','properties.create'),('landlord','properties.edit'),('landlord','properties.archive'),('landlord','properties.restore'),('landlord','photos.create'),('landlord','photos.edit'),('landlord','photos.download'),('landlord','photos.delete'),('landlord','tenants.access'),('landlord','tenants.create'),('landlord','tenants.edit'),('landlord','tenants.archive'),('landlord','tenants.restore'),('landlord','leases.access'),('landlord','leases.create'),('landlord','leases.archive'),('landlord','leases.restore'),('landlord','tickets.access'),('landlord','tickets.create'),('landlord','tickets.assign'),('landlord','tickets.update_status'),('landlord','tickets.archive'),('landlord','tickets.restore'),('landlord','tickets.comment'),('landlord','tickets.internal_comment'),('landlord','tickets.upload_photos'),('landlord','tickets.download_photos'),('landlord','staff.access'),('landlord','staff.create'),('landlord','staff.edit'),('landlord','staff.archive'),('landlord','staff.restore'),('landlord','resources.access'),('landlord','resources.create'),('landlord','resources.edit'),('landlord','resources.delete'),('landlord','calendar.access'),('landlord','documents.download'),
('property_manager','home.access'),('property_manager','properties.access'),('property_manager','properties.create'),('property_manager','properties.edit'),('property_manager','photos.create'),('property_manager','photos.edit'),('property_manager','photos.download'),('property_manager','photos.delete'),('property_manager','tenants.access'),('property_manager','tenants.create'),('property_manager','tenants.edit'),('property_manager','leases.access'),('property_manager','leases.create'),('property_manager','tickets.access'),('property_manager','tickets.create'),('property_manager','tickets.assign'),('property_manager','tickets.update_status'),('property_manager','tickets.comment'),('property_manager','tickets.internal_comment'),('property_manager','tickets.upload_photos'),('property_manager','tickets.download_photos'),('property_manager','staff.access'),('property_manager','resources.access'),('property_manager','resources.create'),('property_manager','resources.edit'),('property_manager','resources.delete'),('property_manager','calendar.access'),('property_manager','documents.download'),
('maintenance','home.access'),('maintenance','properties.access'),('maintenance','tenants.access'),('maintenance','staff.access'),('maintenance','tickets.access'),('maintenance','tickets.create'),('maintenance','tickets.assign'),('maintenance','tickets.update_status'),('maintenance','tickets.comment'),('maintenance','tickets.internal_comment'),('maintenance','tickets.upload_photos'),('maintenance','tickets.download_photos'),
('tenant','home.access'),('tenant','tickets.access'),('tenant','tickets.create'),('tenant','tickets.comment'),('tenant','tickets.upload_photos'),('tenant','tickets.download_photos'),('tenant','resources.access'),('tenant','leases.access'),('tenant','documents.download');"
fi

# Backfill company_user for property_manager/maintenance without entries
run_sql "INSERT IGNORE INTO company_user (company_id, user_id) SELECT c.id, u.id FROM users u CROSS JOIN companies c WHERE u.role IN ('property_manager','maintenance') AND NOT EXISTS (SELECT 1 FROM company_user cu WHERE cu.user_id = u.id);"

# Update version
APP_VER=$(cd /var/www/html && (git describe --tags 2>/dev/null || git log --oneline -1 --format=%h 2>/dev/null || echo "0.0.0") | sed 's/^v//')
run_sql "INSERT INTO settings (\`key\`, \`value\`) VALUES ('app_version', '${APP_VER}') ON DUPLICATE KEY UPDATE \`value\` = '${APP_VER}';"
