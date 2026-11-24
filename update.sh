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
    if [ -n "${BACKUP}" ] && [ -f "${BACKUP}" ]; then
        echo "Update failed — restoring previous files from ${BACKUP} ..." >&2
        tar -xzf "${BACKUP}" -C "${REPO_DIR}" || true
    fi
}
trap restore_backup ERR

echo "Backing up current files..."
# mktemp templates must end in X's on macOS/BSD; add the suffix afterwards.
BACKUP_TMP="$(mktemp "${TMPDIR:-/tmp}/turtle_update_XXXXXX")"
BACKUP="${BACKUP_TMP}.tar.gz"
mv "${BACKUP_TMP}" "${BACKUP}"
tar -czf "${BACKUP}" \
    --exclude='./.git' \
    --exclude='./storage/uploads' \
    --exclude='./www/assets/uploads/logo' \
    .

echo "Fetching origin..."
git fetch origin

echo "Updating ${BRANCH} (fast-forward only)..."
git pull --ff-only origin "${BRANCH}"

echo "Running migrations..."
bash database/migrate.sh

echo "Restarting services..."
docker compose -f docker-compose.yml up -d --build

trap - ERR
rm -f "${BACKUP}"
echo "Done!"
