<?php

namespace Tests\Integration;

use Tests\TestCase;

/**
 * Runs the real migration script against a throwaway database. This class does
 * NOT use IntegrationTestCase: migrations are DDL and MySQL implicitly commits
 * DDL, which would break the per-test transaction used by the other suites.
 */
final class SchemaMigrationTest extends TestCase
{
    private const DATABASE = 'turtle_migration_test';

    public function testMigrationsAreIdempotentAndSeedDataLoads(): void
    {
        if (trim((string) shell_exec('command -v mysql 2>/dev/null')) === '') {
            self::markTestSkipped('mysql CLI is only available in the Docker test image');
        }

        $host = getenv('DB_HOST') ?: 'mysql';
        $port = getenv('DB_PORT') ?: '3306';
        $user = getenv('DB_USERNAME') ?: 'turtle';
        $pass = getenv('DB_PASSWORD') ?: 'turtle';
        $rootPass = getenv('MYSQL_ROOT_PASSWORD') ?: 'root';
        $database = self::DATABASE;

        $root = new \PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            'root',
            $rootPass,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        try {
            $root->exec("DROP DATABASE IF EXISTS `{$database}`");
            $root->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $env = sprintf(
                'DB_HOST=%s DB_PORT=%s DB_DATABASE=%s DB_USERNAME=%s DB_PASSWORD=%s MYSQL_ROOT_PASSWORD=%s',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($database),
                escapeshellarg($user),
                escapeshellarg($pass),
                escapeshellarg($rootPass)
            );
            $command = sprintf(
                'cd %s && %s bash database/migrate.sh 2>&1',
                escapeshellarg(base_path()),
                $env
            );

            $output = [];
            exec($command, $output, $exitCode);
            self::assertSame(0, $exitCode, implode("\n", $output));

            $pdo = new \PDO(
                "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
                'root',
                $rootPass,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            foreach (['users', 'properties', 'tickets', 'settings', 'role_permissions', 'notifications', 'payments', 'rent_period_exemptions'] as $table) {
                self::assertSame(
                    1,
                    (int) $pdo->query(
                        "SELECT COUNT(*) FROM information_schema.tables
                         WHERE table_schema = DATABASE() AND table_name = '{$table}'"
                    )->fetchColumn(),
                    "missing table: {$table}"
                );
            }

            // Running again must stay green — updates call this on every boot.
            $secondOutput = [];
            exec($command, $secondOutput, $secondExitCode);
            self::assertSame(0, $secondExitCode, implode("\n", $secondOutput));

            // Seed data mirrors the setup wizard's "load sample data" option.
            $pdo->exec(
                "INSERT INTO users (id, name, email, password, role, created_at, updated_at)
                 VALUES (1, 'Admin', 'admin@migration.test', 'x', 'admin', NOW(), NOW())"
            );
            $seed = (string) file_get_contents(base_path('database/seed.sql'));
            foreach (\App\Core\Database::splitSql($seed) as $index => $statement) {
                try {
                    $pdo->exec($statement);
                } catch (\PDOException $e) {
                    self::fail("seed.sql statement #{$index} failed: " . $e->getMessage());
                }
            }

            self::assertGreaterThanOrEqual(10, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
            self::assertSame(4, (int) $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn());
            self::assertSame(7, (int) $pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn());
        } finally {
            $root->exec("DROP DATABASE IF EXISTS `{$database}`");
        }
    }

    public function testLegacyExemptionIndexCanBeUpgraded(): void
    {
        if (trim((string) shell_exec('command -v mysql 2>/dev/null')) === '') {
            self::markTestSkipped('mysql CLI is only available in the Docker test image');
        }

        $host = getenv('DB_HOST') ?: 'mysql';
        $port = getenv('DB_PORT') ?: '3306';
        $user = getenv('DB_USERNAME') ?: 'turtle';
        $pass = getenv('DB_PASSWORD') ?: 'turtle';
        $rootPass = getenv('MYSQL_ROOT_PASSWORD') ?: 'root';
        $database = self::DATABASE;

        $root = new \PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            'root',
            $rootPass,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        $env = sprintf(
            'DB_HOST=%s DB_PORT=%s DB_DATABASE=%s DB_USERNAME=%s DB_PASSWORD=%s MYSQL_ROOT_PASSWORD=%s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($database),
            escapeshellarg($user),
            escapeshellarg($pass),
            escapeshellarg($rootPass)
        );
        $command = sprintf('cd %s && %s bash database/migrate.sh 2>&1', escapeshellarg(base_path()), $env);

        try {
            $root->exec("DROP DATABASE IF EXISTS `{$database}`");
            $root->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $output = [];
            exec($command, $output, $firstExit);
            self::assertSame(0, $firstExit, implode("\n", $output));

            // Recreate the pre-change shape: the unique key is the only index
            // backing the tenancy FK, exactly like a failed production update.
            $root->exec("USE `{$database}`");
            $root->exec('ALTER TABLE rent_period_exemptions ADD INDEX tmp_property_idx (property_id)');
            $root->exec('ALTER TABLE rent_period_exemptions DROP INDEX uniq_rent_period_exemption');
            $root->exec('ALTER TABLE rent_period_exemptions ADD UNIQUE KEY uniq_rent_period_exemption (property_tenant_id, period_start)');
            // The upgraded install has no dedicated tenancy index: the legacy
            // unique key itself is the only index backing the foreign key.
            $root->exec('ALTER TABLE rent_period_exemptions DROP INDEX idx_rent_exemptions_tenant');
            $legacyFk = $root->query(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rent_period_exemptions'
                   AND COLUMN_NAME = 'property_tenant_id' AND REFERENCED_TABLE_NAME = 'property_tenant' LIMIT 1"
            )->fetchColumn();
            $root->exec("ALTER TABLE rent_period_exemptions DROP FOREIGN KEY `{$legacyFk}`");
            $root->exec('ALTER TABLE rent_period_exemptions MODIFY property_tenant_id INT NOT NULL');
            $root->exec('ALTER TABLE rent_period_exemptions ADD CONSTRAINT fk_rent_exemptions_property_tenant FOREIGN KEY (property_tenant_id) REFERENCES property_tenant(id) ON DELETE CASCADE');

            $secondOutput = [];
            exec($command, $secondOutput, $secondExit);
            self::assertSame(0, $secondExit, implode("\n", $secondOutput));

            self::assertSame(1, (int) $root->query(
                "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rent_period_exemptions'
                   AND CONSTRAINT_NAME = 'uniq_rent_period_exemption' AND COLUMN_NAME = 'property_id'"
            )->fetchColumn());
            self::assertSame('SET NULL', $root->query(
                "SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'rent_period_exemptions'
                   AND CONSTRAINT_NAME = 'fk_rent_exemptions_property_tenant'"
            )->fetchColumn());

            // A failed update leaves the FK already switched to SET NULL while
            // the legacy unique key remains. A re-run must also succeed.
            $root->exec('ALTER TABLE rent_period_exemptions DROP INDEX uniq_rent_period_exemption');
            $root->exec('ALTER TABLE rent_period_exemptions ADD UNIQUE KEY uniq_rent_period_exemption (property_tenant_id, period_start)');
            $root->exec('ALTER TABLE rent_period_exemptions DROP INDEX idx_rent_exemptions_tenant');

            $thirdOutput = [];
            exec($command, $thirdOutput, $thirdExit);
            self::assertSame(0, $thirdExit, implode("\n", $thirdOutput));
            self::assertSame(1, (int) $root->query(
                "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rent_period_exemptions'
                   AND CONSTRAINT_NAME = 'uniq_rent_period_exemption' AND COLUMN_NAME = 'property_id'"
            )->fetchColumn());
        } finally {
            $root->exec("DROP DATABASE IF EXISTS `{$database}`");
        }
    }
}
