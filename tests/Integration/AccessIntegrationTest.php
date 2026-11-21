<?php

namespace Tests\Integration;

use App\Core\Access;
use App\Core\Database;

final class AccessIntegrationTest extends IntegrationTestCase
{
    /** @return array{company:int,landlord:int,manager:int,tenant:int,outsider:int,property:int,ticket:int} */
    private function fixture(): array
    {
        $company = $this->insertCompany();
        $landlord = $this->insertUser('landlord@access.test', 'landlord');
        $manager = $this->insertUser('manager@access.test', 'property_manager');
        $tenant = $this->insertUser('tenant@access.test', 'tenant');
        $outsider = $this->insertUser('outsider@access.test', 'tenant');

        foreach ([$landlord, $manager] as $userId) {
            Database::execute(
                'INSERT INTO company_user (company_id, user_id) VALUES (?, ?)',
                [$company, $userId]
            );
        }

        $property = Database::insert(
            "INSERT INTO properties (landlord_id, company_id, property_manager_id, name, created_at, updated_at)
             VALUES (?, ?, ?, 'Access Property', NOW(), NOW())",
            [$landlord, $company, $manager]
        );
        Database::execute(
            'INSERT INTO property_tenant (property_id, tenant_id, is_main_tenant) VALUES (?, ?, 1)',
            [$property, $tenant]
        );

        $ticket = Database::insert(
            "INSERT INTO tickets (property_id, tenant_id, subject, description, category, created_at, updated_at)
             VALUES (?, ?, 'Leaky tap', 'Description', 'plumbing', NOW(), NOW())",
            [$property, $tenant]
        );

        return compact('company', 'landlord', 'manager', 'tenant', 'outsider', 'property', 'ticket');
    }

    public function testPropertyAccessHonoursCompanyAndTenancy(): void
    {
        $f = $this->fixture();

        self::assertTrue(Access::property($f['property'], ['id' => $f['landlord'], 'role' => 'landlord']));
        self::assertTrue(Access::property($f['property'], ['id' => $f['manager'], 'role' => 'property_manager']));
        self::assertTrue(Access::property($f['property'], ['id' => $f['tenant'], 'role' => 'tenant']));
        self::assertFalse(Access::property($f['property'], ['id' => $f['outsider'], 'role' => 'tenant']));
        self::assertTrue(Access::property($f['property'], ['id' => 999, 'role' => 'admin']));
    }

    public function testMovedOutTenantLosesPropertyAccess(): void
    {
        $f = $this->fixture();
        Database::execute(
            'UPDATE property_tenant SET moved_out_at = NOW() WHERE property_id = ? AND tenant_id = ?',
            [$f['property'], $f['tenant']]
        );

        self::assertFalse(Access::property($f['property'], ['id' => $f['tenant'], 'role' => 'tenant']));
    }

    public function testTenantAccessAllowsSelfButNotOtherTenants(): void
    {
        $f = $this->fixture();

        self::assertTrue(Access::tenant($f['tenant'], ['id' => $f['tenant'], 'role' => 'tenant']));
        self::assertFalse(Access::tenant($f['tenant'], ['id' => $f['outsider'], 'role' => 'tenant']));
        self::assertTrue(Access::tenant($f['tenant'], ['id' => $f['manager'], 'role' => 'property_manager']));
    }

    public function testTicketAccessUsesTenancyForTenantsAndPropertyForStaff(): void
    {
        $f = $this->fixture();

        self::assertTrue(Access::ticket($f['ticket'], ['id' => $f['tenant'], 'role' => 'tenant']));
        self::assertFalse(Access::ticket($f['ticket'], ['id' => $f['outsider'], 'role' => 'tenant']));
        self::assertTrue(Access::ticket($f['ticket'], ['id' => $f['landlord'], 'role' => 'landlord']));
        self::assertTrue(Access::ticket($f['ticket'], ['id' => $f['manager'], 'role' => 'property_manager']));
        self::assertFalse(Access::ticket(999_999, ['id' => $f['landlord'], 'role' => 'landlord']));
    }
}
