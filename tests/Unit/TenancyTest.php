<?php

namespace Tests\Unit;

use App\Core\Tenancy;
use Tests\TestCase;

final class TenancyTest extends TestCase
{
    public function testActiveLinkConditionChecksBothSidesOfTheLink(): void
    {
        self::assertSame(
            'pt.moved_out_at IS NULL AND u.archived_at IS NULL',
            Tenancy::activeLinkCondition()
        );
        self::assertSame(
            'current.moved_out_at IS NULL AND account.archived_at IS NULL',
            Tenancy::activeLinkCondition('current', 'account')
        );
    }

    public function testTenantCountSqlUsesAliasesAndBothConditions(): void
    {
        $sql = Tenancy::tenantCountSql('p');

        self::assertStringContainsString('p.id', $sql);
        self::assertStringContainsString('pt_count.moved_out_at IS NULL', $sql);
        self::assertStringContainsString('u_count.archived_at IS NULL', $sql);
        self::assertStringContainsString('COUNT(*)', $sql);
    }

    public function testMainTenantIdSqlRequiresMainTenantFlag(): void
    {
        $sql = Tenancy::mainTenantIdSql('property');

        self::assertStringContainsString('property.id', $sql);
        self::assertStringContainsString('pt_main.is_main_tenant = 1', $sql);
        self::assertStringContainsString('pt_main.moved_out_at IS NULL', $sql);
        self::assertStringContainsString('u_main.archived_at IS NULL', $sql);
        self::assertStringContainsString('ORDER BY pt_main.id LIMIT 1', $sql);
    }

    public function testHasTenantSqlIsCorrelatedExistsCheck(): void
    {
        $sql = Tenancy::hasTenantSql('p');

        self::assertStringStartsWith('EXISTS (SELECT 1', $sql);
        self::assertStringContainsString('p.id', $sql);
        self::assertStringContainsString('u_exists.archived_at IS NULL', $sql);
    }
}
