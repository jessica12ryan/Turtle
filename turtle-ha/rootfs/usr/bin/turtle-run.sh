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
MAILPIT_UI_PORT=$(bashio::config 'mailpit_ui_port')

if [ -z "$APP_URL" ]; then
    APP_URL="http://homeassistant.local:80"
fi

# ── Persistent directories ─────────────────────────────────────────────────────
mkdir -p \
    "${DATA_DIR}/mysql" \
    "${DATA_DIR}/uploads/leases" \
    "${DATA_DIR}/uploads/property_photos" \
    "${DATA_DIR}/uploads/application_photos" \
    "${DATA_DIR}/uploads/ticket_files" \
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

# ── Start Mailpit ──────────────────────────────────────────────────────────────
# A backgrounded mailpit is not supervised by s6, so a previous crashed boot
# can leave an orphan holding the ports — reap it or the new instance dies.
for _mp_exe in /proc/[0-9]*/exe; do
    if [ "$(readlink "${_mp_exe}" 2>/dev/null)" = "/usr/local/bin/mailpit" ]; then
        _mp_pid="${_mp_exe#/proc/}"
        _mp_pid="${_mp_pid%/exe}"
        kill -TERM "${_mp_pid}" 2>/dev/null || true
    fi
done
unset _mp_exe _mp_pid
sleep 1
# Validate the configured UI port (default preserves historical behaviour).
case "${MAILPIT_UI_PORT}" in
    ''|*[!0-9]*|??????*)
        bashio::log.warning "Invalid mailpit_ui_port — falling back to 8025."
        MAILPIT_UI_PORT=8025
        ;;
esac
if [ "${MAILPIT_UI_PORT}" -lt 1 ] || [ "${MAILPIT_UI_PORT}" -gt 65535 ]; then
    bashio::log.warning "mailpit_ui_port out of range — falling back to 8025."
    MAILPIT_UI_PORT=8025
fi
# Pick a free UI port: configured port, then the next two. A busy UI port is
# non-fatal — SMTP delivery must still work, so as a last resort bind the UI
# to an OS-assigned ephemeral port (effectively UI-unavailable, SMTP intact).
_port_busy() {
    php -r '$s = @fsockopen("127.0.0.1", (int) $argv[1], $e, $m, 1); if ($s) { fclose($s); exit(0); } exit(1);' "$1" 2>/dev/null
}
MAILPIT_UI_ACTUAL=""
_mp_candidate="${MAILPIT_UI_PORT}"
_mp_tries=0
while [ "${_mp_tries}" -lt 3 ]; do
    if _port_busy "${_mp_candidate}"; then
        bashio::log.warning "Port ${_mp_candidate} is busy — trying next port for Mailpit UI."
        _mp_candidate=$((_mp_candidate + 1))
        _mp_tries=$((_mp_tries + 1))
    else
        MAILPIT_UI_ACTUAL="${_mp_candidate}"
        break
    fi
done
bashio::log.info "Starting Mailpit on port ${MAILPIT_PORT}..."
mkdir -p "${DATA_DIR}/mailpit"
if [ -z "${MAILPIT_UI_ACTUAL}" ]; then
    bashio::log.warning "No free Mailpit UI port near ${MAILPIT_UI_PORT} — starting SMTP-only (UI unavailable)."
    # Port 0 asks the OS for a free ephemeral port; UI effectively unavailable.
    /usr/local/bin/mailpit \
        --smtp "127.0.0.1:${MAILPIT_PORT}" \
        --listen "127.0.0.1:0" \
        --database "${DATA_DIR}/mailpit/mailpit.db" &
else
    /usr/local/bin/mailpit \
        --smtp "127.0.0.1:${MAILPIT_PORT}" \
        --listen "0.0.0.0:${MAILPIT_UI_ACTUAL}" \
        --database "${DATA_DIR}/mailpit/mailpit.db" &
