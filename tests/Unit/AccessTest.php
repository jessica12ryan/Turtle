<?php

namespace Tests\Unit;

use App\Core\Access;
use Tests\TestCase;

final class AccessTest extends TestCase
{
    public function testUserReturnsTheExplicitlyProvidedUser(): void
    {
        $user = ['id' => 7, 'role' => 'landlord'];

        self::assertSame($user, Access::user($user));
    }

    public function testAdminCanManageAnyUser(): void
    {
        self::assertTrue(Access::canManageUser(
            ['id' => 99, 'role' => 'tenant'],
            ['id' => 1, 'role' => 'admin']
        ));
    }

    public function testUserCanManageOwnAccount(): void
    {
        self::assertTrue(Access::canManageUser(
            ['id' => 5, 'role' => 'tenant'],
            ['id' => 5, 'role' => 'tenant']
        ));
    }

    public function testLowerRoleCannotManageHigherRole(): void
    {
        self::assertFalse(Access::canManageUser(
            ['id' => 1, 'role' => 'admin'],
            ['id' => 2, 'role' => 'landlord']
        ));
        self::assertFalse(Access::canManageUser(
            ['id' => 2, 'role' => 'maintenance'],
            ['id' => 3, 'role' => 'tenant']
        ));
    }

    public function testEffectiveRolesTrimsDeduplicatesAndKeepsPrimaryFirst(): void
    {
        self::assertSame(
            ['tenant', 'admin', 'landlord'],
            effective_roles(['role' => 'tenant', 'secondary_roles' => ' admin , tenant,landlord '])
        );
        self::assertSame([], effective_roles([]));
    }

    public function testAdminCanAccessEveryProperty(): void
    {
        self::assertTrue(user_can_access_property(
            ['id' => 1, 'role' => 'admin'],
            ['company_id' => 0, 'property_manager_id' => null],
            []
        ));
    }

    public function testPropertyWithoutCompanyIsNotAccessible(): void
    {
        self::assertFalse(user_can_access_property(
            ['id' => 2, 'role' => 'landlord'],
            ['company_id' => 0, 'property_manager_id' => 2],
            [1]
        ));
    }

    public function testLandlordAndMaintenanceUseCompanyMembership(): void
    {
        foreach (['landlord', 'maintenance'] as $role) {
            self::assertTrue(user_can_access_property(
                ['id' => 3, 'role' => $role],
                ['company_id' => 1, 'property_manager_id' => 99],
                [1, 2]
            ), $role);

            self::assertFalse(user_can_access_property(
                ['id' => 3, 'role' => $role],
                ['company_id' => 5, 'property_manager_id' => 99],
                [1, 2]
            ), $role);
        }
    }

    public function testPropertyManagerUsesDirectAssignment(): void
    {
        self::assertTrue(user_can_access_property(
            ['id' => 8, 'role' => 'property_manager'],
            ['company_id' => 1, 'property_manager_id' => 8],
            []
        ));
        self::assertFalse(user_can_access_property(
            ['id' => 8, 'role' => 'property_manager'],
            ['company_id' => 1, 'property_manager_id' => 9],
            [1]
        ));
    }

    public function testTenantOnlyRoleCannotAccessPropertyThroughCompany(): void
    {
        self::assertFalse(user_can_access_property(
            ['id' => 4, 'role' => 'tenant'],
            ['company_id' => 1, 'property_manager_id' => 4],
            [1]
        ));
    }
}
