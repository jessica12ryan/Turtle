<?php

namespace Tests\Unit;

use App\Core\Logger;
use Tests\TestCase;

final class LoggerTest extends TestCase
{
    public function testSeverityMappingDoesNotUseDeprecatedConstants(): void
    {
        $deprecations = [];
        set_error_handler(function (int $errno, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;
            return true;
        }, E_DEPRECATED);

        try {
            self::assertSame('debug', Logger::levelForPhpSeverity(E_DEPRECATED));
            self::assertSame('error', Logger::levelForPhpSeverity(E_ERROR));
            Logger::errorReporting();
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $deprecations, 'referencing removed constants must not emit deprecations');
    }
}
