<?php

namespace App\Controllers;

use App\Core\Access;
use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Core\Validator;
use App\Core\Tenancy;
use App\Core\RentLedger;
use App\Core\RentPeriod;

class RentController
{
    public function index(): void
    {
        $auth = Auth::instance();
        $user = $auth->user();

        $propertyIdList = implode(',', accessible_property_ids($user)) ?: '0';

        $properties = Database::fetchAll(
            "SELECT p.*, u.name as landlord_name,
             " . Tenancy::tenantCountSql('p') . " as tenants_count
             FROM properties p
             JOIN users u ON u.id = p.landlord_id
             WHERE p.id IN ({$propertyIdList}) AND p.archived_at IS NULL
             ORDER BY p.name"
        );

        $rentData = [];
        foreach ($properties as $prop) {
            $totalRent = $prop['rent_amount'] ?? 0;
            if ($totalRent <= 0) {
                continue;
            }

            $status = RentLedger::status((int) $prop['id'], $prop);

            $lastPayment = Database::fetch(
                "SELECT p.payment_date, p.amount
                 FROM payments p
                 JOIN property_tenant pt ON pt.id = p.property_tenant_id
                 WHERE pt.property_id = ?
                 ORDER BY p.payment_date DESC LIMIT 1",
                [$prop['id']]
            );

            $prop['paid_amount'] = $status['paid_amount'];
            $prop['rent_status'] = $status['status'];
            $prop['period_label'] = $status['label'];
            $prop['last_payment_date'] = $lastPayment['payment_date'] ?? null;
            $prop['last_payment_amount'] = $lastPayment['amount'] ?? null;
            $rentData[] = $prop;
        }

        // Calculate totals
        $totalExpected = array_sum(array_column($rentData, 'rent_amount'));
        $totalCollected = array_sum(array_column($rentData, 'paid_amount'));

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Rent']);
        $view->render('rent/index', compact('rentData', 'totalExpected', 'totalCollected'));
    }

    public function show(int $propertyId): void
    {
        $property = Database::fetch(
            "SELECT p.*, l.name as landlord_name FROM properties p
             JOIN users l ON l.id = p.landlord_id WHERE p.id = ?",
            [$propertyId]
        );
        if (!$property) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        Access::require(Access::property($propertyId));

        $tenants = Database::fetchAll(
            "SELECT u.*, pt.id as property_tenant_id, pt.is_main_tenant, pt.assigned_at
             FROM users u
             JOIN property_tenant pt ON pt.tenant_id = u.id
             WHERE pt.property_id = ? AND pt.moved_out_at IS NULL AND u.archived_at IS NULL",
            [$propertyId]
        );

        // Payment history includes archived rows: a scheduled move-out archives
        // every payment of that tenancy, but that history must stay accessible.
        $payments = Database::fetchAll(
            "SELECT p.*, u.name as tenant_name, r.name as recorded_by_name
             FROM payments p
             JOIN property_tenant pt ON pt.id = p.property_tenant_id
             JOIN users u ON u.id = pt.tenant_id
             JOIN users r ON r.id = p.recorded_by
             WHERE pt.property_id = ?
             ORDER BY p.payment_date DESC",
            [$propertyId]
        );

        $status = RentLedger::status($propertyId, $property);
        $periods = RentLedger::periods($propertyId, $property);
        $dueDay = RentLedger::dueDay($property);

        $mainTenantId = 0;
        foreach ($tenants as $tenant) {
            if (!empty($tenant['is_main_tenant'])) {
                $mainTenantId = (int) $tenant['property_tenant_id'];
                break;
            }
        }
        if ($mainTenantId === 0 && $tenants !== []) {
            $mainTenantId = (int) $tenants[0]['property_tenant_id'];
        }
        $defaultPeriod = $mainTenantId > 0
            ? RentLedger::defaultPeriod($propertyId, $property, $mainTenantId)
            : RentPeriod::forDate(date('Y-m-d'), $dueDay);

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Rent - ' . $property['name']]);
        $view->render('rent/show', array_merge(compact(
            'property',
            'tenants',
            'payments',
            'status',
            'periods',
            'dueDay',
            'defaultPeriod',
            'mainTenantId'
        ), ['canExempt' => RentLedger::canExempt(Auth::instance()->user())]));
    }

