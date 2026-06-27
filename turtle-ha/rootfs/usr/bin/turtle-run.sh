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
    until mysqladmin ping -h 127.0.0.1 -P 3306 --silent 2>/dev/null; do
        sleep 1
    done
else
    bashio::log.info "MariaDB already running, skipping start."
fi

# Create DB + user if missing
mysql --socket=/tmp/mysql.sock <<SQL
CREATE DATABASE IF NOT EXISTS turtle CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'turtle'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON turtle.* TO 'turtle'@'localhost';
FLUSH PRIVILEGES;
SQL

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

# ── Symlink persistent storage into app ───────────────────────────────────────
rm -rf "${TURTLE_DIR}/storage/uploads"
ln -sf "${DATA_DIR}/uploads"   "${TURTLE_DIR}/storage/uploads"
rm -rf "${TURTLE_DIR}/storage/logs"
ln -sf "${DATA_DIR}/logs"      "${TURTLE_DIR}/storage/logs"
rm -rf "${TURTLE_DIR}/storage/framework"
ln -sf "${DATA_DIR}/framework" "${TURTLE_DIR}/storage/framework"

# ── Run migrations ────────────────────────────────────────────────────────────
# migrate.sh hardcodes /var/www/html and -h mysql, so we patch it on the fly
bashio::log.info "Running database migrations..."
PATCHED_MIGRATE=$(mktemp)
sed \
    -e 's|cd /var/www/html|cd '"${TURTLE_DIR}"'|g' \
    -e 's|mysql -h mysql -u turtle -pturtle turtle|mysql --socket=/tmp/mysql.sock -u turtle -p'"${DB_PASSWORD}"' turtle|g' \
    -e 's|mysql -h mysql -u root -proot turtle|mysql --socket=/tmp/mysql.sock -u root turtle|g' \
    "${TURTLE_DIR}/database/migrate.sh" > "${PATCHED_MIGRATE}"
chmod +x "${PATCHED_MIGRATE}"
bash "${PATCHED_MIGRATE}"
rm -f "${PATCHED_MIGRATE}"

# ── Permissions ───────────────────────────────────────────────────────────────
chown -R apache:apache "${TURTLE_DIR}/www/assets" 2>/dev/null || true
chmod -R 775 "${DATA_DIR}/uploads" "${DATA_DIR}/logs" "${DATA_DIR}/framework"

# Allow in-app git pull updater to work
git config --global --add safe.directory "${TURTLE_DIR}" 2>/dev/null || true

# ── Start Apache ──────────────────────────────────────────────────────────────
bashio::log.info "Turtle is ready at port 8099"
exec httpd -D FOREGROUND
