<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Shared setup for every suite: session state and superglobals must never leak
 * between tests (the app relies heavily on $_SESSION for auth/CSRF/flash).
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = ['_language' => 'en']; // avoids DB lookups from __() in views
        unset(
            $_SERVER['HTTP_X_FORWARDED_PREFIX'],
            $_SERVER['HTTP_X_INGRESS_PATH'],
            $_SERVER['HTTP_REFERER'],
            $_SERVER['REQUEST_METHOD']
        );
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        $_SESSION = ['_language' => 'en'];
        http_response_code(200);
        parent::tearDown();
    }
}
