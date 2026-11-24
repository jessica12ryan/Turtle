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
}