    public function store(int $propertyId): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
        Access::require(Access::property($propertyId));
        // Auto-detect main tenant for this property
        $mainTenant = Database::fetch(
            "SELECT pt.id FROM property_tenant pt
             JOIN users u ON u.id = pt.tenant_id
             WHERE pt.property_id = ? AND pt.is_main_tenant = 1
               AND " . Tenancy::activeLinkCondition('pt', 'u'),
            [$propertyId]
        );
        if (!$mainTenant) {
            flash('error', __('No active main tenant found for this property.'));
            redirect('/properties/' . $propertyId);
        }

        $property = Database::fetch("SELECT * FROM properties WHERE id = ?", [$propertyId]);

        $validator = new Validator();
        if (
            !$validator->validate($_POST, [
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'max:50',
            'reference' => 'max:100',
            'notes' => 'max:5000',
            ])
        ) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/properties/' . $propertyId . '/rent');
        }

        $dueDay = RentLedger::dueDay($property ?? []);
        $periodStart = trim((string) ($_POST['period_start'] ?? ''));
        if ($periodStart === '') {
            $periodStart = RentLedger::defaultPeriod($propertyId, $property ?? [], (int) $mainTenant['id']);
        } elseif (!RentLedger::validPeriodStart($periodStart, $dueDay)) {
            $_SESSION['_errors'] = ['period_start' => __('Select a valid pay period.')];
            $_SESSION['_old'] = $_POST;
            redirect('/properties/' . $propertyId . '/rent');
        }

        $isDeposit = !empty($_POST['is_security_deposit']) ? 1 : 0;

        Database::insert(
            "INSERT INTO payments (property_tenant_id, amount, payment_date, period_start, payment_method, reference, notes, is_security_deposit, recorded_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $mainTenant['id'],
                $_POST['amount'],
                $_POST['payment_date'],
                $periodStart,
                $_POST['payment_method'] ?: null,
                $_POST['reference'] ?: null,
                $_POST['notes'] ?: null,
                $isDeposit,
                Auth::instance()->id(),
            ]
        );

        log_activity('payment.created', "Payment of \${$_POST['amount']} recorded for property #{$propertyId} (period {$periodStart})");
        flash('success', __('Payment recorded successfully.'));
        redirect('/properties/' . $propertyId . '/rent');
    }

    public function update(int $paymentId): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
        $payment = Database::fetch("SELECT p.*, pt.property_id FROM payments p JOIN property_tenant pt ON pt.id = p.property_tenant_id WHERE p.id = ?", [$paymentId]);
        if (!$payment) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        Access::require(Access::payment($paymentId));

        $validator = new Validator();
        if (
            !$validator->validate($_POST, [
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'max:50',
            'reference' => 'max:100',
            'notes' => 'max:5000',
            ])
        ) {
            $_SESSION['_errors'] = $validator->errors();
            redirect('/properties/' . $payment['property_id'] . '/rent');
        }

        $periodStart = trim((string) ($_POST['period_start'] ?? ''));
        if ($periodStart !== '') {
            $property = Database::fetch("SELECT * FROM properties WHERE id = ?", [$payment['property_id']]);
            if (!RentLedger::validPeriodStart($periodStart, RentLedger::dueDay($property ?? []))) {
                $_SESSION['_errors'] = ['period_start' => __('Select a valid pay period.')];
                redirect('/properties/' . $payment['property_id'] . '/rent');
            }
        }

        Database::execute(
            "UPDATE payments SET amount = ?, payment_date = ?, period_start = COALESCE(?, period_start), payment_method = ?, reference = ?, notes = ?, updated_at = NOW() WHERE id = ?",
            [
                $_POST['amount'],
                $_POST['payment_date'],
                $periodStart !== '' ? $periodStart : null,
                $_POST['payment_method'] ?: null,
                $_POST['reference'] ?: null,
                $_POST['notes'] ?: null,
                $paymentId,
            ]
        );

        log_activity('payment.updated', "Payment #{$paymentId} updated");
        flash('success', __('Payment updated successfully.'));
        redirect('/properties/' . $payment['property_id'] . '/rent');
    }

    public function exempt(int $propertyId): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
        Access::require(Access::property($propertyId));
        Access::require(RentLedger::canExempt(Auth::instance()->user()));

        $property = Database::fetch("SELECT * FROM properties WHERE id = ?", [$propertyId]);
        $periodStart = trim((string) ($_POST['period_start'] ?? ''));
        $propertyTenantId = (int) ($_POST['property_tenant_id'] ?? 0);
        if (
            !$property
            || !RentLedger::validPeriodStart($periodStart, RentLedger::dueDay($property))
            || !$this->tenancyBelongsTo($propertyTenantId, $propertyId)
        ) {
            flash('error', __('Select a valid pay period.'));
            redirect('/properties/' . $propertyId . '/rent');
        }

        RentLedger::exempt(
            $propertyTenantId,
            $periodStart,
            (int) Auth::instance()->id(),
            trim((string) ($_POST['reason'] ?? ''))
        );
        log_activity('rent.period_exempted', "Rent period {$periodStart} exempted for property #{$propertyId}");
        flash('success', __('Pay period marked as exempt.'));
        redirect('/properties/' . $propertyId . '/rent');
    }

    public function unexempt(int $propertyId): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
        Access::require(Access::property($propertyId));
        Access::require(RentLedger::canExempt(Auth::instance()->user()));

        $periodStart = trim((string) ($_POST['period_start'] ?? ''));
        $propertyTenantId = (int) ($_POST['property_tenant_id'] ?? 0);
        // Accept any real calendar date here: an exemption may predate a change
        // to the property's due day and must still be removable.
        $validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodStart) === 1
            && checkdate(
                (int) substr($periodStart, 5, 2),
                (int) substr($periodStart, 8, 2),
                (int) substr($periodStart, 0, 4)
            );
        if (!$validDate || !$this->tenancyBelongsTo($propertyTenantId, $propertyId)) {
            flash('error', __('Select a valid pay period.'));
            redirect('/properties/' . $propertyId . '/rent');
        }

        RentLedger::unexempt($propertyTenantId, $periodStart);
        log_activity('rent.period_unexempted', "Rent period {$periodStart} exemption removed for property #{$propertyId}");
        flash('success', __('Pay period exemption removed.'));
        redirect('/properties/' . $propertyId . '/rent');
    }

    private function tenancyBelongsTo(int $propertyTenantId, int $propertyId): bool
    {
        if ($propertyTenantId <= 0) {
            return false;
        }
        return Database::fetch(
            "SELECT 1 FROM property_tenant WHERE id = ? AND property_id = ?",
            [$propertyTenantId, $propertyId]
        ) !== null;
    }

    public function archive(int $paymentId): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
        $payment = Database::fetch("SELECT p.*, pt.property_id FROM payments p JOIN property_tenant pt ON pt.id = p.property_tenant_id WHERE p.id = ?", [$paymentId]);
        if (!$payment) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        Access::require(Access::payment($paymentId));

        Database::execute("UPDATE payments SET archived_at = NOW() WHERE id = ?", [$paymentId]);
        log_activity('payment.archived', "Payment #{$paymentId} archived");
        flash('success', __('Payment archived successfully.'));
        redirect('/properties/' . $payment['property_id'] . '/rent');
    }

    public function restore(int $paymentId): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
        $payment = Database::fetch("SELECT p.*, pt.property_id FROM payments p JOIN property_tenant pt ON pt.id = p.property_tenant_id WHERE p.id = ?", [$paymentId]);
        if (!$payment) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        Access::require(Access::payment($paymentId));

        Database::execute("UPDATE payments SET archived_at = NULL WHERE id = ?", [$paymentId]);
        log_activity('payment.restored', "Payment #{$paymentId} restored");
        flash('success', __('Payment restored successfully.'));
        redirect('/properties/' . $payment['property_id'] . '/rent');
    }

    public function destroy(int $paymentId): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
        $payment = Database::fetch("SELECT p.*, pt.property_id FROM payments p JOIN property_tenant pt ON pt.id = p.property_tenant_id WHERE p.id = ?", [$paymentId]);
        if (!$payment) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        Access::require(Access::payment($paymentId));

        Database::execute("DELETE FROM payments WHERE id = ?", [$paymentId]);
        log_activity('payment.deleted', "Payment #{$paymentId} permanently deleted");
        flash('success', __('Payment permanently deleted.'));
        redirect('/properties/' . $payment['property_id'] . '/rent');
    }
}
