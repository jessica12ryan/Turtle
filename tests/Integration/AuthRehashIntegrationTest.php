<?php

namespace Tests\Integration;

use App\Core\Auth;
use App\Core\Database;

final class AuthRehashIntegrationTest extends IntegrationTestCase
{
    public function testLegacyHashIsUpgradedAndThenLeftAlone(): void
    {
        $legacy = password_hash('secret123', PASSWORD_BCRYPT, ['cost' => 4]);
        $userId = Database::insert(
            "INSERT INTO users (name, email, password, role, created_at, updated_at)
             VALUES ('Legacy', 'legacy@example.test', ?, 'tenant', NOW(), NOW())",
            [$legacy]
        );

        $updated = Auth::rehashPasswordIfNeeded($userId, $legacy, 'secret123');
        self::assertNotSame($legacy, $updated);
        self::assertTrue(password_verify('secret123', $updated));

        $stored = Database::fetch("SELECT password FROM users WHERE id = ?", [$userId]);
        self::assertSame($updated, $stored['password']);

        // A second call is a no-op once the hash is current.
        self::assertSame($updated, Auth::rehashPasswordIfNeeded($userId, $updated, 'secret123'));
    }
}
