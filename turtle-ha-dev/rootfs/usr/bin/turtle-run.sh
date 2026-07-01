#!/usr/bin/env bashio
set -e

TURTLE_DIR=/var/www/turtle
DATA_DIR=/data

# ── Read HA options ────────────────────────────────────────────────────────────
DB_PASSWORD=$(bashio::config 'db_password')
APP_URL=$(bashio::config 'app_url')
MAIL_HOST=$(bashio::config 'mail_host')
MAIL_PORT=$(bashio::config 'mail_port')
MAIL_USER=$(bashio::config 'mail_username')
MAIL_PASS=$(bashio::config 'mail_password')
MAIL_FROM=$(bashio::config 'mail_from_address')
MAILPIT_PORT=$(bashio::config 'mailpit_port')

if [ -z "$APP_URL" ]; then
    APP_URL="http://homeassistant.local:8099"
fi

# ── Persistent directories ─────────────────────────────────────────────────────
mkdir -p \
    "${DATA_DIR}/mysql" \
    "${DATA_DIR}/uploads/leases" \
    "${DATA_DIR}/uploads/property_photos" \
    "${DATA_DIR}/logs" \
    "${DATA_DIR}/framework"

# ── MariaDB: initialise data dir on first boot ────────────────────────────────
if [ ! -d "${DATA_DIR}/mysql/mysql" ]; then
    bashio::log.info "Initialising MariaDB data directory..."
    mysql_install_db --user=mysql --datadir="${DATA_DIR}/mysql" --skip-test-db > /dev/null
fi

# ── MariaDB: start only if not already running ────────────────────────────────
if ! mysqladmin ping --socket=/tmp/mysql.sock --silent 2>/dev/null; then
    bashio::log.info "Starting MariaDB..."
    mysqld_safe \
        --datadir="${DATA_DIR}/mysql" \
        --socket=/tmp/mysql.sock \
        --pid-file=/tmp/mysqld.pid \
        --user=mysql \
        --bind-address=127.0.0.1 \
        --port=3306 &

    bashio::log.info "Waiting for MariaDB..."
    until mysqladmin ping --socket=/tmp/mysql.sock --silent 2>/dev/null; do
        sleep 1
    done
else
    bashio::log.info "MariaDB already running, skipping start."
fi

# Create DB + user if missing (socket + TCP user for PHP compatibility)
mysql --socket=/tmp/mysql.sock <<SQL
CREATE DATABASE IF NOT EXISTS turtle CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'turtle'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
CREATE USER IF NOT EXISTS 'turtle'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON turtle.* TO 'turtle'@'localhost';
GRANT ALL PRIVILEGES ON turtle.* TO 'turtle'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

# ── Pull latest master (before schema load) ───────────────────────────────────
bashio::log.info "Pulling latest code from GitHub..."
git config --global --add safe.directory "${TURTLE_DIR}" 2>/dev/null || true
git -C "${TURTLE_DIR}" pull --ff-only origin master 2>/dev/null || \
    bashio::log.warning "Git pull failed — using cached code. Schema may be outdated."

# ── Start Mailpit ──────────────────────────────────────────────────────────────
bashio::log.info "Starting Mailpit on port ${MAILPIT_PORT}..."
mkdir -p "${DATA_DIR}/mailpit"
/usr/local/bin/mailpit \
    --smtp "0.0.0.0:${MAILPIT_PORT}" \
    --listen "0.0.0.0:8025" \
    --data-dir "${DATA_DIR}/mailpit" &
bashio::log.info "Mailpit started (SMTP :${MAILPIT_PORT}, UI :8025)"

# ── Derive mail defaults ───────────────────────────────────────────────────────
if [ -z "$MAIL_HOST" ]; then
    MAIL_HOST="127.0.0.1"
    MAIL_PORT=$MAILPIT_PORT
    bashio::log.info "mail_host empty — defaulting to local Mailpit (127.0.0.1:${MAILPIT_PORT})"
fi
if [ -z "$MAIL_FROM" ]; then
    MAIL_FROM="noreply@turtle.local"
fi

# ── Write .env ────────────────────────────────────────────────────────────────
bashio::log.info "Writing .env..."
cat > "${TURTLE_DIR}/.env" <<ENV
APP_NAME=Turtle
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=${APP_URL}

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=turtle
DB_USERNAME=turtle
DB_PASSWORD=${DB_PASSWORD}
DB_SOCKET=/tmp/mysql.sock

