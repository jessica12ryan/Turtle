<?php

namespace Tests\Unit;

use Tests\TestCase;

final class RoleScopingTest extends TestCase
{
    public function testSecondaryRolesAreHonoured(): void
    {
        $user = ['id' => 1, 'role' => 'tenant', 'secondary_roles' => ' property_manager, landlord '];

        self::assertTrue(has_role($user, 'property_manager'));
        self::assertTrue(is_property_manager_user($user));
        self::assertFalse(is_tenant_only_user($user), 'a tenant with staff roles is not tenant-only');
        self::assertTrue(user_can($user, 'tickets.assign'), 'secondary PM permissions must count');
    }

    public function testTenantOnlyUsersHaveTenantPermissionsOnly(): void
    {
        $user = ['id' => 2, 'role' => 'tenant', 'secondary_roles' => ''];

        self::assertTrue(is_tenant_only_user($user));
        self::assertTrue(user_can($user, 'tickets.access'));
        self::assertFalse(user_can($user, 'tickets.assign'));
        self::assertFalse(is_admin_user($user));
    }

    public function testAdminHasEveryPermission(): void
    {
        $user = ['id' => 3, 'role' => 'admin', 'secondary_roles' => ''];
        self::assertTrue(user_can($user, 'anything.at.all'));
    }
}