fi
unset _mp_candidate _mp_tries
bashio::log.info "Mailpit started (SMTP :${MAILPIT_PORT}, UI :${MAILPIT_UI_ACTUAL:-unavailable})"

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
# Generate APP_KEY if missing or effectively empty (a bare "base64:" prefix
# from a failed keygen must NOT be reused — it would persist a null key).
if [ ! -f "${TURTLE_DIR}/.env" ] || ! grep -Eq '^APP_KEY=base64:.{20,}' "${TURTLE_DIR}/.env" 2>/dev/null; then
    if command -v openssl >/dev/null 2>&1; then
        GENERATED_KEY="base64:$(openssl rand -base64 32 2>/dev/null | tr -d '\n')"
    else
        GENERATED_KEY=""
    fi
    if [ "${#GENERATED_KEY}" -lt 27 ]; then
        # openssl missing or failed — fall back to PHP CSPRNG (always present)
        GENERATED_KEY=$(php -r "echo 'base64:' . base64_encode(random_bytes(32));" 2>/dev/null)
    fi
else
    GENERATED_KEY=$(grep '^APP_KEY=' "${TURTLE_DIR}/.env" | cut -d= -f2-)
fi
cat > "${TURTLE_DIR}/.env" <<ENV
APP_NAME=Turtle
APP_ENV=production
APP_KEY=${GENERATED_KEY}
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
# Guarded: a migration failure must not kill the boot (set -e) — warn and continue.
bash "${PATCHED_MIGRATE}" || bashio::log.warning "Migrations exited non-zero — continuing boot; check add-on log."
rm -f "${PATCHED_MIGRATE}"

# ── Sync mail settings to database ────────────────────────────────────────────
# The Mailer reads DB first, so push the add-on config values into the DB so
# they take effect even after the setup wizard or a restore.
bashio::log.info "Syncing mail settings to database..."
# Guarded: mail sync is best-effort — the Mailer falls back to .env values.
mysql --socket=/tmp/mysql.sock -u root turtle <<SQL || bashio::log.warning "Mail settings sync failed — continuing boot."
INSERT INTO settings (\`key\`, \`value\`) VALUES ('mail_host', '${MAIL_HOST}')
    ON DUPLICATE KEY UPDATE \`value\` = '${MAIL_HOST}';
INSERT INTO settings (\`key\`, \`value\`) VALUES ('mail_port', '${MAIL_PORT}')
    ON DUPLICATE KEY UPDATE \`value\` = '${MAIL_PORT}';
INSERT INTO settings (\`key\`, \`value\`) VALUES ('mail_username', '${MAIL_USER}')
    ON DUPLICATE KEY UPDATE \`value\` = '${MAIL_USER}';
INSERT INTO settings (\`key\`, \`value\`) VALUES ('mail_password', '${MAIL_PASS}')
    ON DUPLICATE KEY UPDATE \`value\` = '${MAIL_PASS}';
INSERT INTO settings (\`key\`, \`value\`) VALUES ('mail_from_address', '${MAIL_FROM}')
    ON DUPLICATE KEY UPDATE \`value\` = '${MAIL_FROM}';
SQL

# ── Permissions ───────────────────────────────────────────────────────────────
# Least-privilege: only persistent data dirs writable, not entire code
chmod -R 775 "${DATA_DIR}/uploads" "${DATA_DIR}/logs" "${DATA_DIR}/framework"
chown -R root:root "${TURTLE_DIR}" 2>/dev/null || true
chmod 755 "${TURTLE_DIR}" 2>/dev/null || true
# Allow updater to write .git and storage only
chmod -R 775 "${TURTLE_DIR}/.git" "${TURTLE_DIR}/storage" "${TURTLE_DIR}/www/assets/uploads" 2>/dev/null || true

# Allow in-app git pull updater to work
git config --global --add safe.directory "${TURTLE_DIR}" 2>/dev/null || true

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
bashio::log.info "Turtle is ready at port 80"
httpd -D FOREGROUND &
APACHE_PID=$!
wait
