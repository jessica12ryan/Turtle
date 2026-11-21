<?php

namespace Tests\Integration;

use App\Core\Database;
use Tests\TestCase;

/**
 * Base class for tests that need MySQL. Each test runs inside a transaction
 * which is rolled back in tearDown, so tests never see each other's data.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected \PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = turtle_test_database();
        Database::beginTransaction();
    }

    protected function tearDown(): void
    {
        Database::rollback();
        parent::tearDown();
    }

    protected function insertUser(string $email, string $role = 'tenant', ?string $password = null): int
    {
        return Database::insert(
            "INSERT INTO users (name, email, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())",
            [$email, $email, $password ?? password_hash('secret123', PASSWORD_DEFAULT), $role]
        );
    }

    protected function insertCompany(string $name = 'Test Company'): int
    {
        return Database::insert(
            "INSERT INTO companies (name, created_at, updated_at) VALUES (?, NOW(), NOW())",
            [$name]
        );
    }

    protected function insertProperty(int $companyId, int $landlordId, string $name = 'Test Property'): int
    {
        return Database::insert(
            "INSERT INTO properties (landlord_id, company_id, name, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())",
            [$landlordId, $companyId, $name]
        );
    }
}
