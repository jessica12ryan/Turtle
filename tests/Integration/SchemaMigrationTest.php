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

            foreach (['users', 'properties', 'tickets', 'settings', 'role_permissions', 'notifications'] as $table) {
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
            $seed = preg_replace('/^-- .*/m', '', $seed) ?? $seed;
            foreach (explode(';', $seed) as $statement) {
                $statement = trim($statement);
                if ($statement === '') {
                    continue;
                }
                try {
                    $pdo->exec($statement);
                } catch (\Throwable $e) {
                    // seed.sql is best-effort and includes statements whose
                    // columns are added by migrations on some installations.
                }
            }

            self::assertGreaterThanOrEqual(10, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
            self::assertSame(4, (int) $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn());
            self::assertSame(7, (int) $pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn());
        } finally {
            $root->exec("DROP DATABASE IF EXISTS `{$database}`");
        }
    }
}
