<?php

namespace Tests\Integration;

use App\Core\Database;

final class ScopingIntegrationTest extends IntegrationTestCase
{
    public function testSecondaryPropertyManagerRoleScopesToManagedProperties(): void
    {
        $landlord = $this->insertUser('scoping-landlord@example.com', 'landlord');
        $manager = $this->insertUser('scoping-manager@example.com', 'tenant');
        $company = $this->insertCompany();
        $managed = $this->insertProperty($company, $landlord, 'Managed Property');
        $other = $this->insertProperty($company, $landlord, 'Other Property');

        Database::execute("UPDATE users SET secondary_roles = 'property_manager' WHERE id = ?", [$manager]);
        Database::execute("INSERT INTO company_user (company_id, user_id) VALUES (?, ?)", [$company, $manager]);
        Database::execute("UPDATE properties SET property_manager_id = ? WHERE id = ?", [$manager, $managed]);

        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$manager]);
        self::assertNotNull($user);

        $ids = accessible_property_ids($user);
        self::assertContains($managed, $ids, 'a secondary PM role must expose managed properties');
        self::assertNotContains($other, $ids, 'unmanaged properties must stay hidden');
        self::assertTrue(user_can($user, 'tickets.assign'), 'secondary role permissions must apply');
    }

    public function testTenantOnlyUserSeesOnlyTheirActiveTenancies(): void
    {
        $landlord = $this->insertUser('scoping-owner@example.com', 'landlord');
        $tenant = $this->insertUser('scoping-tenant@example.com', 'tenant');
        $company = $this->insertCompany();
        $mine = $this->insertProperty($company, $landlord, 'My Property');
        $theirs = $this->insertProperty($company, $landlord, 'Other Property');

        Database::execute(
            "INSERT INTO property_tenant (property_id, tenant_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())",
            [$mine, $tenant]
        );

        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$tenant]);
        self::assertNotNull($user);
        self::assertSame([$mine], accessible_property_ids($user));
        self::assertNotContains($theirs, accessible_property_ids($user));
    }
}
