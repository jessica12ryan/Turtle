<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Core\Validator;

class RentController
{
    public function index(): void
    {
        $auth = Auth::instance();
        $user = $auth->user();

        if ($user['role'] === 'admin') {
            $properties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name,
                 (SELECT COUNT(*) FROM property_tenant WHERE property_id = p.id AND moved_out_at IS NULL) as tenants_count
                 FROM properties p
                 JOIN users u ON u.id = p.landlord_id
                 WHERE p.archived_at IS NULL
                 ORDER BY p.name"
            );
        } else {
            $companyIds = Database::fetchAll(
                "SELECT company_id FROM company_user WHERE user_id = ?",
                [$auth->id()]
            );
            $companyIdList = implode(',', array_column($companyIds, 'company_id')) ?: '0';

            $pmClause = $user['role'] === 'property_manager' ? ' AND p.property_manager_id = ?' : '';
            $params = $pmClause ? [$auth->id()] : [];

            $properties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name,
                 (SELECT COUNT(*) FROM property_tenant WHERE property_id = p.id AND moved_out_at IS NULL) as tenants_count
                 FROM properties p
                 JOIN users u ON u.id = p.landlord_id
                 WHERE p.company_id IN ({$companyIdList}) AND p.archived_at IS NULL{$pmClause}
                 ORDER BY p.name",
                $params
            );
        }

        $currentMonth = date('Y-m');
        $rentData = [];
        foreach ($properties as $prop) {
            $totalRent = $prop['rent_amount'] ?? 0;
            if ($totalRent <= 0) continue;

            $paid = Database::fetch(
                "SELECT COALESCE(SUM(p.amount), 0) as total
                 FROM payments p
                 JOIN property_tenant pt ON pt.id = p.property_tenant_id
                 WHERE pt.property_id = ? AND p.payment_date LIKE ? AND p.archived_at IS NULL",
                [$prop['id'], $currentMonth . '%']
            );
            $paidAmount = (float)($paid['total'] ?? 0);

            $lastPayment = Database::fetch(
                "SELECT p.payment_date, p.amount
                 FROM payments p
                 JOIN property_tenant pt ON pt.id = p.property_tenant_id
                 WHERE pt.property_id = ? AND p.archived_at IS NULL
                 ORDER BY p.payment_date DESC LIMIT 1",
                [$prop['id']]
            );

            $prop['paid_amount'] = $paidAmount;
            $prop['rent_status'] = $paidAmount >= $totalRent ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid');
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
        if (!$property) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $tenants = Database::fetchAll(
            "SELECT u.*, pt.id as property_tenant_id, pt.is_main_tenant, pt.assigned_at
             FROM users u
             JOIN property_tenant pt ON pt.tenant_id = u.id
             WHERE pt.property_id = ? AND pt.moved_out_at IS NULL AND u.archived_at IS NULL",
            [$propertyId]
        );

        $payments = Database::fetchAll(
            "SELECT p.*, u.name as tenant_name, r.name as recorded_by_name
             FROM payments p
             JOIN property_tenant pt ON pt.id = p.property_tenant_id
             JOIN users u ON u.id = pt.tenant_id
             JOIN users r ON r.id = p.recorded_by
             WHERE pt.property_id = ? AND p.archived_at IS NULL
             ORDER BY p.payment_date DESC",
            [$propertyId]
        );

        $currentMonth = date('Y-m');
        $totalRent = $property['rent_amount'] ?? 0;
        $paidThisMonth = 0;
        foreach ($payments as $pym) {
            if (str_starts_with($pym['payment_date'], $currentMonth)) {
                $paidThisMonth += $pym['amount'];
            }
        }
        $rentStatus = $totalRent > 0
            ? ($paidThisMonth >= $totalRent ? 'paid' : ($paidThisMonth > 0 ? 'partial' : 'unpaid'))
            : 'not_set';

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Rent - ' . $property['name']]);
        $view->render('rent/show', compact('property', 'tenants', 'payments', 'rentStatus', 'paidThisMonth'));
    }

    public function store(int $propertyId): void
    {
        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'property_tenant_id' => 'required|exists:property_tenant,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'max:50',
            'reference' => 'max:100',
            'notes' => 'max:5000',
        ])) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/properties/' . $propertyId . '/rent');
        }

        Database::insert(
            "INSERT INTO payments (property_tenant_id, amount, payment_date, payment_method, reference, notes, recorded_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $_POST['property_tenant_id'],
                $_POST['amount'],
                $_POST['payment_date'],
                $_POST['payment_method'] ?: null,
                $_POST['reference'] ?: null,
                $_POST['notes'] ?: null,
                Auth::instance()->id(),
            ]
        );

        log_activity('payment.created', "Payment of \${$_POST['amount']} recorded for property #{$propertyId}");
        flash('success', 'Payment recorded successfully.');
        redirect('/properties/' . $propertyId . '/rent');
    }

    public function update(int $paymentId): void
    {
        $payment = Database::fetch("SELECT p.*, pt.property_id FROM payments p JOIN property_tenant pt ON pt.id = p.property_tenant_id WHERE p.id = ?", [$paymentId]);
        if (!$payment) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'max:50',
            'reference' => 'max:100',
            'notes' => 'max:5000',
        ])) {
            $_SESSION['_errors'] = $validator->errors();
            redirect('/properties/' . $payment['property_id'] . '/rent');
        }

        Database::execute(
            "UPDATE payments SET amount = ?, payment_date = ?, payment_method = ?, reference = ?, notes = ?, updated_at = NOW() WHERE id = ?",
            [$_POST['amount'], $_POST['payment_date'], $_POST['payment_method'] ?: null, $_POST['reference'] ?: null, $_POST['notes'] ?: null, $paymentId]
        );

        log_activity('payment.updated', "Payment #{$paymentId} updated");
        flash('success', 'Payment updated successfully.');
        redirect('/properties/' . $payment['property_id'] . '/rent');
    }

    public function archive(int $paymentId): void
    {
        $payment = Database::fetch("SELECT p.*, pt.property_id FROM payments p JOIN property_tenant pt ON pt.id = p.property_tenant_id WHERE p.id = ?", [$paymentId]);
        if (!$payment) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        Database::execute("UPDATE payments SET archived_at = NOW() WHERE id = ?", [$paymentId]);
        log_activity('payment.archived', "Payment #{$paymentId} archived");
        flash('success', 'Payment archived successfully.');
        redirect('/properties/' . $payment['property_id'] . '/rent');
    }

    public function restore(int $paymentId): void
    {
        $payment = Database::fetch("SELECT p.*, pt.property_id FROM payments p JOIN property_tenant pt ON pt.id = p.property_tenant_id WHERE p.id = ?", [$paymentId]);
        if (!$payment) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        Database::execute("UPDATE payments SET archived_at = NULL WHERE id = ?", [$paymentId]);
        log_activity('payment.restored', "Payment #{$paymentId} restored");
        flash('success', 'Payment restored successfully.');
        redirect('/properties/' . $payment['property_id'] . '/rent');
    }

    public function destroy(int $paymentId): void
    {
        $payment = Database::fetch("SELECT p.*, pt.property_id FROM payments p JOIN property_tenant pt ON pt.id = p.property_tenant_id WHERE p.id = ?", [$paymentId]);
        if (!$payment) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        Database::execute("DELETE FROM payments WHERE id = ?", [$paymentId]);
        log_activity('payment.deleted', "Payment #{$paymentId} permanently deleted");
        flash('success', 'Payment permanently deleted.');
        redirect('/properties/' . $payment['property_id'] . '/rent');
    }
}
