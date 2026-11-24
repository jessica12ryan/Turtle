<?php

namespace Tests\Unit;

use Tests\TestCase;

final class UpdateAlertTest extends TestCase
{
    public function testDevelopmentChannelUsesCheckResultNotVersionComparison(): void
    {
        // A hash and a tag can never compare equal; the persisted check result
        // is the only reliable signal on the development channel.
        self::assertFalse(has_available_update(['update_available' => '0']));
        self::assertFalse(has_available_update([]));
        self::assertTrue(has_available_update(['update_available' => '1']));
    }
}
