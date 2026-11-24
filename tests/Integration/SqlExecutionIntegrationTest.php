<?php

namespace Tests\Integration;

use App\Core\Database;

final class SqlExecutionIntegrationTest extends IntegrationTestCase
{
    public function testExecuteSqlPreservesSemicolonsInsideQuotedData(): void
    {
        $pdo = Database::instance()->getConnection();
        $pdo->exec("DROP TABLE IF EXISTS sql_split_fixture");
        try {
            $sql = (string)file_get_contents(dirname(__DIR__) . '/fixtures/sql-with-semicolons.sql');
            Database::executeSql($sql);

            $rows = Database::fetchAll("SELECT description FROM sql_split_fixture ORDER BY id");
            self::assertSame([
                ['description' => 'Bright; quiet street; near transit'],
                ['description' => 'It\'s a "test"; with quotes'],
                ['description' => 'last'],
            ], $rows);
        } finally {
            $pdo->exec("DROP TABLE IF EXISTS sql_split_fixture");
        }
    }

    public function testExecuteSqlAbortsOnInvalidStatement(): void
    {
        $this->expectException(\Throwable::class);
        Database::executeSql("SELECT 1; THIS IS NOT SQL;");
    }
}
