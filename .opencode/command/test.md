---
description: Run the full Turtle test suite in Docker and report results
---

Run the complete Turtle test suite from the repository root using the canonical script:

```bash
./scripts/test.sh
```

This runs PHPUnit (unit + integration, in Docker PHP 8.5 + MySQL 8.0) followed by the Playwright E2E smoke test against a fresh throwaway stack.

After it finishes, report:

1. PHPUnit: number of tests/assertions and pass/fail.
2. Playwright: pass/fail.
3. For each failure: the test name and the first relevant error lines.

Do not modify application code to make a failing test pass unless I explicitly ask. If the suite cannot run (Docker unavailable, image pull failure), report the exact blocker instead of guessing. See `AGENTS.md` for the full testing guidance.
