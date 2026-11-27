<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Core\RentLedger;
use App\Core\RentPeriod;

final class RentLedgerIntegrationTest extends IntegrationTestCase
{
    /** @return array{property:array<string,mixed>,property_id:int,property_tenant_id:int,landlord:int,tenant:int} */
    private function fixture(string $email = 'rent-ledger@test.local', int $dueDay = 2): array
    {
        $company = $this->insertCompany('Rent Ledger Company ' . $email);
        $landlord = $this->insertUser('landlord-' . $email, 'landlord');
        $tenant = $this->insertUser($email, 'tenant');

        $propertyId = Database::insert(
            "INSERT INTO properties (landlord_id, company_id, name, rent_amount, rent_due_day, created_at, updated_at)
             VALUES (?, ?, 'Ledger Property', 1000.00, ?, NOW(), NOW())",
            [$landlord, $company, $dueDay]
        );
        $propertyTenantId = Database::insert(
            "INSERT INTO property_tenant (property_id, tenant_id, is_main_tenant, lease_start, assigned_at)
             VALUES (?, ?, 1, '2026-01-01', NOW())",
            [$propertyId, $tenant]
        );

        $property = Database::fetch("SELECT * FROM properties WHERE id = ?", [$propertyId]);

        return [
            'property' => $property ?? [],
            'property_id' => $propertyId,
            'property_tenant_id' => $propertyTenantId,
            'landlord' => $landlord,
            'tenant' => $tenant,
        ];
    }

    private function addPayment(
        int $propertyId,
        int $propertyTenantId,
        int $recordedBy,
        string $periodStart,
        float $amount,
        bool $archived = false
    ): void {
        Database::insert(
            "INSERT INTO payments (property_id, property_tenant_id, tenant_name_snapshot, amount, payment_date, period_start, recorded_by, archived_at, created_at, updated_at)
             VALUES (?, ?, 'Ledger Tenant', ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $propertyId,
                $propertyTenantId,
                $amount,
                $periodStart,
                $periodStart,
                $recordedBy,
                $archived ? date('Y-m-d') : null,
            ]
        );
    }

    public function testExemptIsLimitedToAdminsAndLandlords(): void
    {
        self::assertTrue(RentLedger::canExempt(['role' => 'admin']));
        self::assertTrue(RentLedger::canExempt(['role' => 'landlord']));
        self::assertTrue(RentLedger::canExempt(['role' => 'tenant', 'secondary_roles' => 'landlord']));
        self::assertFalse(RentLedger::canExempt(['role' => 'property_manager']));
        self::assertFalse(RentLedger::canExempt(['role' => 'tenant']));
    }

    public function testPeriodStatusUsesTheStoredPeriodAndExemption(): void
    {
        $f = $this->fixture();
        $property = $f['property'];
        $this->addPayment($f['property_id'], $f['property_tenant_id'], $f['landlord'], '2026-01-02', 1000.00);

        $rows = RentLedger::periods($f['property_id'], $property);
        $january = null;
        foreach ($rows as $row) {
            if ($row['start'] === '2026-01-02') {
                $january = $row;
                break;
            }
        }
        self::assertNotNull($january);
        self::assertSame('2026-02-01', $january['end']);
        self::assertSame('paid', $january['status']);
        self::assertSame(1000.0, $january['paid_amount']);

        RentLedger::exempt($f['property_id'], $f['property_tenant_id'], '2026-01-02', $f['landlord'], 'Not rented');
        $rows = RentLedger::periods($f['property_id'], $property);
        foreach ($rows as $row) {
            if ($row['start'] === '2026-01-02') {
                self::assertSame('exempt', $row['status']);
                self::assertTrue($row['exempt']);
            }
        }

        // Re-marking is idempotent thanks to the unique key.
        RentLedger::exempt($f['property_id'], $f['property_tenant_id'], '2026-01-02', $f['landlord'], 'Different reason');
        self::assertSame(
            1,
            (int) Database::fetch(
                "SELECT COUNT(*) AS cnt FROM rent_period_exemptions WHERE property_id = ? AND period_start = '2026-01-02'",
                [$f['property_id']]
            )['cnt']
        );

        RentLedger::unexempt($f['property_id'], '2026-01-02');
        self::assertFalse(RentLedger::isExempt($f['property_id'], '2026-01-02'));
    }