SESSION_DRIVER=database
SESSION_LIFETIME=120

MAIL_MAILER=smtp
MAIL_HOST=${MAIL_HOST}
MAIL_PORT=${MAIL_PORT}
MAIL_USERNAME=${MAIL_USER}
MAIL_PASSWORD=${MAIL_PASS}
MAIL_FROM_ADDRESS=${MAIL_FROM}
MAIL_FROM_NAME=Turtle
ENV

# Export env vars for Apache/PHP and ensure .env is readable by apache user
set -a
. "${TURTLE_DIR}/.env"
set +a
chmod 644 "${TURTLE_DIR}/.env"

# ── Symlink persistent storage into app ───────────────────────────────────────
rm -rf "${TURTLE_DIR}/storage/uploads"
ln -sf "${DATA_DIR}/uploads"   "${TURTLE_DIR}/storage/uploads"
rm -rf "${TURTLE_DIR}/storage/logs"
ln -sf "${DATA_DIR}/logs"      "${TURTLE_DIR}/storage/logs"
rm -rf "${TURTLE_DIR}/storage/framework"
ln -sf "${DATA_DIR}/framework" "${TURTLE_DIR}/storage/framework"

# ── Run database schema directly (before patched migrate.sh) ──────────────────
# Run schema.sql directly so errors are visible
bashio::log.info "Loading database schema..."
if mysql --socket=/tmp/mysql.sock -u root turtle < "${TURTLE_DIR}/database/schema.sql"; then
    bashio::log.info "Schema loaded successfully."
else
    bashio::log.error "Schema loading FAILED — check schema.sql for errors."
fi

# Verify settings table was created
if echo "SELECT 1 FROM settings LIMIT 1" | mysql --socket=/tmp/mysql.sock -u root turtle >/dev/null 2>&1; then
    bashio::log.info "Settings table exists — schema is complete."
else
    bashio::log.error "Settings table is MISSING — schema.sql may have failed partway through."
    mysql --socket=/tmp/mysql.sock -u root turtle -e "SHOW TABLES;" 2>&1 | bashio::log.info
fi

# ── Run migrations ────────────────────────────────────────────────────────────
# migrate.sh hardcodes /var/www/html and -h mysql, so we patch it on the fly
bashio::log.info "Running incremental migrations..."
PATCHED_MIGRATE=$(mktemp)
sed \
    -e 's|cd /var/www/html|cd '"${TURTLE_DIR}"'|g' \
    -e 's|mysql -h mysql -u turtle -pturtle turtle|mysql --socket=/tmp/mysql.sock -u turtle -p'"${DB_PASSWORD}"' turtle|g' \
    -e 's|mysql -h mysql -u root -proot turtle|mysql --socket=/tmp/mysql.sock -u root turtle|g' \
    "${TURTLE_DIR}/database/migrate.sh" > "${PATCHED_MIGRATE}"
chmod +x "${PATCHED_MIGRATE}"
bash "${PATCHED_MIGRATE}"
rm -f "${PATCHED_MIGRATE}"
touch "${DATA_DIR}/.db_initialized"

# ── Dev defaults ──────────────────────────────────────────────────────────────
bashio::log.info "Setting dev defaults (update_channel=development)..."
mysql --socket=/tmp/mysql.sock -u root turtle -e "INSERT INTO settings (\`key\`, \`value\`) VALUES ('update_channel', 'development') ON DUPLICATE KEY UPDATE \`value\` = 'development';"

# ── Permissions ───────────────────────────────────────────────────────────────
chown -R apache:apache "${TURTLE_DIR}/www/assets" 2>/dev/null || true
chmod -R 775 "${DATA_DIR}/uploads" "${DATA_DIR}/logs" "${DATA_DIR}/framework"
# Make app dir writable so in-app updater can modify files
chmod -R a+w "${TURTLE_DIR}" 2>/dev/null || true

# ── Shutdown handler ──────────────────────────────────────────────────────────
_cleanup() {
    bashio::log.info "Shutting down..."
    kill -TERM "${APACHE_PID}" 2>/dev/null || true
    mysqladmin --socket=/tmp/mysql.sock shutdown 2>/dev/null || true
    wait
    bashio::log.info "Shutdown complete."
}
trap '_cleanup' SIGTERM SIGHUP

# ── Start Apache ──────────────────────────────────────────────────────────────
bashio::log.info "Turtle (Dev) is ready at port 8099"
httpd -D FOREGROUND &
APACHE_PID=$!
wait
