<?php

namespace Tests\Integration;

use App\Core\Database;

final class SchemaIntegrityTest extends IntegrationTestCase
{
    public function testInvalidTicketStatusAndPriorityAreRejected(): void
    {
        $landlord = $this->insertUser('schema-landlord@example.com', 'landlord');
        $tenant = $this->insertUser('schema-tenant@example.com', 'tenant');
        $property = $this->insertProperty($this->insertCompany(), $landlord);

        Database::execute(
            "INSERT INTO tickets (property_id, tenant_id, subject, description, category, status, priority, created_at, updated_at)
             VALUES (?, ?, 'Valid', 'Body', 'plumbing', 'resolved', 'emergency', NOW(), NOW())",
            [$property, $tenant]
        );

        try {
            Database::execute(
                "INSERT INTO tickets (property_id, tenant_id, subject, description, category, status, priority, created_at, updated_at)
                 VALUES (?, ?, 'Invalid', 'Body', 'plumbing', 'bogus', 'medium', NOW(), NOW())",
                [$property, $tenant]
            );
            self::fail('an invalid ticket status must be rejected by the database');
        } catch (\PDOException $e) {
            self::assertStringContainsString('chk_tickets_status', $e->getMessage());
        }

        try {
            Database::execute(
                "INSERT INTO tickets (property_id, tenant_id, subject, description, category, status, priority, created_at, updated_at)
                 VALUES (?, ?, 'Invalid', 'Body', 'plumbing', 'open', 'urgent', NOW(), NOW())",
                [$property, $tenant]
            );
            self::fail('an invalid ticket priority must be rejected by the database');
        } catch (\PDOException $e) {
            self::assertStringContainsString('chk_tickets_priority', $e->getMessage());
        }
    }

    public function testApplicationCannotReferenceNonexistentProperty(): void
    {
        try {
            Database::execute(
                "INSERT INTO tenant_applications (property_id, status, data, created_at, updated_at)
                 VALUES (999999, 'new', '{}', NOW(), NOW())"
            );
            self::fail('an application must not reference a missing property');
        } catch (\PDOException $e) {
            self::assertStringContainsString('FOREIGN KEY', strtoupper($e->getMessage()));
        }
    }

    public function testDeletingDocumentUploaderClearsTheReference(): void
    {
        $uploader = $this->insertUser('schema-uploader@example.com', 'landlord');
        $documentId = Database::insert(
            "INSERT INTO documents (documentable_type, documentable_id, file_path, original_name, uploaded_by, created_at, updated_at)
             VALUES ('lease', 1, '/tmp/x.pdf', 'x.pdf', ?, NOW(), NOW())",
            [$uploader]
        );

        Database::execute("DELETE FROM users WHERE id = ?", [$uploader]);

        $document = Database::fetch("SELECT uploaded_by FROM documents WHERE id = ?", [$documentId]);
        self::assertNotNull($document);
        self::assertNull($document['uploaded_by']);
    }
}
