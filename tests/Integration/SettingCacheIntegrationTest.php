<?php

namespace Tests\Integration;

use App\Core\Database;

final class SettingCacheIntegrationTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        clearSettingCache();
        parent::tearDown();
    }

    public function testSettingsAreNotCachedInTheSession(): void
    {
        $key = 'turtle_test_cached_setting';
        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?",
            [$key, 'first', 'first']
        );
        clearSettingCache();

        self::assertSame('first', getCachedSetting($key));
        self::assertArrayNotHasKey('_setting_cache_' . $key, $_SESSION);
        self::assertArrayNotHasKey('_setting_cache_' . $key . '_expiry', $_SESSION);
    }

    public function testNewRequestSeesSettingsChangeImmediately(): void
    {
        $key = 'turtle_test_cached_setting';
        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?",
            [$key, 'first', 'first']
        );
        clearSettingCache();
        self::assertSame('first', getCachedSetting($key));

        Database::execute("UPDATE settings SET `value` = ? WHERE `key` = ?", ['second', $key]);
        self::assertSame('first', getCachedSetting($key), 'same request keeps its own cache');

        // A new request has an empty cache — the change is visible at once,
        // unlike the old per-session five-minute cache.
        clearSettingCache();
        self::assertSame('second', getCachedSetting($key));
    }

    public function testMissingSettingIsNotCachedAsMissingForever(): void
    {
        $key = 'turtle_test_missing_setting';
        Database::execute("DELETE FROM settings WHERE `key` = ?", [$key]);
        clearSettingCache();

        self::assertNull(getCachedSetting($key));

        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES (?, ?)",
            [$key, 'present']
        );
        clearSettingCache();
        self::assertSame('present', getCachedSetting($key));
    }
}
