#!/bin/bash
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "${REPO_DIR}"

echo "=== Turtle Update ==="

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "Error: ${REPO_DIR} is not a git repository." >&2
    exit 1
fi

BRANCH=$(git rev-parse --abbrev-ref HEAD)
BACKUP=""

restore_backup() {
    trap - ERR
    if [ -n "${BACKUP}" ] && [ -f "${BACKUP}" ]; then
        echo "Update failed — restoring previous files from ${BACKUP} ..." >&2
        tar -xzf "${BACKUP}" -C "${REPO_DIR}" || true
        # Never leave a copy of the pre-update tree (or its secrets) in /tmp.
        rm -f "${BACKUP}"
    fi
}
trap restore_backup ERR

# Optional supply-chain checks. They are opt-in so existing installs keep
# working, but allow an operator to pin the source and refuse unsigned code.
if [ -n "${UPDATE_EXPECTED_REMOTE:-}" ]; then
    ACTUAL_REMOTE=$(git config --get remote.origin.url || true)
    if [ "${ACTUAL_REMOTE}" != "${UPDATE_EXPECTED_REMOTE}" ]; then
        echo "Error: remote origin is '${ACTUAL_REMOTE}', expected '${UPDATE_EXPECTED_REMOTE}'." >&2
        exit 1
    fi
fi

echo "Backing up current files..."
# mktemp templates must end in X's on macOS/BSD; add the suffix afterwards.
BACKUP_TMP="$(mktemp "${TMPDIR:-/tmp}/turtle_update_XXXXXX")"
BACKUP="${BACKUP_TMP}.tar.gz"
mv "${BACKUP_TMP}" "${BACKUP}"
tar -czf "${BACKUP}" \
    --exclude='./.git' \
    --exclude='./.env' \
    --exclude='./storage/uploads' \
    --exclude='./www/assets/uploads/logo' \
    .

echo "Fetching origin..."
git fetch origin

if [ -n "${UPDATE_EXPECTED_SHA:-}" ]; then
    REMOTE_SHA=$(git rev-parse "origin/${BRANCH}")
    case "${REMOTE_SHA}" in
        "${UPDATE_EXPECTED_SHA}"*) ;;
        *)
            echo "Error: origin/${BRANCH} is ${REMOTE_SHA}, expected ${UPDATE_EXPECTED_SHA}... Update aborted." >&2
            exit 1
            ;;
    esac
fi

echo "Updating ${BRANCH} (fast-forward only)..."
git pull --ff-only origin "${BRANCH}"

if [ "${UPDATE_VERIFY_SIGNATURES:-0}" = "1" ] && ! git verify-commit HEAD >/dev/null 2>&1; then
    echo "Error: updated HEAD is not signed by a trusted key. Update aborted." >&2
    exit 1
fi

echo "Running migrations..."
bash database/migrate.sh

echo "Restarting services..."
docker compose -f docker-compose.yml up -d --build

trap - ERR
rm -f "${BACKUP}"
echo "Done!"
