<?php

namespace Tests\Integration;

use App\Controllers\ApplicationController;
use App\Core\Database;

final class ApplicationConversionTest extends IntegrationTestCase
{
    public function testConvertiblePropertiesIncludeDelistedVacantProperties(): void
    {
        $admin = $this->insertUser('convert-admin@example.com', 'admin');
        $landlord = $this->insertUser('convert-landlord@example.com', 'landlord');
        $tenant = $this->insertUser('convert-tenant@example.com', 'tenant');
        $company = $this->insertCompany('Convert Co');

        $listedVacant = $this->insertProperty($company, $landlord, 'Listed Vacant');
        Database::execute('UPDATE properties SET listed = 1 WHERE id = ?', [$listedVacant]);
        $delistedVacant = $this->insertProperty($company, $landlord, 'Delisted Vacant');
        $occupied = $this->insertProperty($company, $landlord, 'Occupied');
        $archived = $this->insertProperty($company, $landlord, 'Archived');
        Database::execute('UPDATE properties SET archived_at = NOW() WHERE id = ?', [$archived]);
        Database::insert(
            'INSERT INTO property_tenant (property_id, tenant_id, is_main_tenant) VALUES (?, ?, 1)',
            [$occupied, $tenant]
        );

        $ids = array_map('intval', array_column(
            ApplicationController::convertibleProperties(['id' => $admin, 'role' => 'admin']),
            'id'
        ));

        self::assertContains($listedVacant, $ids);
        self::assertContains($delistedVacant, $ids);
        self::assertNotContains($occupied, $ids);
        self::assertNotContains($archived, $ids);
    }

    public function testConvertiblePropertiesRespectStaffScope(): void
    {
        $landlord = $this->insertUser('scope-landlord@example.com', 'landlord');
        $otherLandlord = $this->insertUser('scope-other@example.com', 'landlord');
        $ownCompany = $this->insertCompany('Own Co');
        $otherCompany = $this->insertCompany('Other Co');
        Database::execute('INSERT INTO company_user (company_id, user_id) VALUES (?, ?)', [$ownCompany, $landlord]);
        Database::execute('INSERT INTO company_user (company_id, user_id) VALUES (?, ?)', [$otherCompany, $otherLandlord]);

        $own = $this->insertProperty($ownCompany, $landlord, 'Own Vacant');
        $other = $this->insertProperty($otherCompany, $otherLandlord, 'Other Vacant');
        $user = ['id' => $landlord, 'role' => 'landlord'];

        $ids = array_map('intval', array_column(
            ApplicationController::convertibleProperties($user),
            'id'
        ));

        self::assertContains($own, $ids);
        self::assertNotContains($other, $ids);
        self::assertTrue(ApplicationController::propertyIsConvertible($own, $user));
        self::assertFalse(ApplicationController::propertyIsConvertible($other, $user));
    }

    public function testGuardRejectsOccupiedAndArchivedProperties(): void
    {
        $admin = $this->insertUser('guard-admin@example.com', 'admin');
        $landlord = $this->insertUser('guard-landlord@example.com', 'landlord');
        $tenant = $this->insertUser('guard-tenant@example.com', 'tenant');
        $company = $this->insertCompany('Guard Co');

        $vacant = $this->insertProperty($company, $landlord, 'Vacant');
        $occupied = $this->insertProperty($company, $landlord, 'Occupied');
        $archived = $this->insertProperty($company, $landlord, 'Archived');
        Database::execute('UPDATE properties SET archived_at = NOW() WHERE id = ?', [$archived]);
        Database::insert(
            'INSERT INTO property_tenant (property_id, tenant_id, is_main_tenant) VALUES (?, ?, 1)',
            [$occupied, $tenant]
        );

        $user = ['id' => $admin, 'role' => 'admin'];

        self::assertTrue(ApplicationController::propertyIsConvertible($vacant, $user));
        self::assertFalse(ApplicationController::propertyIsConvertible($occupied, $user));
        self::assertFalse(ApplicationController::propertyIsConvertible($archived, $user));
    }
}
