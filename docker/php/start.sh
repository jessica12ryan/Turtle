#!/bin/bash
set -e

cd /var/www/html

# Use the same DB settings as the application/migrations (custom passwords must
# work for the readiness check too). Only DB_* keys are read from .env.
if [ -f .env ]; then
    for key in DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD DB_SOCKET; do
        value=$(grep -E "^${key}=" .env 2>/dev/null | head -1 | cut -d= -f2-)
        value=$(printf '%s' "$value" | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'\$//")
        if [ -n "$value" ]; then
            export "${key}=${value}"
        fi
    done
fi

# Wait for MySQL, but never forever: a misconfigured DB must fail loudly so
# Compose/CI sees an unhealthy container instead of a silent, idle app.
DB_WAIT_SECONDS="${DB_WAIT_SECONDS:-120}"
if [ -n "${DB_SOCKET:-}" ]; then
    DB_TARGET="socket ${DB_SOCKET}"
else
    DB_TARGET="${DB_HOST:-mysql}:${DB_PORT:-3306}"
fi
echo ">>> Waiting for MySQL at ${DB_TARGET} (timeout ${DB_WAIT_SECONDS}s)..."
DB_DEADLINE=$(( $(date +%s) + DB_WAIT_SECONDS ))
while true; do
    if DB_HOST="${DB_HOST:-mysql}" DB_PORT="${DB_PORT:-3306}" \
          DB_DATABASE="${DB_DATABASE:-turtle}" DB_USERNAME="${DB_USERNAME:-turtle}" \
          DB_PASSWORD="${DB_PASSWORD:-turtle}" DB_SOCKET="${DB_SOCKET:-}" php -r '
        $socket = (string) getenv("DB_SOCKET");
        $dsn = $socket !== ""
            ? "mysql:unix_socket={$socket};dbname=" . getenv("DB_DATABASE")
            : "mysql:host=" . getenv("DB_HOST") . ";port=" . getenv("DB_PORT") . ";dbname=" . getenv("DB_DATABASE");
        new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD"));
    ' >/dev/null 2>&1; then
        break
    fi
    if [ "$(date +%s)" -ge "$DB_DEADLINE" ]; then
        echo ">>> MySQL at ${DB_TARGET} did not become available within ${DB_WAIT_SECONDS}s. Check DB_* configuration and database logs." >&2
        exit 1
    fi
    sleep 1
done

# Run schema/migrations — first boot creates tables, every boot runs incremental migrations (idempotent)
# TURTLE_FORCE_MIGRATIONS is used by the throwaway E2E stack so a stale
# .db_initialized marker on the host cannot skip setup of the fresh test DB.
if [ -f database/schema.sql ]; then
    if [ ! -f storage/.db_initialized ] || [ "${TURTLE_FORCE_MIGRATIONS:-0}" = "1" ]; then
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
# MySQL writes its error log as uid 999; make it readable by the web server so
# the logging settings page can display it.
chmod 644 /var/lib/mysql/mysql-error.log 2>/dev/null || true

# Start queue worker in background
php -r "
require '/var/www/html/www/autoload.php';
require '/var/www/html/www/functions.php';
\App\Core\Logger::apply();
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
