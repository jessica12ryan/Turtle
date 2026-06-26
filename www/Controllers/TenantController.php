<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Core\Validator;
use App\Core\Mailer;

class TenantController
{
    private function getPropertyTenant(int $tenantId): ?array
    {
        return Database::fetch(
            "SELECT * FROM property_tenant WHERE tenant_id = ?", [$tenantId]
        );
    }

    private function archiveSecondaryTenants(int $propertyId, int $mainTenantId): void
    {
        $secondaries = Database::fetchAll(
            "SELECT tenant_id FROM property_tenant 
             WHERE property_id = ? AND tenant_id != ? AND moved_out_at IS NULL",
            [$propertyId, $mainTenantId]
        );
        foreach ($secondaries as $s) {
            Database::execute("UPDATE users SET archived_at = NOW() WHERE id = ?", [$s['tenant_id']]);
            Database::execute(
                "UPDATE property_tenant SET moved_out_at = NOW(), updated_at = NOW() WHERE tenant_id = ? AND moved_out_at IS NULL",
                [$s['tenant_id']]
            );
            Database::execute(
                "UPDATE leases SET archived_at = NOW() WHERE tenant_id = ? AND archived_at IS NULL",
                [$s['tenant_id']]
            );
        }
    }

    private function restoreSecondaryTenants(int $propertyId, int $mainTenantId): void
    {
        $secondaries = Database::fetchAll(
            "SELECT tenant_id FROM property_tenant 
             WHERE property_id = ? AND tenant_id != ? AND moved_out_at IS NOT NULL",
            [$propertyId, $mainTenantId]
        );
        foreach ($secondaries as $s) {
            Database::execute("UPDATE users SET archived_at = NULL WHERE id = ?", [$s['tenant_id']]);
            Database::execute(
                "UPDATE property_tenant SET moved_out_at = NULL, updated_at = NOW() WHERE tenant_id = ? AND moved_out_at IS NOT NULL",
                [$s['tenant_id']]
            );
            Database::execute(
                "UPDATE leases SET archived_at = NULL WHERE tenant_id = ? AND archived_at IS NOT NULL",
                [$s['tenant_id']]
            );
        }
    }

    public function index(): void
    {
        $user = Auth::instance()->user();
        $showArchived = !empty($_GET['show_archived']);
        $archivedClause = $showArchived ? '' : ' AND u.archived_at IS NULL';
        $orderBy = $showArchived ? 'ORDER BY u.archived_at IS NULL DESC, u.name' : 'ORDER BY u.name';

        if ($user['role'] === 'admin') {
            $tenants = Database::fetchAll(
                "SELECT u.*, pt.is_main_tenant, pt.moved_out_at, pt.assigned_at,
                 p.name as property_name, p.id as property_id 
                 FROM users u 
                 JOIN property_tenant pt ON pt.tenant_id = u.id 
                 JOIN properties p ON p.id = pt.property_id 
                 WHERE 1=1{$archivedClause}
                 {$orderBy}"
            );
        } else {
            $companyIds = Database::fetchAll(
                "SELECT company_id FROM company_user WHERE user_id = ?",
                [Auth::instance()->id()]
            );
            $companyIdList = implode(',', array_column($companyIds, 'company_id')) ?: '0';

            $tenants = Database::fetchAll(
                "SELECT u.*, pt.is_main_tenant, pt.moved_out_at, pt.assigned_at,
                 p.name as property_name, p.id as property_id 
                 FROM users u 
                 JOIN property_tenant pt ON pt.tenant_id = u.id 
                 JOIN properties p ON p.id = pt.property_id 
                 WHERE p.company_id IN ({$companyIdList}){$archivedClause}
                 {$orderBy}"
            );
        }

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Tenants']);
        $view->render('tenants/index', compact('tenants', 'showArchived'));
    }

