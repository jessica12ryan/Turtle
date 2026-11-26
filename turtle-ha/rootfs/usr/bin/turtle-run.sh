#!/usr/bin/env bashio
set -e

# New files (uploads, git pull, logs) should stay group-writable.
umask 0002

TURTLE_DIR=/var/www/turtle
DATA_DIR=/data
WEB_GROUP=$(id -gn apache 2>/dev/null || echo www-data)

# Operator options are data, never shell code. Strip CR/LF so a value cannot
# inject extra KEY= lines into the generated .env or break its parsing.
env_safe() {
    local value="$1"
    value="${value//$'\r'/}"
    value="${value//$'\n'/}"
    printf '%s' "${value}"
}

# ── Read HA options ────────────────────────────────────────────────────────────
DB_PASSWORD=$(env_safe "$(bashio::config 'db_password')")
APP_URL=$(env_safe "$(bashio::config 'app_url')")
MAIL_HOST=$(env_safe "$(bashio::config 'mail_host')")
MAIL_PORT=$(env_safe "$(bashio::config 'mail_port')")
MAIL_USER=$(env_safe "$(bashio::config 'mail_username')")
MAIL_PASS=$(env_safe "$(bashio::config 'mail_password')")
MAIL_FROM=$(env_safe "$(bashio::config 'mail_from_address')")
MAIL_AUTH_MODE=$(env_safe "$(bashio::config 'mail_auth_mode')")
MAIL_OAUTH_CLIENT_ID=$(env_safe "$(bashio::config 'mail_oauth_client_id')")
MAIL_OAUTH_CLIENT_SECRET=$(env_safe "$(bashio::config 'mail_oauth_client_secret')")
MAIL_OAUTH_REFRESH_TOKEN=$(env_safe "$(bashio::config 'mail_oauth_refresh_token')")
MAIL_OAUTH_EMAIL=$(env_safe "$(bashio::config 'mail_oauth_email')")
MAILPIT_PORT=$(env_safe "$(bashio::config 'mailpit_port')")
MAILPIT_UI_PORT=$(env_safe "$(bashio::config 'mailpit_ui_port')")

# Track whether a value came from the operator or from the add-on default. The
# sync script only overwrites UI-configured mail settings for explicit values.
MAIL_HOST_EXPLICIT=0
[ -n "${MAIL_HOST}" ] && MAIL_HOST_EXPLICIT=1
# The configured port only has meaning when a remote host was configured;
# otherwise it is replaced with the bundled Mailpit port below.
MAIL_PORT_EXPLICIT="${MAIL_HOST_EXPLICIT}"
MAIL_FROM_EXPLICIT=0
if [ -n "${MAIL_FROM}" ] && [ "${MAIL_FROM}" != "noreply@turtle.local" ]; then
    MAIL_FROM_EXPLICIT=1
fi

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
        --port=3306 \
        --log-error="${DATA_DIR}/mysql/mariadb-error.log" &

    bashio::log.info "Waiting for MariaDB..."
    until mysqladmin ping --socket=/tmp/mysql.sock --silent 2>/dev/null; do
        sleep 1
    done
else
    bashio::log.info "MariaDB already running, skipping start."
fi
# MariaDB creates this as uid mysql; grant the web group read access so the
# Settings -> Logging page can show the error log without world access.
chgrp "${WEB_GROUP}" "${DATA_DIR}/mysql/mariadb-error.log" 2>/dev/null || true
chmod 640 "${DATA_DIR}/mysql/mariadb-error.log" 2>/dev/null || true

# Create DB + user and apply the configured password. The password travels via
# the environment and is escaped as a SQL literal by the PHP helper; embedding
# it in a shell heredoc broke on quotes and allowed SQL injection.
bashio::log.info "Configuring MariaDB database and user..."
export DB_PASSWORD DB_SOCKET=/tmp/mysql.sock
if php /usr/bin/setup-db.php; then
    bashio::log.info "Database and user ready."
else
    bashio::log.error "Database setup failed — aborting boot."
    exit 1
fi

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
# Mailpit itself only listens on loopback. Apache publishes the UI on the
# add-on's UI port and restricts it to loopback/private networks.
MAILPIT_UI_INTERNAL=18025

