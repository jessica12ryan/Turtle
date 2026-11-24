<?php

namespace Tests\Unit;

use App\Core\Database;
use Tests\TestCase;

final class DatabaseSqlTest extends TestCase
{
    public function testSplitSqlRespectsSemicolonsInsideQuotes(): void
    {
        $sql = "INSERT INTO t (v) VALUES ('a;b'); -- a comment with ;\nINSERT INTO t (v) VALUES ('c''d');";
        $statements = Database::splitSql($sql);

        self::assertCount(2, $statements);
        self::assertStringContainsString("'a;b'", $statements[0]);
        self::assertStringContainsString("'c''d'", $statements[1]);
    }

    public function testSplitSqlSkipsEmptyStatements(): void
    {
        self::assertSame(['SELECT 1'], Database::splitSql(";;\n SELECT 1 ;;;"));
    }

    public function testSplitSqlHandlesFixtureWithSemicolonsInText(): void
    {
        $sql = (string)file_get_contents(dirname(__DIR__) . '/fixtures/sql-with-semicolons.sql');
        $statements = Database::splitSql($sql);

        self::assertCount(4, $statements);
        self::assertStringContainsString('Bright; quiet street; near transit', $statements[1]);
        self::assertStringContainsString('It\'\'s a "test"; with quotes', $statements[2]);
        self::assertStringContainsString("'last'", $statements[3]);
    }
}