    public function create(): void
    {
        $user = Auth::instance()->user();
        if ($user['role'] === 'admin') {
            $properties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name FROM properties p 
                 JOIN users u ON u.id = p.landlord_id 
                 WHERE p.archived_at IS NULL 
                 ORDER BY p.name"
            );
        } else {
            $companyIds = Database::fetchAll(
                "SELECT company_id FROM company_user WHERE user_id = ?",
                [$user['id']]
            );
            $companyIdList = implode(',', array_column($companyIds, 'company_id')) ?: '0';

            $properties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name FROM properties p 
                 JOIN users u ON u.id = p.landlord_id 
                 WHERE p.company_id IN ({$companyIdList}) AND p.archived_at IS NULL 
                 ORDER BY p.name"
            );
        }

        // Fetch main tenant info per property for auto-fill
        $mainTenants = [];
        foreach ($properties as $p) {
            $mt = Database::fetch(
                "SELECT u.name, pt.lease_start, pt.lease_end, pt.move_out_date
                 FROM property_tenant pt 
                 JOIN users u ON u.id = pt.tenant_id 
                 WHERE pt.property_id = ? AND pt.is_main_tenant = 1 AND pt.moved_out_at IS NULL 
                 LIMIT 1",
                [$p['id']]
            );
            if ($mt) {
                $mainTenants[$p['id']] = $mt;
            }
        }

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Invite Tenant']);
        $view->render('tenants/create', compact('properties', 'mainTenants'));
    }

    public function store(): void
    {
        $archived = Database::fetch("SELECT id FROM users WHERE email = ? AND archived_at IS NOT NULL", [$_POST['email']]);
        if ($archived) {
            flash('error', 'Email exists in archived tenant.');
            $_SESSION['_old'] = $_POST;
            redirect('/tenants/create');
        }

        $existingMain = Database::fetch(
            "SELECT pt.id, pt.lease_start, pt.lease_end, p.name as property_name FROM property_tenant pt 
             JOIN properties p ON p.id = pt.property_id
             WHERE pt.property_id = ? AND pt.is_main_tenant = 1 AND pt.moved_out_at IS NULL",
            [$_POST['property_id']]
        );

        $isMainRequest = !empty($_POST['is_main_tenant']) || !$existingMain;
        $rules = [
            'name' => 'required|max:255',
            'email' => 'required|email|unique:users,email',
            'property_id' => 'required|exists:properties,id',
            'phone' => 'required|max:20',
        ];
        if ($isMainRequest) {
            $rules['lease_start'] = 'required';
        }

        $validator = new Validator();
        if (!$validator->validate($_POST, $rules)) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/tenants/create');
        }

        $phone = $_POST['phone'] ?? '';
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 10) {
            $phone = '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6, 4);
        }

        $password = bin2hex(random_bytes(6));
        $timezone = $_POST['timezone'] ?: null;

        $tenantId = Database::insert(
            "INSERT INTO users (name, email, phone, password, role, timezone, must_change_password, created_at, updated_at) VALUES (?, ?, ?, ?, 'tenant', ?, 1, NOW(), NOW())",
            [$_POST['name'], $_POST['email'], $phone, password_hash($password, PASSWORD_DEFAULT), $timezone]
        );

        if ($existingMain && !empty($_POST['is_main_tenant'])) {
            Database::execute("DELETE FROM users WHERE id = ?", [$tenantId]);
            flash('error', 'Main tenant already exists for ' . h($existingMain['property_name']) . '. Uncheck "Main tenant" or choose a different property.');
            $_SESSION['_old'] = $_POST;
            redirect('/tenants/create');
        }

        $isMain = !$existingMain ? 1 : 0;

        // Secondary tenants inherit lease dates from the main tenant
        if (!$isMain && $existingMain) {
            $leaseStart = $existingMain['lease_start'];
            $leaseEnd = $existingMain['lease_end'];
        } else {
            $leaseStart = $_POST['lease_start'] ?? null;
            $leaseEnd = $_POST['lease_end'] ?: null;
        }

        $moveOutDate = $_POST['move_out_date'] ?: null;

        Database::insert(
            "INSERT INTO property_tenant (property_id, tenant_id, is_main_tenant, assigned_at, lease_start, lease_end, move_out_date, created_at, updated_at) VALUES (?, ?, ?, NOW(), ?, ?, ?, NOW(), NOW())",
            [$_POST['property_id'], $tenantId, $isMain, $leaseStart, $leaseEnd, $moveOutDate]
        );

        if (!empty($_POST['send_welcome_email'])) {
            $loginUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/login';
            Mailer::sendTemplate(
                $_POST['email'],
                'Welcome to Turtle - Your Account Has Been Created',
                'Hello ' . h($_POST['name']) . ',',
                'Your account has been created on the Turtle Tenant Management Portal.<br><br><strong>Your temporary password is: ' . $password . '</strong><br><br>Please log in and change your password immediately.',
                $loginUrl,
                'Log In'
            );
        }

        flash('success', 'Tenant added successfully.');
        redirect('/tenants');
    }

    public function show(int $id): void
    {
        $tenant = Database::fetch(
            "SELECT u.*, pt.is_main_tenant, pt.moved_out_at, pt.assigned_at,
             p.name as property_name, p.id as property_id 
             FROM users u 
             JOIN property_tenant pt ON pt.tenant_id = u.id 
             JOIN properties p ON p.id = pt.property_id 
             WHERE u.id = ? AND u.role = 'tenant'",
            [$id]
        );
        if (!$tenant) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $tickets = Database::fetchAll(
            "SELECT t.*, p.name as property_name FROM tickets t 
             JOIN properties p ON p.id = t.property_id 
             WHERE t.tenant_id = ? AND t.archived_at IS NULL 
             ORDER BY t.created_at DESC LIMIT 10",
            [$id]
        );

        $view = new View();
        $view->layout('layouts/main', ['title' => $tenant['name']]);
        $view->render('tenants/show', compact('tenant', 'tickets'));
    }

    public function edit(int $id): void
    {
        $tenant = Database::fetch("SELECT * FROM users WHERE id = ? AND role = 'tenant'", [$id]);
        if (!$tenant) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $pt = $this->getPropertyTenant($id);

        $user = Auth::instance()->user();
        if ($user['role'] === 'admin') {
            $properties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name FROM properties p 
                 JOIN users u ON u.id = p.landlord_id 
                 WHERE p.archived_at IS NULL 
                 ORDER BY p.name"
            );
        } else {
            $companyIds = Database::fetchAll(
                "SELECT company_id FROM company_user WHERE user_id = ?",
                [$user['id']]
            );
            $companyIdList = implode(',', array_column($companyIds, 'company_id')) ?: '0';

            $properties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name FROM properties p 
                 JOIN users u ON u.id = p.landlord_id 
                 WHERE p.company_id IN ({$companyIdList}) AND p.archived_at IS NULL 
                 ORDER BY p.name"
            );
        }

        $tenant['is_main_tenant'] = $pt['is_main_tenant'] ?? 0;
        $tenant['lease_start'] = $pt['lease_start'] ?? '';
        $tenant['lease_end'] = $pt['lease_end'] ?? '';
        $tenant['move_out_date'] = $pt['move_out_date'] ?? '';
        $tenant['property_id'] = $pt['property_id'] ?? '';

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Edit Tenant']);
        $view->render('tenants/edit', compact('tenant', 'properties'));
    }

    public function update(int $id): void
    {
        $validator = new Validator();
        $rules = ['name' => 'required|max:255'];
        if (!empty($_POST['phone'])) {
            $rules['phone'] = 'max:20';
        }
        if (!empty($_POST['password'])) {
            $rules['password'] = 'min:8|confirmed';
        }
        if (!$validator->validate($_POST, $rules)) {
            $_SESSION['_errors'] = $validator->errors();
            redirect('/tenants/' . $id . '/edit');
        }

        $phone = $_POST['phone'] ?? '';
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 10) {
            $phone = '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6, 4);
        }

        $timezone = $_POST['timezone'] ?: null;

        $sql = "UPDATE users SET name = ?, phone = ?, timezone = ?, updated_at = NOW()";
        $params = [$_POST['name'], $phone, $timezone];

        if (!empty($_POST['password'])) {
            $sql .= ", password = ?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $mustChange = !empty($_POST['must_change_password']) ? 1 : 0;
        $sql .= ", must_change_password = ?";
        $params[] = $mustChange;

        $sql .= " WHERE id = ?";
        $params[] = $id;
        Database::execute($sql, $params);

        $pt = $this->getPropertyTenant($id);
        if ($pt && !empty($pt['is_main_tenant'])) {
            $leaseStart = $_POST['lease_start'] ?? null;
            $leaseEnd = $_POST['lease_end'] ?: null;
            $moveOutDate = $_POST['move_out_date'] ?: null;

            Database::execute(
                "UPDATE property_tenant SET lease_start = ?, lease_end = ?, move_out_date = ?, updated_at = NOW() WHERE tenant_id = ?",
                [$leaseStart, $leaseEnd, $moveOutDate, $id]
            );
        }

        flash('success', 'Tenant updated successfully.');
        redirect('/tenants/' . $id);
    }

    public function restore(int $id): void
    {
        Database::execute("UPDATE users SET archived_at = NULL WHERE id = ? AND role = 'tenant'", [$id]);
        Database::execute("UPDATE property_tenant SET moved_out_at = NULL WHERE tenant_id = ? AND moved_out_at IS NOT NULL", [$id]);

        // Cascade restore to secondary tenants if this is a main tenant
        $pt = Database::fetch(
            "SELECT * FROM property_tenant WHERE tenant_id = ? AND is_main_tenant = 1",
            [$id]
        );
        if ($pt) {
            $this->restoreSecondaryTenants($pt['property_id'], $id);
        }

        flash('success', 'Tenant restored successfully.');
        redirect('/tenants');
    }

    public function moveOut(int $id): void
    {
        $pt = Database::fetch(
            "SELECT * FROM property_tenant WHERE tenant_id = ? AND moved_out_at IS NULL",
            [$id]
        );

        if ($pt) {
            // Archive the tenant
            Database::execute(
                "UPDATE property_tenant SET moved_out_at = NOW(), updated_at = NOW() WHERE tenant_id = ? AND moved_out_at IS NULL",
                [$id]
            );
            Database::execute(
                "UPDATE users SET archived_at = NOW() WHERE id = ?",
                [$id]
            );
            Database::execute(
                "UPDATE leases SET archived_at = NOW() WHERE tenant_id = ? AND archived_at IS NULL",
                [$id]
            );

            // Cascade archive to secondary tenants if this is a main tenant
            if (!empty($pt['is_main_tenant'])) {
                $this->archiveSecondaryTenants($pt['property_id'], $id);
            }
        }

        flash('success', 'Tenant archived successfully.');
        redirect('/tenants');
    }

    public function destroy(int $id): void
    {
        $target = Database::fetch("SELECT * FROM users WHERE id = ? AND role = 'tenant'", [$id]);
        if (!$target) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $pt = Database::fetch(
            "SELECT * FROM property_tenant WHERE tenant_id = ? AND is_main_tenant = 1",
            [$id]
        );

        if ($pt) {
            // Also delete secondary tenants
            $secondaries = Database::fetchAll(
                "SELECT tenant_id FROM property_tenant WHERE property_id = ? AND tenant_id != ?",
                [$pt['property_id'], $id]
            );
            foreach ($secondaries as $s) {
                Database::execute("DELETE FROM property_tenant WHERE tenant_id = ?", [$s['tenant_id']]);
                Database::execute("DELETE FROM users WHERE id = ?", [$s['tenant_id']]);
            }
        }

        Database::execute("DELETE FROM property_tenant WHERE tenant_id = ?", [$id]);
        Database::execute("DELETE FROM users WHERE id = ?", [$id]);

        flash('success', 'Tenant permanently deleted.');
        redirect('/tenants');
    }
}
