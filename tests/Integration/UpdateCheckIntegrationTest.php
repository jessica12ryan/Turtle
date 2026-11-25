<?php

namespace Tests\Integration;

use App\Controllers\UpdateController;
use App\Core\Database;

final class UpdateCheckIntegrationTest extends IntegrationTestCase
{
    public function testClaimIsThrottledAndPersistsResults(): void
    {
        $now = time();
        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES ('last_update_check', ?)
             ON DUPLICATE KEY UPDATE `value` = ?",
            [gmdate('Y-m-d H:i:s', $now - 8 * 3600), gmdate('Y-m-d H:i:s', $now - 8 * 3600)]
        );

        self::assertTrue(UpdateController::claimUpdateCheck($now));
        self::assertFalse(UpdateController::claimUpdateCheck($now), 'a second claim in the same window must not check again');

        UpdateController::persistCheckResult([
            'latest_version' => '9.9.9',
            'update_available' => true,
            'behind_count' => 3,
        ]);

        $values = [];
        foreach (['latest_version', 'update_available', 'behind_count'] as $key) {
            $row = Database::fetch("SELECT `value` FROM settings WHERE `key` = ?", [$key]);
            $values[$key] = (string)($row['value'] ?? '');
        }
        self::assertSame('9.9.9', $values['latest_version']);
        self::assertSame('1', $values['update_available']);
        self::assertSame('3', $values['behind_count']);
    }
}
