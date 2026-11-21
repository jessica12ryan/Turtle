<?php

namespace Tests\Integration;

use App\Core\Database;

final class DatabaseTest extends IntegrationTestCase
{
    public function testInsertFetchFetchAllAndExecute(): void
    {
        $id = $this->insertCompany('Alpha');

        self::assertGreaterThan(0, $id);

        $row = Database::fetch('SELECT id, name FROM companies WHERE id = ?', [$id]);
        self::assertSame('Alpha', $row['name']);

        $affected = Database::execute('UPDATE companies SET name = ? WHERE id = ?', ['Beta', $id]);
        self::assertSame(1, $affected);
        self::assertSame('Beta', Database::fetch('SELECT name FROM companies WHERE id = ?', [$id])['name']);

        $this->insertCompany('Gamma');
        self::assertCount(2, Database::fetchAll('SELECT id FROM companies'));
    }

    public function testFetchReturnsNullWhenNoRowMatches(): void
    {
        self::assertNull(Database::fetch('SELECT id FROM companies WHERE id = ?', [999_999]));
        self::assertSame([], Database::fetchAll('SELECT id FROM companies WHERE name = ?', ['nope']));
    }

    public function testUserInputIsNeverInterpretedAsSql(): void
    {
        $name = "O'Brien; DROP TABLE users; --";

        $id = Database::insert(
            'INSERT INTO companies (name, created_at, updated_at) VALUES (?, NOW(), NOW())',
            [$name]
        );

        self::assertSame($name, Database::fetch('SELECT name FROM companies WHERE id = ?', [$id])['name']);
        self::assertNotNull(Database::fetch(
            "SELECT table_name FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'users'"
        ));
    }

    public function testTransactionsCanBeRolledBack(): void
    {
        $id = $this->insertCompany('Temporary');
        self::assertNotNull(Database::fetch('SELECT id FROM companies WHERE id = ?', [$id]));

        Database::execute('DELETE FROM companies WHERE id = ?', [$id]);
        self::assertNull(Database::fetch('SELECT id FROM companies WHERE id = ?', [$id]));
    }
}
