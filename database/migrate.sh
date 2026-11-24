#!/bin/bash
# Database migrations — safe to run from updates (no root needed)
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(dirname "${SCRIPT_DIR}")"
ENV_FILE="${REPO_DIR}/.env"
SCHEMA_FILE="${SCRIPT_DIR}/schema.sql"

# Read DB settings from the environment, falling back to the app's .env so a
# custom DB password does not silently skip every migration. Hardcoded
# credentials used to make this script a no-op outside the default Docker setup.
if [ -f "$ENV_FILE" ]; then
    for key in DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD DB_SOCKET DB_ROOT_PASSWORD MYSQL_ROOT_PASSWORD; do
        value=$(grep -E "^${key}=" "$ENV_FILE" 2>/dev/null | head -1 | cut -d= -f2-)
        value=$(printf '%s' "$value" | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'\$//")
        if [ -n "$value" ]; then
            export "${key}=${value}"
        fi
    done
fi

DB_HOST="${DB_HOST:-mysql}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-turtle}"
DB_USERNAME="${DB_USERNAME:-turtle}"
DB_PASSWORD="${DB_PASSWORD:-turtle}"

if [ -n "${DB_SOCKET:-}" ]; then
    MYSQL_BASE="mysql --socket=${DB_SOCKET} --skip-ssl"
else
    MYSQL_BASE="mysql -h ${DB_HOST} -P ${DB_PORT} --skip-ssl"
fi

if [ -n "$DB_PASSWORD" ]; then
    MYSQL_CMD="${MYSQL_BASE} -u ${DB_USERNAME} -p${DB_PASSWORD} ${DB_DATABASE}"
else
    MYSQL_CMD="${MYSQL_BASE} -u ${DB_USERNAME} ${DB_DATABASE}"
fi

# An explicitly empty DB_ROOT_PASSWORD means "root has no password" (the HA
# add-on). Otherwise fall back to the Docker Compose root password.
if [ "${DB_ROOT_PASSWORD+x}" = x ]; then
    ROOT_PASSWORD="$DB_ROOT_PASSWORD"
else
    ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-root}"
fi
if [ -n "$ROOT_PASSWORD" ]; then
    MYSQL_ROOT="${MYSQL_BASE} -u root -p${ROOT_PASSWORD} ${DB_DATABASE}"
else
    MYSQL_ROOT="${MYSQL_BASE} -u root ${DB_DATABASE}"
fi

MIGRATION_FAILURES=0

run_sql() {
    local output rootOutput combined
    if output=$($MYSQL_CMD -e "$1" 2>&1); then
        return 0
    fi
    if rootOutput=$($MYSQL_ROOT -e "$1" 2>&1); then
        return 0
    fi
    combined="${output} ${rootOutput}"

    # Most incremental statements are intentionally re-run on every boot.
    # MySQL reports "already applied" as an error; those are not failures.
    if printf '%s' "$combined" | grep -Eqi \
        'duplicate column name|duplicate key name|duplicate foreign key|already exists|check that column/key exists|can.t drop|duplicate entry'; then
        return 0
    fi

    echo "Migration failed: $1" >&2
    printf '%s\n' "$combined" >&2
    MIGRATION_FAILURES=$((MIGRATION_FAILURES + 1))
    return 0
}

# Run full schema (idempotent)
if [ -f "$SCHEMA_FILE" ]; then
    if ! schemaOut=$($MYSQL_CMD < "$SCHEMA_FILE" 2>&1); then
        if ! schemaRootOut=$($MYSQL_ROOT < "$SCHEMA_FILE" 2>&1); then
            schemaCombined="${schemaOut} ${schemaRootOut}"
            if ! printf '%s' "$schemaCombined" | grep -Eqi 'already exists|duplicate column name|duplicate key|duplicate foreign key'; then
                echo "Failed to load database/schema.sql" >&2
                printf '%s\n' "$schemaCombined" >&2
                MIGRATION_FAILURES=$((MIGRATION_FAILURES + 1))
            fi
        fi
    fi
fi

