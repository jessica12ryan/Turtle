<?php

namespace Tests\Unit;

use App\Core\View;
use Tests\TestCase;

final class VacantRentViewTest extends TestCase
{
    public function testVacantPropertyKeepsHistoryButOffersNoPaymentOrExemption(): void
    {
        $view = new View();

        ob_start();
        $view->render('rent/show', [
            'property' => [
                'id' => 1,
                'name' => 'Vacant Unit',
                'address' => '1 Main St',
                'city' => 'Toronto',
                'province' => 'ON',
                'rent_amount' => 1000.00,
                'rent_due_day' => 1,
            ],
            'tenants' => [],
            'payments' => [[
                'id' => 7,
                'payment_date' => '2026-10-01',
                'period_start' => '2026-10-01',
                'tenant_name' => 'Former Tenant',
                'amount' => 1000.00,
                'payment_method' => 'e-transfer',
                'reference' => '',
                'notes' => '',
                'is_security_deposit' => 0,
                'recorded_by_name' => 'Admin',
                'archived_at' => null,
            ]],
            'status' => [
                'status' => 'vacant',
                'label' => '2026-11-01 – 2026-11-30',
                'paid_amount' => 0.0,
            ],
            'periods' => [[
                'start' => '2026-10-01',
                'end' => '2026-10-31',
                'label' => '2026-10-01 – 2026-10-31',
                'paid_amount' => 1000.0,
                'payment_count' => 1,
                'status' => 'paid',
                'expected' => false,
                'exempt' => false,
                'property_tenant_id' => 0,
            ]],
            'dueDay' => 1,
            'defaultPeriod' => '2026-11-01',
            'mainTenantId' => 0,
            'hasActiveTenant' => false,
            'canExempt' => false,
        ]);
        $output = (string) ob_get_clean();

        self::assertStringContainsString('Vacant', $output);
        self::assertStringContainsString('No rent is expected while this property has no tenant.', $output);
        self::assertStringContainsString('Payment History', $output);
        self::assertStringNotContainsString('name="amount"', $output);
        self::assertStringNotContainsString('Mark Exempt', $output);
    }
}
