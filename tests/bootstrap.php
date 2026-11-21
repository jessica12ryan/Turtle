<?php

/**
 * PHPUnit bootstrap.
 *
 * Loads the application's own autoloader and helper functions so tests exercise
 * the exact same code paths as the web entrypoint. Kept dependency-free on
 * purpose — no framework test harness, just the app plus PHPUnit.
 */

require_once dirname(__DIR__) . '/www/autoload.php';
require_once dirname(__DIR__) . '/www/functions.php';

// Shared test database helper (no PHPUnit dependency).
require_once __DIR__ . '/Support/database.php';

// PHPUnit base classes. The legacy standalone smoke test (view_test.php) also
// boots this file without Composer's autoloader, so only load these when
// PHPUnit is available.
if (class_exists(\PHPUnit\Framework\TestCase::class)) {
    require_once __DIR__ . '/TestCase.php';
    require_once __DIR__ . '/Integration/IntegrationTestCase.php';
}