# Incremental migrations
# Allow activity log rows with no authenticated user (e.g. public-form emails/notifications)
run_sql "SET @fk_name = (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activity_logs' AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1); SET @drop_fk = IF(@fk_name IS NULL, 'SELECT 1', CONCAT('ALTER TABLE activity_logs DROP FOREIGN KEY \`', @fk_name, '\`')); PREPARE stmt FROM @drop_fk; EXECUTE stmt; DEALLOCATE PREPARE stmt; ALTER TABLE activity_logs MODIFY COLUMN user_id INT NULL DEFAULT NULL; ALTER TABLE activity_logs ADD CONSTRAINT fk_activity_logs_user FOREIGN KEY (user_id) REFERENCES users(id);"
run_sql "ALTER TABLE users MODIFY COLUMN role ENUM('admin','landlord','property_manager','maintenance','tenant') NOT NULL DEFAULT 'tenant';"
run_sql "ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT '' AFTER email;"
run_sql "ALTER TABLE properties ADD COLUMN landlord_id INT NOT NULL DEFAULT 1 AFTER id;"
# Named, guarded FKs: unnamed ADD FOREIGN KEY creates a new auto-generated
# constraint on every boot, which eventually makes ALTER TABLE on properties
# take minutes and blocks the whole app.
run_sql "SET @fk_name = (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'landlord_id' AND REFERENCED_TABLE_NAME = 'users' LIMIT 1); SET @add_fk = IF(@fk_name IS NULL, 'ALTER TABLE properties ADD CONSTRAINT fk_properties_landlord FOREIGN KEY (landlord_id) REFERENCES users(id)', 'SELECT 1'); PREPARE stmt FROM @add_fk; EXECUTE stmt; DEALLOCATE PREPARE stmt;"
run_sql "ALTER TABLE leases ADD COLUMN tenant_id INT DEFAULT NULL AFTER property_id;"
run_sql "ALTER TABLE leases ADD COLUMN document_type VARCHAR(50) DEFAULT NULL AFTER title;"
run_sql "SET @fk_name = (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leases' AND COLUMN_NAME = 'tenant_id' AND REFERENCED_TABLE_NAME = 'users' LIMIT 1); SET @add_fk = IF(@fk_name IS NULL, 'ALTER TABLE leases ADD CONSTRAINT fk_leases_tenant FOREIGN KEY (tenant_id) REFERENCES users(id)', 'SELECT 1'); PREPARE stmt FROM @add_fk; EXECUTE stmt; DEALLOCATE PREPARE stmt;"
run_sql "CREATE TABLE IF NOT EXISTS property_photos (id INT AUTO_INCREMENT PRIMARY KEY, property_id INT NOT NULL, file_path VARCHAR(500) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(100) DEFAULT '', is_main TINYINT(1) DEFAULT 0, sort_order INT NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE, INDEX idx_property (property_id), INDEX idx_main (property_id, is_main), INDEX idx_sort (property_id, sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
run_sql "SET @col_count = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'property_photos' AND COLUMN_NAME = 'sort_order'); SET @add_col = IF(@col_count = 0, 'ALTER TABLE property_photos ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER is_main', 'SELECT 1'); PREPARE stmt_sort_col FROM @add_col; EXECUTE stmt_sort_col; DEALLOCATE PREPARE stmt_sort_col;"
run_sql "SET @idx_count = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'property_photos' AND INDEX_NAME = 'idx_sort'); SET @add_idx = IF(@idx_count = 0, 'ALTER TABLE property_photos ADD INDEX idx_sort (property_id, sort_order)', 'SELECT 1'); PREPARE stmt_sort_idx FROM @add_idx; EXECUTE stmt_sort_idx; DEALLOCATE PREPARE stmt_sort_idx;"
run_sql "ALTER TABLE property_tenant ADD COLUMN lease_start DATE DEFAULT NULL AFTER moved_out_at;"
run_sql "ALTER TABLE property_tenant ADD COLUMN lease_end DATE DEFAULT NULL AFTER lease_start;"
run_sql "ALTER TABLE users ADD COLUMN timezone VARCHAR(100) DEFAULT NULL AFTER remember_token;"
run_sql "ALTER TABLE users ADD COLUMN theme VARCHAR(20) DEFAULT 'system' AFTER timezone;"
run_sql "ALTER TABLE users ADD COLUMN language VARCHAR(5) DEFAULT NULL AFTER theme;"
run_sql "ALTER TABLE users ADD COLUMN secondary_roles VARCHAR(255) DEFAULT NULL AFTER language;"
run_sql "ALTER TABLE users ADD COLUMN avatar_path VARCHAR(500) DEFAULT NULL AFTER secondary_roles;"
run_sql "ALTER TABLE users ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL AFTER secondary_roles;"
run_sql "ALTER TABLE properties ADD COLUMN country VARCHAR(2) DEFAULT 'CA' AFTER postal_code;"
run_sql "ALTER TABLE properties ADD COLUMN apt_suite VARCHAR(100) DEFAULT '' AFTER address;"
run_sql "ALTER TABLE properties ADD COLUMN property_manager_id INT DEFAULT NULL AFTER company_id;"
run_sql "SET @fk_name = (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'property_manager_id' AND REFERENCED_TABLE_NAME = 'users' LIMIT 1); SET @add_fk = IF(@fk_name IS NULL, 'ALTER TABLE properties ADD CONSTRAINT fk_properties_manager FOREIGN KEY (property_manager_id) REFERENCES users(id)', 'SELECT 1'); PREPARE stmt FROM @add_fk; EXECUTE stmt; DEALLOCATE PREPARE stmt;"
run_sql "INSERT IGNORE INTO settings (\`key\`, \`value\`) VALUES ('default_country', 'CA');"
run_sql "INSERT IGNORE INTO settings (\`key\`, \`value\`) VALUES ('openai_api_key', '');"
run_sql "ALTER TABLE property_tenant ADD COLUMN move_out_date DATE DEFAULT NULL AFTER lease_end;"
run_sql "ALTER TABLE ticket_comments ADD COLUMN is_system TINYINT(1) DEFAULT 0 AFTER is_internal;"
run_sql "CREATE TABLE IF NOT EXISTS ticket_files (id INT AUTO_INCREMENT PRIMARY KEY, ticket_id INT NOT NULL, comment_id INT DEFAULT NULL, file_path VARCHAR(500) NOT NULL, original_name VARCHAR(255) NOT NULL, size INT DEFAULT NULL, mime_type VARCHAR(100) DEFAULT NULL, uploaded_by INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE, FOREIGN KEY (comment_id) REFERENCES ticket_comments(id) ON DELETE SET NULL, FOREIGN KEY (uploaded_by) REFERENCES users(id), INDEX idx_ticket (ticket_id), INDEX idx_comment (comment_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
run_sql "INSERT IGNORE INTO settings (\`key\`, \`value\`) VALUES ('timezone', 'America/New_York'), ('ntp_server', 'time.gov'), ('last_ntp_check', ''), ('last_ntp_status', '');"
run_sql "INSERT IGNORE INTO settings (\`key\`, \`value\`) VALUES ('telemetry_enabled', '1'), ('telemetry_install_id', ''), ('last_telemetry_sent', '');"
run_sql "INSERT IGNORE INTO settings (\`key\`, \`value\`) VALUES ('activity_log_retention_days', '365');"
run_sql "CREATE TABLE IF NOT EXISTS resources (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, url VARCHAR(500) NOT NULL, description TEXT, type VARCHAR(20) NOT NULL DEFAULT 'general', created_by INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY (created_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
run_sql "ALTER TABLE resources ADD COLUMN type VARCHAR(20) NOT NULL DEFAULT 'general' AFTER description;"
run_sql "CREATE TABLE IF NOT EXISTS notification_events (event VARCHAR(100) NOT NULL, role VARCHAR(50) NOT NULL, PRIMARY KEY (event, role)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
run_sql "CREATE TABLE IF NOT EXISTS inapp_notification_events (event VARCHAR(100) NOT NULL, role VARCHAR(50) NOT NULL, PRIMARY KEY (event, role)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
run_sql "INSERT IGNORE INTO settings (\`key\`, \`value\`) VALUES ('inapp_notification_mode', 'default');"
run_sql "INSERT IGNORE INTO settings (\`key\`, \`value\`) VALUES ('messaging_enabled', '1');"
run_sql "INSERT IGNORE INTO settings (\`key\`, \`value\`) VALUES ('notification_mode', 'default');"
run_sql "INSERT IGNORE INTO settings (\`key\`, \`value\`) VALUES ('site_url', '');"
run_sql "CREATE TABLE IF NOT EXISTS role_permissions (role VARCHAR(50) NOT NULL, permission VARCHAR(100) NOT NULL, PRIMARY KEY (role, permission)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
run_sql "CREATE TABLE IF NOT EXISTS messages (id INT AUTO_INCREMENT PRIMARY KEY, sender_id INT NOT NULL, recipient_id INT NOT NULL, body TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, read_at TIMESTAMP NULL DEFAULT NULL, FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE, INDEX idx_conversation (sender_id, recipient_id, created_at), INDEX idx_recipient_unread (recipient_id, read_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
run_sql "CREATE TABLE IF NOT EXISTS user_presence (user_id INT NOT NULL PRIMARY KEY, last_seen TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
run_sql "CREATE TABLE IF NOT EXISTS activity_logs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, user_name VARCHAR(255) NOT NULL, action VARCHAR(100) NOT NULL, description TEXT, ip_address VARCHAR(45) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id), INDEX idx_user (user_id), INDEX idx_action (action), INDEX idx_created (created_at), INDEX idx_created_date (created_at, user_id, action)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
run_sql "ALTER TABLE activity_logs ADD INDEX idx_created_date (created_at, user_id, action);"
run_sql "ALTER TABLE properties ADD COLUMN rent_amount DECIMAL(10,2) DEFAULT NULL AFTER country;"
run_sql "ALTER TABLE properties ADD COLUMN rent_due_day INT DEFAULT NULL AFTER rent_amount;"
run_sql "ALTER TABLE properties ADD COLUMN heating_type VARCHAR(50) DEFAULT NULL AFTER rent_due_day;"
run_sql "ALTER TABLE properties ADD COLUMN security_deposit DECIMAL(10,2) DEFAULT NULL AFTER heating_type;"
run_sql "ALTER TABLE properties ADD COLUMN listed TINYINT(1) NOT NULL DEFAULT 1 AFTER security_deposit;"
# Per-property advertisement copy shown on the public listings page.
run_sql "SET @col_count = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'ad_description'); SET @add_col = IF(@col_count = 0, 'ALTER TABLE properties ADD COLUMN ad_description TEXT NULL AFTER security_deposit', 'SELECT 1'); PREPARE stmt FROM @add_col; EXECUTE stmt; DEALLOCATE PREPARE stmt;"
# Public visitor chats (token hash only; raw token stays with the visitor).
run_sql "CREATE TABLE IF NOT EXISTS guest_chats (id INT AUTO_INCREMENT PRIMARY KEY, property_id INT NOT NULL, guest_name VARCHAR(255) NOT NULL, guest_email VARCHAR(255) NOT NULL, token_hash CHAR(64) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE, UNIQUE KEY uniq_guest_chat_token (token_hash), INDEX idx_guest_chat_property (property_id), INDEX idx_guest_chat_activity (last_activity)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
run_sql "ALTER TABLE guest_chats ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL AFTER last_activity;"
run_sql "CREATE TABLE IF NOT EXISTS guest_chat_messages (id INT AUTO_INCREMENT PRIMARY KEY, chat_id INT NOT NULL, sender_type ENUM('guest','staff') NOT NULL, sender_id INT DEFAULT NULL, body TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, read_at TIMESTAMP NULL DEFAULT NULL, guest_read_at TIMESTAMP NULL DEFAULT NULL, FOREIGN KEY (chat_id) REFERENCES guest_chats(id) ON DELETE CASCADE, FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL, INDEX idx_guest_chat_messages_chat (chat_id, id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
# Additional access links stay valid; only their hashes are kept. Backfill
# existing chats with their original link so old bookmarks keep working.
run_sql "CREATE TABLE IF NOT EXISTS guest_chat_tokens (id INT AUTO_INCREMENT PRIMARY KEY, chat_id INT NOT NULL, token_hash CHAR(64) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (chat_id) REFERENCES guest_chats(id) ON DELETE CASCADE, UNIQUE KEY uniq_guest_chat_token_hash (token_hash), INDEX idx_guest_chat_tokens_chat (chat_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
# Backfill the legacy guest_chats.token_hash column, then remove it:
# guest_chat_tokens is now the single source of truth for access links.
run_sql "SET @col_count = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guest_chats' AND COLUMN_NAME = 'token_hash'); SET @backfill = IF(@col_count > 0, 'INSERT IGNORE INTO guest_chat_tokens (chat_id, token_hash, created_at) SELECT id, token_hash, created_at FROM guest_chats', 'SELECT 1'); PREPARE stmt FROM @backfill; EXECUTE stmt; DEALLOCATE PREPARE stmt; SET @drop_col = IF(@col_count > 0, 'ALTER TABLE guest_chats DROP COLUMN token_hash', 'SELECT 1'); PREPARE stmt2 FROM @drop_col; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;"
# One guest (by email) can have a conversation per property. Their hub link
# lists them all; hub tokens follow the same hash-only, additive model.
run_sql "CREATE TABLE IF NOT EXISTS guest_visitors (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL, name VARCHAR(120) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uniq_guest_visitor_email (email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
run_sql "CREATE TABLE IF NOT EXISTS guest_visitor_tokens (id INT AUTO_INCREMENT PRIMARY KEY, guest_id INT NOT NULL, token_hash CHAR(64) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (guest_id) REFERENCES guest_visitors(id) ON DELETE CASCADE, UNIQUE KEY uniq_guest_visitor_token_hash (token_hash), INDEX idx_guest_visitor_tokens_guest (guest_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
run_sql "INSERT IGNORE INTO guest_visitors (email, name, created_at) SELECT LOWER(guest_email), MAX(guest_name), MIN(created_at) FROM guest_chats GROUP BY LOWER(guest_email);"
run_sql "CREATE TABLE IF NOT EXISTS payments (id INT AUTO_INCREMENT PRIMARY KEY, property_tenant_id INT NOT NULL, amount DECIMAL(10,2) NOT NULL, payment_date DATE NOT NULL, payment_method VARCHAR(50) DEFAULT NULL, reference VARCHAR(100) DEFAULT NULL, notes TEXT, recorded_by INT NOT NULL, archived_at TIMESTAMP NULL DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY (property_tenant_id) REFERENCES property_tenant(id) ON DELETE CASCADE, FOREIGN KEY (recorded_by) REFERENCES users(id), INDEX idx_property_tenant (property_tenant_id), INDEX idx_payment_date (payment_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
run_sql "ALTER TABLE payments ADD COLUMN is_security_deposit TINYINT(1) DEFAULT 0 AFTER notes;"
run_sql "ALTER TABLE property_tenant ADD COLUMN lease_type VARCHAR(50) DEFAULT NULL AFTER move_out_date;"
run_sql "ALTER TABLE property_tenant ADD COLUMN emergency_contact_name VARCHAR(255) DEFAULT NULL AFTER lease_type;"
run_sql "ALTER TABLE property_tenant ADD COLUMN emergency_contact_phone VARCHAR(20) DEFAULT NULL AFTER emergency_contact_name;"
run_sql "CREATE TABLE IF NOT EXISTS tenant_applications (id INT AUTO_INCREMENT PRIMARY KEY, property_id INT DEFAULT NULL, status VARCHAR(20) DEFAULT 'new', data LONGTEXT NOT NULL, notes TEXT, archived_at TIMESTAMP NULL DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
run_sql "ALTER TABLE tenant_applications ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL AFTER notes;"
run_sql "CREATE TABLE IF NOT EXISTS auth_rate_limits (cache_key VARCHAR(191) PRIMARY KEY, attempts INT NOT NULL DEFAULT 0, window_start INT NOT NULL, last_at DATETIME NOT NULL, INDEX idx_window (window_start)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
# Batch markers let a restore undo only the rows archived by that one action.
run_sql "ALTER TABLE properties ADD COLUMN archived_batch VARCHAR(32) DEFAULT NULL AFTER archived_at;"
run_sql "ALTER TABLE property_tenant ADD COLUMN archived_batch VARCHAR(32) DEFAULT NULL AFTER moved_out_at;"
run_sql "ALTER TABLE payments ADD COLUMN archived_batch VARCHAR(32) DEFAULT NULL AFTER archived_at;"
run_sql "ALTER TABLE leases ADD COLUMN archived_batch VARCHAR(32) DEFAULT NULL AFTER archived_at;"
run_sql "ALTER TABLE tickets ADD COLUMN archived_batch VARCHAR(32) DEFAULT NULL AFTER archived_at;"
# Only re-seed permissions if not in custom mode (preserves user customizations on updates)
PERM_MODE=$($MYSQL_CMD -N -e "SELECT \`value\` FROM settings WHERE \`key\` = 'permissions_mode';" 2>/dev/null || $MYSQL_ROOT -N -e "SELECT \`value\` FROM settings WHERE \`key\` = 'permissions_mode';" 2>/dev/null || echo "")
if [ "$PERM_MODE" != "custom" ]; then
run_sql "DELETE FROM role_permissions WHERE 1=1;"
    run_sql "INSERT INTO role_permissions (role, permission) VALUES
    ('landlord','home.access'),('landlord','properties.access'),('landlord','properties.create'),('landlord','properties.edit'),('landlord','properties.archive'),('landlord','properties.restore'),('landlord','photos.create'),('landlord','photos.edit'),('landlord','photos.download'),('landlord','photos.delete'),('landlord','tenants.access'),('landlord','tenants.create'),('landlord','tenants.edit'),('landlord','tenants.archive'),('landlord','tenants.restore'),('landlord','leases.access'),('landlord','leases.create'),('landlord','leases.archive'),('landlord','leases.restore'),('landlord','tickets.access'),('landlord','tickets.create'),('landlord','tickets.assign'),('landlord','tickets.update_status'),('landlord','tickets.archive'),('landlord','tickets.restore'),('landlord','tickets.comment'),('landlord','tickets.internal_comment'),('landlord','tickets.upload_photos'),('landlord','tickets.download_photos'),('landlord','staff.access'),('landlord','staff.create'),('landlord','staff.edit'),('landlord','staff.archive'),('landlord','staff.restore'),('landlord','resources.access'),('landlord','resources.create'),('landlord','resources.edit'),('landlord','resources.delete'),('landlord','calendar.access'),('landlord','documents.download'),('landlord','ai_assistant.access'),('landlord','rents.access'),('landlord','rents.payments.create'),('landlord','rents.payments.edit'),('landlord','rents.payments.archive'),('landlord','rents.payments.restore'),('landlord','applications.view'),('landlord','applications.edit'),('landlord','applications.archive'),('landlord','applications.restore'),('landlord','guest_chats.access'),('landlord','guest_chats.archive'),('landlord','guest_chats.restore'),
    ('property_manager','home.access'),('property_manager','properties.access'),('property_manager','properties.edit'),('property_manager','photos.create'),('property_manager','photos.edit'),('property_manager','photos.download'),('property_manager','photos.delete'),('property_manager','tenants.access'),('property_manager','tenants.create'),('property_manager','tenants.edit'),('property_manager','leases.access'),('property_manager','leases.create'),('property_manager','tickets.access'),('property_manager','tickets.create'),('property_manager','tickets.assign'),('property_manager','tickets.update_status'),('property_manager','tickets.comment'),('property_manager','tickets.internal_comment'),('property_manager','tickets.upload_photos'),('property_manager','tickets.download_photos'),('property_manager','staff.access'),('property_manager','resources.access'),('property_manager','resources.create'),('property_manager','resources.edit'),('property_manager','resources.delete'),('property_manager','calendar.access'),('property_manager','documents.download'),('property_manager','ai_assistant.access'),('property_manager','rents.access'),('property_manager','rents.payments.create'),('property_manager','applications.view'),('property_manager','applications.edit'),('property_manager','guest_chats.access'),