# Validate the configured public UI port (default preserves historical behaviour).
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
if [ "${MAILPIT_UI_PORT}" = "80" ] || [ "${MAILPIT_UI_PORT}" = "${MAILPIT_PORT}" ]; then
    bashio::log.warning "mailpit_ui_port conflicts with another Turtle port — falling back to 8025."
    MAILPIT_UI_PORT=8025
fi

# Pick a free public port for Apache: configured port, then the next two. A busy
# UI port is non-fatal — SMTP delivery must still work; only the UI is skipped.
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
/usr/local/bin/mailpit \
    --smtp "127.0.0.1:${MAILPIT_PORT}" \
    --listen "127.0.0.1:${MAILPIT_UI_INTERNAL}" \
    --database "${DATA_DIR}/mailpit/mailpit.db" &

# Generate the Apache reverse proxy. Remove any stale vhost from a previous
# boot so a changed port cannot leave a second Listen directive behind.
# Prefix with zz- so Apache parses this after proxy.conf, which loads mod_proxy.
MAILPIT_PROXY_CONF=/etc/apache2/conf.d/zz-mailpit-ui.conf
rm -f "${MAILPIT_PROXY_CONF}"
if [ -n "${MAILPIT_UI_ACTUAL}" ]; then
    cat > "${MAILPIT_PROXY_CONF}" <<EOF
Listen ${MAILPIT_UI_ACTUAL}
<VirtualHost *:${MAILPIT_UI_ACTUAL}>
    ServerName mailpit.local
    ProxyPreserveHost On
    ProxyRequests Off
    ProxyPass / http://127.0.0.1:${MAILPIT_UI_INTERNAL}/ retry=0
    ProxyPassReverse / http://127.0.0.1:${MAILPIT_UI_INTERNAL}/
    <Location />
        Require ip 127.0.0.1 ::1 10.0.0.0/8 172.16.0.0/12 192.168.0.0/16
    </Location>
    ErrorLog /data/logs/mailpit_ui_error.log
    CustomLog /data/logs/mailpit_ui_access.log combined
</VirtualHost>
EOF
    chmod 644 "${MAILPIT_PROXY_CONF}"
else
    bashio::log.warning "No free Mailpit UI port near ${MAILPIT_UI_PORT} — UI unavailable; SMTP still works."
fi
unset _mp_candidate _mp_tries
bashio::log.info "Mailpit started (SMTP :${MAILPIT_PORT}, UI :${MAILPIT_UI_ACTUAL:-unavailable}, private networks only)"

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
# Build the add-on-managed keys separately, then merge them into the existing
# .env. Keys added by an operator (or by a restore) are preserved, and empty
# add-on options are written explicitly so cleared credentials do not linger.
# Emit key/value pairs as data. Values are never evaluated by the shell.
write_env_pair() {
    printf '%s=%s\n' "$1" "$2"
}
MANAGED_ENV=$(mktemp)
{
    write_env_pair APP_NAME Turtle
    write_env_pair APP_ENV production
    write_env_pair APP_KEY "${GENERATED_KEY}"
    write_env_pair APP_DEBUG false
    write_env_pair APP_URL "${APP_URL}"

    write_env_pair DB_CONNECTION mysql
    write_env_pair DB_HOST 127.0.0.1
    write_env_pair DB_PORT 3306
    write_env_pair DB_DATABASE turtle
    write_env_pair DB_USERNAME turtle
    write_env_pair DB_PASSWORD "${DB_PASSWORD}"
    write_env_pair DB_SOCKET /tmp/mysql.sock

    write_env_pair SESSION_DRIVER database
    write_env_pair SESSION_LIFETIME 120

    write_env_pair MAIL_MAILER smtp
    write_env_pair MAIL_HOST "${MAIL_HOST}"
    write_env_pair MAIL_PORT "${MAIL_PORT}"
    write_env_pair MAIL_USERNAME "${MAIL_USER}"
    write_env_pair MAIL_PASSWORD "${MAIL_PASS}"
    write_env_pair MAIL_FROM_ADDRESS "${MAIL_FROM}"
    write_env_pair MAIL_FROM_NAME Turtle
    write_env_pair MAIL_AUTH_MODE "${MAIL_AUTH_MODE}"
    write_env_pair MAIL_OAUTH_CLIENT_ID "${MAIL_OAUTH_CLIENT_ID}"
    write_env_pair MAIL_OAUTH_CLIENT_SECRET "${MAIL_OAUTH_CLIENT_SECRET}"
    write_env_pair MAIL_OAUTH_REFRESH_TOKEN "${MAIL_OAUTH_REFRESH_TOKEN}"
    write_env_pair MAIL_OAUTH_EMAIL "${MAIL_OAUTH_EMAIL}"
} > "${MANAGED_ENV}"

