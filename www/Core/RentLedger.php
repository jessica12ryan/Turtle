<?php

namespace App\Core;

/**
 * Shared reads and writes for rent payment periods. Every status display goes
 * through here so "this period" means the same thing on all screens.
 */
class RentLedger
{
    private const LOOKBACK_PERIODS = 12;

    /** Exempting a period is deliberately limited to admins and landlords. */
    public static function canExempt(array $user): bool
    {
        return \has_role($user, 'admin') || \has_role($user, 'landlord');
    }

    /** @param array<string, mixed> $property */
    public static function dueDay(array $property): int
    {
        return RentPeriod::dueDay($property['rent_due_day'] ?? null);
    }

    /** @return list<array<string, mixed>> */
    private static function paymentRows(int $propertyId): array
    {
        return Database::fetchAll(
            "SELECT p.id, p.property_tenant_id, p.amount, p.payment_date, p.period_start,
                    p.is_security_deposit, p.archived_at, pt.moved_out_at
             FROM payments p
             JOIN property_tenant pt ON pt.id = p.property_tenant_id
             WHERE pt.property_id = ?",
            [$propertyId]
        );
    }

    /** @return list<array<string, mixed>> */
    private static function exemptionRows(int $propertyId): array
    {
        return Database::fetchAll(
            "SELECT e.property_tenant_id, e.period_start, e.reason
             FROM rent_period_exemptions e
             JOIN property_tenant pt ON pt.id = e.property_tenant_id
             WHERE pt.property_id = ?",
            [$propertyId]
        );
    }

    /** @return list<array<string, mixed>> */
    private static function tenancyRows(int $propertyId): array
    {
        return Database::fetchAll(
            "SELECT pt.id, pt.is_main_tenant, pt.moved_out_at, pt.lease_start, pt.assigned_at,
                    u.archived_at AS tenant_archived_at
             FROM property_tenant pt JOIN users u ON u.id = pt.tenant_id
             WHERE pt.property_id = ?
             ORDER BY COALESCE(pt.lease_start, DATE(pt.assigned_at)), pt.id",
            [$propertyId]
        );
    }

    /** @param array<string, mixed> $payment */
    private static function periodOf(array $payment, int $dueDay): string
    {
        $periodStart = (string) ($payment['period_start'] ?? '');
        return $periodStart !== ''
            ? $periodStart
            : RentPeriod::forDate((string) $payment['payment_date'], $dueDay);
    }

    /** @param array<string, mixed> $payment */
    private static function countsForPayment(array $payment): bool
    {
        // A scheduled move-out archives every payment of that tenancy. Those
        // rows are history, not reversals, so they still count for their period.
        return empty($payment['archived_at']) || !empty($payment['moved_out_at']);
    }

    /** @param list<array<string, mixed>> $tenancies */
    private static function activeTenancyId(array $tenancies): int
    {
        foreach ($tenancies as $tenancy) {
            if (
                empty($tenancy['moved_out_at']) && empty($tenancy['tenant_archived_at'])
                && !empty($tenancy['is_main_tenant'])
            ) {
                return (int) $tenancy['id'];
            }
        }
        foreach ($tenancies as $tenancy) {
            if (empty($tenancy['moved_out_at']) && empty($tenancy['tenant_archived_at'])) {
                return (int) $tenancy['id'];
            }
        }
        return $tenancies !== [] ? (int) $tenancies[0]['id'] : 0;
    }

    private static function nextMonthDate(string $date): string
    {
        return (new \DateTimeImmutable($date))->modify('first day of next month')->format('Y-m-d');
    }

