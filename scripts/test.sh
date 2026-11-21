#!/usr/bin/env bash
# Canonical test runner. Every layer runs in Docker so local, AI and CI runs
# use the same PHP/MySQL/browser versions as the application.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

usage() {
    cat <<'USAGE'
Usage: ./scripts/test.sh [all|phpunit|unit|integration|e2e]

  all           PHPUnit (unit + integration) then Playwright E2E  (default)
  phpunit       PHPUnit unit + integration only
  unit          PHPUnit Unit suite only
  integration   PHPUnit Integration suite only (needs MySQL)
  e2e           Playwright HTTP/UI smoke tests against a fresh stack
USAGE
}

MODE="${1:-all}"
case "$MODE" in
    all|phpunit|unit|integration|e2e) ;;
    -h|--help|help) usage; exit 0 ;;
    *) echo "Unknown mode: $MODE" >&2; usage; exit 1 ;;
esac

# Always tear down the throwaway stacks, even when a test fails.
cleanup() {
    docker compose -f docker-compose.e2e.yml down -v --remove-orphans >/dev/null 2>&1 || true
    docker compose -f docker-compose.test.yml down -v --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT

run_phpunit() {
    local suite="${1:-}"
    echo "==> PHPUnit ${suite:-unit+integration} (Docker PHP 8.5 + MySQL 8.0)"
    if [ -n "$suite" ]; then
        docker compose -f docker-compose.test.yml run --rm test \
            bash -c "composer install --no-interaction --no-progress && vendor/bin/phpunit --testsuite ${suite}"
    else
        docker compose -f docker-compose.test.yml run --rm test
    fi
}

run_e2e() {
    echo "==> Playwright E2E (fresh Turtle stack)"
    cleanup
    docker compose -f docker-compose.e2e.yml run --rm playwright
    cleanup
}

case "$MODE" in
    unit)        run_phpunit Unit ;;
    integration) run_phpunit Integration ;;
    phpunit)     run_phpunit ;;
    e2e)         run_e2e ;;
    all)         run_phpunit; run_e2e ;;
esac

echo "==> Done: $MODE"
