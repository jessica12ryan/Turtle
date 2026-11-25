<?php

namespace Tests\Unit;

use App\Controllers\UpdateController;
use Tests\TestCase;

final class UpdateCheckTest extends TestCase
{
    public function testMissingTimestampIsStale(): void
    {
        self::assertTrue(UpdateController::shouldCheckUpdates('', time()));
    }

    public function testRecentTimestampIsNotStale(): void
    {
        $now = (int)strtotime('2026-01-01 12:00:00 UTC');
        self::assertFalse(UpdateController::shouldCheckUpdates('2026-01-01 11:30:00', $now));
    }

    public function testOldTimestampIsStale(): void
    {
        $now = (int)strtotime('2026-01-01 12:00:00 UTC');
        self::assertTrue(UpdateController::shouldCheckUpdates('2026-01-01 05:00:00', $now));
    }

    public function testMalformedTimestampIsStale(): void
    {
        self::assertTrue(UpdateController::shouldCheckUpdates('not-a-date', time()));
    }
}