    public static function validPeriodStart(string $start, int $dueDay): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) === 1
            && (int) substr($start, 8, 2) === $dueDay
            && $start === RentPeriod::forDate($start, $dueDay);
    }

    public static function isExempt(int $propertyTenantId, string $start): bool
    {
        if ($propertyTenantId <= 0) {
            return false;
        }
        return Database::fetch(
            "SELECT 1 FROM rent_period_exemptions WHERE property_tenant_id = ? AND period_start = ?",
            [$propertyTenantId, $start]
        ) !== null;
    }

    public static function exempt(int $propertyTenantId, string $start, int $userId, string $reason): void
    {
        Database::execute(
            "INSERT INTO rent_period_exemptions (property_tenant_id, period_start, reason, marked_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE reason = VALUES(reason), marked_by = VALUES(marked_by), updated_at = NOW()",
            [$propertyTenantId, $start, $reason !== '' ? $reason : null, $userId]
        );
    }

    public static function unexempt(int $propertyTenantId, string $start): void
    {
        Database::execute(
            "DELETE FROM rent_period_exemptions WHERE property_tenant_id = ? AND period_start = ?",
            [$propertyTenantId, $start]
        );
    }

    /**
     * Every period with rent data, plus the current and next one. Rows for
     * archived tenancies stay in the list so no payment history is lost.
     *
     * @param array<string, mixed> $property
     * @return list<array<string, mixed>>
     */
    public static function periods(int $propertyId, array $property): array
    {
        $dueDay = self::dueDay($property);
        $rent = (float) ($property['rent_amount'] ?? 0);
        $tenancies = self::tenancyRows($propertyId);
        $payments = self::paymentRows($propertyId);
        $exemptions = self::exemptionRows($propertyId);

        $dates = [date('Y-m-d')];
        foreach ($tenancies as $tenancy) {
            if (!empty($tenancy['lease_start'])) {
                $dates[] = substr((string) $tenancy['lease_start'], 0, 10);
            }
            if (!empty($tenancy['assigned_at'])) {
                $dates[] = substr((string) $tenancy['assigned_at'], 0, 10);
            }
        }
        foreach ($payments as $payment) {
            $dates[] = substr((string) $payment['payment_date'], 0, 10);
            if (!empty($payment['period_start'])) {
                $dates[] = (string) $payment['period_start'];
            }
        }
        foreach ($exemptions as $exemption) {
            $dates[] = (string) $exemption['period_start'];
        }
        sort($dates);

        $first = RentPeriod::forDate($dates[0], $dueDay);
        $last = RentPeriod::forDate(self::nextMonthDate($dates[count($dates) - 1]), $dueDay);

        $rows = [];
        foreach (RentPeriod::starts($first, $last, $dueDay) as $start) {
            $paid = 0.0;
            $paymentCount = 0;
            $tenancyIds = [];
            foreach ($payments as $payment) {
                if (!empty($payment['is_security_deposit']) || !self::countsForPayment($payment)) {
                    continue;
                }
                if (self::periodOf($payment, $dueDay) !== $start) {
                    continue;
                }
                $paid += (float) $payment['amount'];
                $paymentCount++;
                $tenancyIds[(int) $payment['property_tenant_id']] = true;
            }

            $exemption = null;
            foreach ($exemptions as $candidate) {
                if ((string) $candidate['period_start'] === $start) {
                    $exemption = $candidate;
                    break;
                }
            }

            if ($rent <= 0) {
                $status = 'not_set';
            } elseif ($exemption !== null) {
                $status = 'exempt';
            } elseif ($paid >= $rent) {
                $status = 'paid';
            } elseif ($paid > 0) {
                $status = 'partial';
            } else {
                $status = 'unpaid';
            }

            $tenancyId = $exemption !== null
                ? (int) $exemption['property_tenant_id']
                : (array_key_first($tenancyIds) ?: self::activeTenancyId($tenancies));

            $rows[] = [
                'start' => $start,
                'end' => RentPeriod::end($start, $dueDay),
                'label' => RentPeriod::label($start, $dueDay),
                'paid_amount' => $paid,
                'payment_count' => $paymentCount,
                'status' => $status,
                'exempt' => $exemption !== null,
                'exempt_reason' => $exemption['reason'] ?? null,
                'property_tenant_id' => $tenancyId,
            ];
        }
        return $rows;
    }

    /**
     * Status of the period containing today.
     *
     * @param array<string, mixed> $property
     * @return array<string, mixed>
     */
    public static function status(int $propertyId, array $property): array
    {
        $start = RentPeriod::forDate(date('Y-m-d'), self::dueDay($property));
        foreach (self::periods($propertyId, $property) as $row) {
            if ($row['start'] === $start) {
                return $row;
            }
        }
        return [
            'start' => $start,
            'end' => RentPeriod::end($start, self::dueDay($property)),
            'label' => RentPeriod::label($start, self::dueDay($property)),
            'paid_amount' => 0.0,
            'status' => (float) ($property['rent_amount'] ?? 0) > 0 ? 'unpaid' : 'not_set',
            'exempt' => false,
        ];
    }

    /**
     * Most recent unpaid period of the active tenancy, or the current period
     * when everything is paid. Partial periods still count as unpaid.
     *
     * @param array<string, mixed> $property
     */
    public static function defaultPeriod(int $propertyId, array $property, int $propertyTenantId): string
    {
        $dueDay = self::dueDay($property);
        $rent = (float) ($property['rent_amount'] ?? 0);
        $today = date('Y-m-d');
        $current = RentPeriod::forDate($today, $dueDay);

        $tenancyStart = null;
        foreach (self::tenancyRows($propertyId) as $tenancy) {
            if ((int) $tenancy['id'] === $propertyTenantId) {
                $tenancyStart = !empty($tenancy['lease_start'])
                    ? substr((string) $tenancy['lease_start'], 0, 10)
                    : substr((string) $tenancy['assigned_at'], 0, 10);
                break;
            }
        }

        $payments = self::paymentRows($propertyId);
        $exemptions = self::exemptionRows($propertyId);
        for ($i = 0; $i < self::LOOKBACK_PERIODS; $i++) {
            $start = RentPeriod::offset($current, -$i, $dueDay);
            if ($start > $today) {
                continue;
            }
            if ($tenancyStart !== null && RentPeriod::end($start, $dueDay) < $tenancyStart) {
                break;
            }
            $isExempt = false;
            foreach ($exemptions as $exemption) {
                if (
                    (int) $exemption['property_tenant_id'] === $propertyTenantId
                    && (string) $exemption['period_start'] === $start
                ) {
                    $isExempt = true;
                    break;
                }
            }
            if ($isExempt) {
                continue;
            }
            $paid = 0.0;
            foreach ($payments as $payment) {
                if ((int) $payment['property_tenant_id'] !== $propertyTenantId) {
                    continue;
                }
                if (!empty($payment['is_security_deposit']) || !empty($payment['archived_at'])) {
                    continue;
                }
                if (self::periodOf($payment, $dueDay) === $start) {
                    $paid += (float) $payment['amount'];
                }
            }
            if ($paid < $rent) {
                return $start;
            }
        }
        return $current;
    }
}
