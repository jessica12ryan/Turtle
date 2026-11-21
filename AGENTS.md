# AGENTS.md

Guidance for AI coding agents working in this repository.

## Project

Turtle is a PHP 8.2+ tenant management portal with a small custom framework in `www/Core/` (no Laravel/Symfony). The Docker stack is PHP 8.5 + Apache (`app`), MySQL 8.0 (`mysql`), and Mailpit (`mailpit`). See `README.md` and `CONTRIBUTING.md` for product and coding conventions.

## Testing — canonical commands

Docker is required. Every suite runs in Docker so results are identical locally, for AI agents, and in CI (`.github/workflows/tests.yml` runs the same script).

```bash
./scripts/test.sh              # everything: PHPUnit + Playwright E2E (default)
./scripts/test.sh phpunit      # PHPUnit unit + integration
./scripts/test.sh unit         # PHPUnit Unit suite only
./scripts/test.sh integration  # PHPUnit Integration suite only
./scripts/test.sh e2e          # Playwright HTTP/UI smoke tests
```

Equivalent direct commands, if the script cannot be used:

```bash
docker compose -f docker-compose.test.yml run --rm test
docker compose -f docker-compose.e2e.yml run --rm playwright
```

### When asked to "run the tests" or "do all the testing"

1. Run `./scripts/test.sh` from the repository root.
2. Report a consolidated result: PHPUnit test/assertion counts, Playwright pass/fail, and the failing test name plus first error lines for any failure.
3. **Do not change application code to make a test pass unless explicitly asked** — report the failure instead.
4. Static analysis is separate: `composer lint` (PHPStan + PHPCS), also enforced by `.github/workflows/lint.yml`. Run it when asked or when changing PHP code.
5. Never point tests at the developer's live stack or `.env`. The test compose files mount `tests/empty.env` over `.env` and use throwaway databases (`turtle_test`, `turtle_migration_test`, `turtle_e2e`).

### What the layers cover

- **Unit** (`tests/Unit/`) — pure logic: `View`, `Validator`, `Router`, `Access`, `Tenancy`, and helpers in `www/functions.php`. No database.
- **Integration** (`tests/Integration/`) — `Database`, migrations, `Validator`'s `unique`/`exists` rules, access scoping, and tenancy SQL. Uses MySQL in Docker; each test is wrapped in a transaction and rolled back.
- **E2E** (`e2e/tests/`) — Playwright smoke test: fresh install wizard → login → navigate → create ticket → send test email → verify it in Mailpit → logout/login.

### Adding tests

- Put unit tests in `tests/Unit/` and integration tests in `tests/Integration/`, extending `Tests\TestCase` or `Tests\Integration\IntegrationTestCase`. Name files `*Test.php`.
- Use `tests/fixtures/` for view/controller stubs. Reset `$_SESSION` through the base class — auth, CSRF, and flash state must not leak between tests.
- Never write to the real `turtle` database. Integration tests get a clean `turtle_test` database via `turtle_test_database()` in `tests/bootstrap.php`.
- Add E2E specs under `e2e/tests/`. Keep them few and high-value; prefer stable selectors (`name`, `id`, labels) over CSS chain positions.
- After adding tests, run `./scripts/test.sh` before opening a PR.

### Exploratory browser runbook (AI agents)

The committed Playwright suite covers the happy path. Use the in-app browser tool when you need adaptiveness — first-boot visuals, layout checks, ad-hoc console inspection, or Mailpit UI exploration:

1. `docker compose -f docker-compose.e2e.yml down -v --remove-orphans` (never run bare `docker compose down` — that would stop the developer's live stack), then `docker compose -f docker-compose.e2e.yml up -d --build`.
2. Open `http://localhost:8085` (the app's E2E port). A fresh database routes to `/setup`.
3. Walk the wizard, then check `/home`, `/properties`, `/tickets`, `/settings`.
4. Read the browser console for uncaught errors; check Mailpit at `http://localhost:8025`.
5. Tear down with `docker compose -f docker-compose.e2e.yml down -v --remove-orphans`.

Do not use the browser runbook as a substitute for the canonical script — it is a supplement.
