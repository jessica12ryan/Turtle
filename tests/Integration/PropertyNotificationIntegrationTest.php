<?php

namespace Tests\Integration;

use App\Core\Database;

final class PropertyNotificationIntegrationTest extends IntegrationTestCase
{
    public function testManagerReceivesPropertyNotificationWithLandlordFallback(): void
    {
        $companyId = $this->insertCompany();
        $landlordId = $this->insertUser('landlord-notify@example.com', 'landlord');
        $managerId = $this->insertUser('manager-notify@example.com', 'property_manager');
        $propertyId = $this->insertProperty($companyId, $landlordId);
        Database::execute("UPDATE properties SET property_manager_id = ? WHERE id = ?", [$managerId, $propertyId]);

        $recipients = propertyNotificationRecipients($propertyId, 'new_application', 'inapp');
        self::assertCount(1, $recipients);
        self::assertSame($managerId, (int) $recipients[0]['id']);

        Database::execute("UPDATE properties SET property_manager_id = NULL WHERE id = ?", [$propertyId]);
        $recipients = propertyNotificationRecipients($propertyId, 'new_application', 'inapp');
        self::assertCount(1, $recipients);
        self::assertSame($landlordId, (int) $recipients[0]['id']);
    }

    public function testTenantIsNeverAPropertyNotificationRecipient(): void
    {
        $companyId = $this->insertCompany();
        $landlordId = $this->insertUser('landlord-notify2@example.com', 'landlord');
        $tenantId = $this->insertUser('tenant-notify@example.com', 'tenant');
        $propertyId = $this->insertProperty($companyId, $landlordId);
        Database::execute("UPDATE properties SET property_manager_id = ? WHERE id = ?", [$tenantId, $propertyId]);

        self::assertSame([], propertyNotificationRecipients($propertyId, 'new_guest_chat', 'email'));
    }
}