('maintenance','home.access'),('maintenance','properties.access'),('maintenance','tenants.access'),('maintenance','staff.access'),('maintenance','tickets.access'),('maintenance','tickets.create'),('maintenance','tickets.assign'),('maintenance','tickets.update_status'),('maintenance','tickets.comment'),('maintenance','tickets.internal_comment'),('maintenance','tickets.upload_photos'),('maintenance','tickets.download_photos'),
('tenant','home.access'),('tenant','properties.access'),('tenant','tenants.access'),('tenant','tickets.access'),('tenant','tickets.create'),('tenant','tickets.comment'),('tenant','tickets.upload_photos'),('tenant','tickets.download_photos'),('tenant','resources.access'),('tenant','leases.access'),('tenant','documents.download'),('tenant','rents.access');"
fi

# Backfill company_user for property_manager/maintenance without entries
run_sql "INSERT IGNORE INTO company_user (company_id, user_id) SELECT c.id, u.id FROM users u CROSS JOIN companies c WHERE u.role IN ('property_manager','maintenance') AND NOT EXISTS (SELECT 1 FROM company_user cu WHERE cu.user_id = u.id);"

# ── Schema integrity ─────────────────────────────────────────────────────────
# Drop dangling application references, then enforce the FK. Existing rows
# must be cleaned first or the constraint cannot be added.
run_sql "UPDATE tenant_applications ta LEFT JOIN properties p ON p.id = ta.property_id SET ta.property_id = NULL WHERE ta.property_id IS NOT NULL AND p.id IS NULL; SET @app_fk = (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_applications' AND COLUMN_NAME = 'property_id' AND REFERENCED_TABLE_NAME = 'properties'); SET @sql = IF(@app_fk = 0, 'ALTER TABLE tenant_applications ADD CONSTRAINT fk_tenant_applications_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;"

