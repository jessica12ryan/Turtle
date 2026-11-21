<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Tenancy;

final class TenancyIntegrationTest extends IntegrationTestCase
{
    /** @return array{property:int,tenant:int} */
    private function fixture(): array
    {
        $company = $this->insertCompany();
        $landlord = $this->insertUser('owner@tenancy.test', 'landlord');
        $tenant = $this->insertUser('current@tenancy.test', 'tenant');
        $archived = $this->insertUser('archived@tenancy.test', 'tenant');

        Database::execute('INSERT INTO company_user (company_id, user_id) VALUES (?, ?)', [$company, $landlord]);

        $property = Database::insert(
            "INSERT INTO properties (landlord_id, company_id, name, created_at, updated_at)
             VALUES (?, ?, 'Tenancy Property', NOW(), NOW())",
            [$landlord, $company]
        );

        Database::execute(
            'INSERT INTO property_tenant (property_id, tenant_id, is_main_tenant) VALUES (?, ?, 1)',
            [$property, $tenant]
        );
        Database::execute(
            'INSERT INTO property_tenant (property_id, tenant_id, is_main_tenant) VALUES (?, ?, 0)',
            [$property, $archived]
        );
        Database::execute('UPDATE users SET archived_at = NOW() WHERE id = ?', [$archived]);

        return ['property' => $property, 'tenant' => $tenant];
    }

    public function testSqlFragmentsCountOnlyActiveNonArchivedTenants(): void
    {
        $f = $this->fixture();

        $row = Database::fetch(
            'SELECT p.id, ' . Tenancy::tenantCountSql('p') . ' AS tenants,
                    ' . Tenancy::mainTenantIdSql('p') . ' AS main_tenant,
                    ' . Tenancy::hasTenantSql('p') . ' AS occupied
             FROM properties p WHERE p.id = ?',
            [$f['property']]
        );

        self::assertSame(1, (int) $row['tenants']);
        self::assertSame($f['tenant'], (int) $row['main_tenant']);
        self::assertSame(1, (int) $row['occupied']);
    }

    public function testArchivingTheLastTenantMakesPropertyVacant(): void
    {
        $f = $this->fixture();
        Database::execute('UPDATE users SET archived_at = NOW() WHERE id = ?', [$f['tenant']]);

        $row = Database::fetch(
            'SELECT p.id, ' . Tenancy::tenantCountSql('p') . ' AS tenants,
                    ' . Tenancy::mainTenantIdSql('p') . ' AS main_tenant,
                    ' . Tenancy::hasTenantSql('p') . ' AS occupied
             FROM properties p WHERE p.id = ?',
            [$f['property']]
        );

        self::assertSame(0, (int) $row['tenants']);
        self::assertNull($row['main_tenant']);
        self::assertSame(0, (int) $row['occupied']);
    }
}