    public function testDefaultPeriodSkipsPaidAndExemptPeriods(): void
    {
        $f = $this->fixture('default-period@test.local');
        $property = $f['property'];
        $current = RentPeriod::forDate(date('Y-m-d'), 2);

        // The tenancy spans four periods; the oldest is deliberately unpaid.
        $target = RentPeriod::offset($current, -3, 2);
        Database::execute(
            'UPDATE property_tenant SET lease_start = ? WHERE id = ?',
            [RentPeriod::offset($target, -1, 2), $f['property_tenant_id']]
        );
        foreach ([-2, -1, 0] as $offset) {
            $this->addPayment(
                $f['property_id'],
                $f['property_tenant_id'],
                $f['landlord'],
                RentPeriod::offset($current, $offset, 2),
                1000.00
            );
        }

        self::assertSame($target, RentLedger::defaultPeriod($f['property_id'], $property, $f['property_tenant_id']));

        RentLedger::exempt($f['property_id'], $f['property_tenant_id'], $target, $f['landlord'], 'Not rented');
        self::assertSame(
            RentPeriod::offset($target, -1, 2),
            RentLedger::defaultPeriod($f['property_id'], $property, $f['property_tenant_id'])
        );
    }

    public function testArchivedPaymentsOfMovedOutTenanciesStayInTheLedger(): void
    {
        $f = $this->fixture('archived-rent@test.local');
        $property = $f['property'];
        $this->addPayment($f['property_id'], $f['property_tenant_id'], $f['landlord'], '2026-01-02', 1000.00, true);
        Database::execute(
            'UPDATE property_tenant SET moved_out_at = NOW() WHERE id = ?',
            [$f['property_tenant_id']]
        );

        $rows = RentLedger::periods($f['property_id'], $property);
        foreach ($rows as $row) {
            if ($row['start'] === '2026-01-02') {
                self::assertSame(1, $row['payment_count']);
                self::assertSame(1000.0, $row['paid_amount']);
                self::assertSame('paid', $row['status']);
                return;
            }
        }
        self::fail('Historical pay period missing from the ledger.');
    }

    public function testVacantPropertiesExpectNoRent(): void
    {
        $f = $this->fixture('vacant-rent@test.local');
        Database::execute('UPDATE users SET archived_at = NOW() WHERE id = ?', [$f['tenant']]);

        self::assertNull(RentLedger::activeTenancy($f['property_id']));
        $status = RentLedger::status($f['property_id'], $f['property']);
        self::assertSame('vacant', $status['status']);

        $current = RentPeriod::forDate(date('Y-m-d'), 2);
        foreach (RentLedger::periods($f['property_id'], $f['property']) as $row) {
            if ($row['start'] === $current) {
                self::assertSame('vacant', $row['status']);
                self::assertFalse($row['expected']);
            }
        }
    }

    public function testPaymentHistorySurvivesTenantDeletion(): void
    {
        $f = $this->fixture('deleted-tenant@test.local');
        $this->addPayment($f['property_id'], $f['property_tenant_id'], $f['landlord'], '2026-01-02', 1000.00, true);
        RentLedger::exempt($f['property_id'], $f['property_tenant_id'], '2026-01-02', $f['landlord'], 'Vacant');

        // Mirrors the FK behaviour of a hard tenant delete.
        Database::execute('DELETE FROM property_tenant WHERE id = ?', [$f['property_tenant_id']]);

        $payment = Database::fetch("SELECT * FROM payments WHERE property_id = ? AND period_start = '2026-01-02'", [$f['property_id']]);
        self::assertNotNull($payment);
        self::assertNull($payment['property_tenant_id']);
        self::assertSame('Ledger Tenant', $payment['tenant_name_snapshot']);

        $rows = RentLedger::periods($f['property_id'], $f['property']);
        foreach ($rows as $row) {
            if ($row['start'] === '2026-01-02') {
                self::assertSame(1000.0, $row['paid_amount']);
                self::assertSame(1, $row['payment_count']);
                return;
            }
        }
        self::fail('Payment history was detached from the property.');
    }

    public function testValidPeriodStartIsCheckedAgainstTheDueDay(): void
    {
        self::assertTrue(RentLedger::validPeriodStart('2026-01-02', 2));
        self::assertFalse(RentLedger::validPeriodStart('2026-01-03', 2));
        self::assertFalse(RentLedger::validPeriodStart('not-a-date', 2));
    }
}