# Normalise legacy ticket values before adding CHECK constraints, otherwise
# the migration fails on installs that already contain invalid statuses.
run_sql "UPDATE tickets SET status = 'open' WHERE status IS NULL OR status NOT IN ('open','in_progress','awaiting_parts','awaiting_contractor','resolved','closed'); UPDATE tickets SET priority = 'medium' WHERE priority IS NULL OR priority NOT IN ('low','medium','high','emergency'); SET @status_chk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND CONSTRAINT_NAME = 'chk_tickets_status'); SET @sql = IF(@status_chk = 0, 'ALTER TABLE tickets ADD CONSTRAINT chk_tickets_status CHECK (status IN (\'open\',\'in_progress\',\'awaiting_parts\',\'awaiting_contractor\',\'resolved\',\'closed\'))', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt; SET @priority_chk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND CONSTRAINT_NAME = 'chk_tickets_priority'); SET @sql = IF(@priority_chk = 0, 'ALTER TABLE tickets ADD CONSTRAINT chk_tickets_priority CHECK (priority IN (\'low\',\'medium\',\'high\',\'emergency\'))', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;"

# Documents: allow a deleted uploader to be cleared instead of blocking the
# delete. Discover the auto-generated FK name so this works on old installs.
run_sql "SET @doc_fk = (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'uploaded_by' AND REFERENCED_TABLE_NAME = 'users' LIMIT 1); SET @doc_rule = (SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND CONSTRAINT_NAME = @doc_fk); SET @doc_nullable = (SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'uploaded_by'); SET @doc_change = (@doc_fk IS NULL OR @doc_rule <> 'SET NULL'); SET @sql = IF(@doc_nullable = 'NO', 'ALTER TABLE documents MODIFY uploaded_by INT NULL', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt; SET @sql = IF(@doc_change AND @doc_fk IS NOT NULL, CONCAT('ALTER TABLE documents DROP FOREIGN KEY ', @doc_fk), 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt; SET @sql = IF(@doc_change, 'ALTER TABLE documents ADD CONSTRAINT fk_documents_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;"

# Update version
APP_VER=$( (git -c safe.directory="$REPO_DIR" -C "$REPO_DIR" describe --tags 2>/dev/null || git -c safe.directory="$REPO_DIR" -C "$REPO_DIR" log --oneline -1 --format=%h 2>/dev/null || echo "0.0.0") | sed 's/^v//' )
run_sql "INSERT INTO settings (\`key\`, \`value\`) VALUES ('app_version', '${APP_VER}') ON DUPLICATE KEY UPDATE \`value\` = '${APP_VER}';"

if [ "$MIGRATION_FAILURES" -gt 0 ]; then
    echo "${MIGRATION_FAILURES} migration statement(s) failed — check the database configuration and logs." >&2
    exit 1
fi