MERGED_ENV=$(mktemp)
if [ -f "${TURTLE_DIR}/.env" ]; then
    while IFS='=' read -r env_key env_value || [ -n "${env_key:-}" ]; do
        case "${env_key}" in ''|\#*) continue ;; esac
        [[ "${env_key}" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]] || continue
        if ! grep -q "^${env_key}=" "${MANAGED_ENV}"; then
            printf '%s=%s\n' "${env_key}" "${env_value}" >> "${MERGED_ENV}"
        fi
    done < "${TURTLE_DIR}/.env"
fi
cat "${MANAGED_ENV}" >> "${MERGED_ENV}"
mv "${MERGED_ENV}" "${TURTLE_DIR}/.env"
rm -f "${MANAGED_ENV}"
unset env_key env_value

# Export env vars for Apache/PHP. The file holds secrets: owner-only with
# group read for the web server, never world-readable. Parse it as data —
# sourcing it would re-evaluate operator values as shell code (command
# substitution, backticks, `;`) with root privileges.
while IFS='=' read -r env_key env_value || [ -n "${env_key:-}" ]; do
    case "${env_key}" in
        ''|\#*) continue ;;
        export\ *) env_key="${env_key#export }" ;;
    esac
    # Only valid shell identifiers can be exported.
    [[ "${env_key}" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]] || continue
    # Match functions.php's loadEnvFile(): strip one matching quote pair.
    if [[ ${#env_value} -ge 2 ]]; then
        if [[ "${env_value:0:1}" == '"' && "${env_value: -1}" == '"' ]] ||
           [[ "${env_value:0:1}" == "'" && "${env_value: -1}" == "'" ]]; then
            env_value="${env_value:1:${#env_value}-2}"
        fi
    fi
    export "${env_key}=${env_value}"
done < "${TURTLE_DIR}/.env"
unset env_key env_value
chown "root:${WEB_GROUP}" "${TURTLE_DIR}/.env" 2>/dev/null || true
chmod 640 "${TURTLE_DIR}/.env"

# ── Symlink persistent storage into app ───────────────────────────────────────
# Never delete a data directory. If a previous boot (or a restore) left a real
# directory at a link path, migrate its contents into /data and keep a backup
# instead of rm -rf'ing uploads/logs/framework.
ensure_persistent_link() {
    local target="$1" link="$2"
    mkdir -p "${target}"

    if [ -L "${link}" ]; then
        [ "$(readlink "${link}")" = "${target}" ] && return 0
        rm -f "${link}" # only the symlink
    elif [ -e "${link}" ]; then
        if [ ! -d "${link}" ]; then
            bashio::log.warning "Unexpected file at ${link}; leaving it untouched."
            return 1
        fi
        if [ -n "$(ls -A "${link}" 2>/dev/null)" ]; then
            local backup="${link}.pre-symlink.$(date +%s)"
            bashio::log.info "Migrating ${link} into ${target}..."
            cp -a "${link}/." "${target}/" || {
                bashio::log.error "Copy failed; keeping ${link} untouched."
                return 1
            }
            mv "${link}" "${backup}"
            bashio::log.info "Original preserved at ${backup}"
        else
            rmdir "${link}"
        fi
    fi

    ln -sfn "${target}" "${link}"
}

ensure_persistent_link "${DATA_DIR}/uploads"   "${TURTLE_DIR}/storage/uploads"
ensure_persistent_link "${DATA_DIR}/logs"      "${TURTLE_DIR}/storage/logs"
ensure_persistent_link "${DATA_DIR}/framework" "${TURTLE_DIR}/storage/framework"

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
# migrate.sh reads DB_* from the environment/.env (including DB_SOCKET), so no
# command rewriting is needed. MariaDB's root account here has no password.
bashio::log.info "Running incremental migrations..."
PATCHED_MIGRATE=$(mktemp)
sed \
    -e 's|cd /var/www/html|cd '"${TURTLE_DIR}"'|g' \
    "${TURTLE_DIR}/database/migrate.sh" > "${PATCHED_MIGRATE}"
chmod +x "${PATCHED_MIGRATE}"
# Guarded: a migration failure must not kill the boot (set -e) — warn and continue.
DB_ROOT_PASSWORD="" bash "${PATCHED_MIGRATE}" || bashio::log.warning "Migrations exited non-zero — continuing boot; check add-on log."
rm -f "${PATCHED_MIGRATE}"

# ── Sync mail settings to database ────────────────────────────────────────────
# The Mailer reads DB first, so push add-on values into the DB. Values are passed
# via the environment to a PHP helper that uses prepared statements — interpolating
# secrets into a SQL heredoc breaks on quotes and allows SQL injection.
bashio::log.info "Syncing mail settings to database..."
export TURTLE_MAIL_HOST="${MAIL_HOST}"
export TURTLE_MAIL_PORT="${MAIL_PORT}"
export TURTLE_MAIL_USERNAME="${MAIL_USER}"
export TURTLE_MAIL_PASSWORD="${MAIL_PASS}"
export TURTLE_MAIL_FROM_ADDRESS="${MAIL_FROM}"
export TURTLE_MAIL_HOST_EXPLICIT="${MAIL_HOST_EXPLICIT}"
export TURTLE_MAIL_PORT_EXPLICIT="${MAIL_PORT_EXPLICIT}"
export TURTLE_MAIL_FROM_EXPLICIT="${MAIL_FROM_EXPLICIT}"
export TURTLE_MAIL_AUTH_MODE="${MAIL_AUTH_MODE}"
export TURTLE_MAIL_OAUTH_CLIENT_ID="${MAIL_OAUTH_CLIENT_ID}"
export TURTLE_MAIL_OAUTH_CLIENT_SECRET="${MAIL_OAUTH_CLIENT_SECRET}"
export TURTLE_MAIL_OAUTH_REFRESH_TOKEN="${MAIL_OAUTH_REFRESH_TOKEN}"
export TURTLE_MAIL_OAUTH_EMAIL="${MAIL_OAUTH_EMAIL}"

# Guarded: mail sync is best-effort — the Mailer falls back to .env values.
if php /usr/bin/sync-mail-settings.php; then
    bashio::log.info "Mail settings synced."
else
    bashio::log.warning "Mail settings sync failed — continuing boot."
fi
unset TURTLE_MAIL_PASSWORD TURTLE_MAIL_OAUTH_CLIENT_SECRET TURTLE_MAIL_OAUTH_REFRESH_TOKEN

# ── Permissions ───────────────────────────────────────────────────────────────
# Least privilege: the code tree is readable by the web group, never world
# writable. Only the directories the app/updater actually writes are group
# writable; everything under /data that Apache touches is group-owned too.
chown -R "root:${WEB_GROUP}" "${TURTLE_DIR}" 2>/dev/null || true
chmod -R u=rwX,g=rX,o= "${TURTLE_DIR}" 2>/dev/null || true

# The in-app updater (git pull), logs, and uploads need group write access.
for writable in "${TURTLE_DIR}/.git" "${TURTLE_DIR}/storage" "${TURTLE_DIR}/www/assets/uploads"; do
    [ -e "${writable}" ] || continue
    chown -R "root:${WEB_GROUP}" "${writable}" 2>/dev/null || true
    chmod -R u=rwX,g=rwX,o= "${writable}" 2>/dev/null || true
done

# Persistent data dirs are written by Apache (group apache), not just by root.
for data_dir in "${DATA_DIR}/uploads" "${DATA_DIR}/logs" "${DATA_DIR}/framework"; do
    chown -R "root:${WEB_GROUP}" "${data_dir}" 2>/dev/null || true
    chmod -R u=rwX,g=rwX,o= "${data_dir}" 2>/dev/null || true
done

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
