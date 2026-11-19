<?php

namespace App\Controllers;

use App\Core\Access;
use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Core\Validator;
use App\Core\Mailer;
use App\Core\Tenancy;

class TenantController
{
    private function getPropertyTenant(int $tenantId): ?array
    {
        return Database::fetch(
            "SELECT * FROM property_tenant WHERE tenant_id = ?",
            [$tenantId]
        );
    }

    /**
     * Reduce a phone number to digits (and the standard US display format)
     * before it is validated and stored. Doing this first means formatting,
     * country codes or pasted Unicode separators cannot trip the length rule
     * even though the stored value fits in the varchar(20) column.
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 10) {
            return '(' . substr($digits, 0, 3) . ') ' . substr($digits, 3, 3) . '-' . substr($digits, 6, 4);
        }
        return $digits;
    }

    private function archiveSecondaryTenants(int $propertyId, int $mainTenantId, string $batch): void
    {
        $secondaries = Database::fetchAll(
            "SELECT pt.id, pt.tenant_id FROM property_tenant pt
             WHERE pt.property_id = ? AND pt.tenant_id != ? AND pt.moved_out_at IS NULL",
            [$propertyId, $mainTenantId]
        );
        foreach ($secondaries as $s) {
            Database::execute("UPDATE users SET archived_at = NOW() WHERE id = ?", [$s['tenant_id']]);
            Database::execute(
                "UPDATE property_tenant SET moved_out_at = NOW(), archived_batch = ?, updated_at = NOW() WHERE tenant_id = ? AND moved_out_at IS NULL",
                [$batch, $s['tenant_id']]
            );
            Database::execute(
                "UPDATE leases SET archived_at = NOW(), archived_batch = ? WHERE tenant_id = ? AND archived_at IS NULL",
                [$batch, $s['tenant_id']]
            );
            Database::execute(
                "UPDATE payments SET archived_at = NOW(), archived_batch = ? WHERE property_tenant_id = ? AND archived_at IS NULL",
                [$batch, $s['id']]
            );
        }
    }

    private function restoreSecondaryTenants(int $propertyId, int $mainTenantId, string $batch): int
    {
        $secondaries = Database::fetchAll(
            "SELECT pt.id, pt.tenant_id FROM property_tenant pt
             WHERE pt.property_id = ? AND pt.tenant_id != ? AND pt.moved_out_at IS NOT NULL
               AND pt.archived_batch = ?",
            [$propertyId, $mainTenantId, $batch]
        );
        $restored = 0;
        foreach ($secondaries as $s) {
            $tenantId = (int)$s['tenant_id'];
            $elsewhere = Database::fetch(
                "SELECT 1 FROM property_tenant WHERE tenant_id = ? AND moved_out_at IS NULL AND property_id != ?",
                [$tenantId, $propertyId]
            );
            if ($elsewhere) {
                continue;
            }
            Database::execute(
                "UPDATE property_tenant SET moved_out_at = NULL, archived_batch = NULL, updated_at = NOW() WHERE id = ?",
                [$s['id']]
            );
            Database::execute(
                "UPDATE leases SET archived_at = NULL, archived_batch = NULL WHERE tenant_id = ? AND archived_batch = ?",
                [$tenantId, $batch]
            );
            Database::execute(
                "UPDATE payments SET archived_at = NULL, archived_batch = NULL WHERE property_tenant_id = ? AND archived_batch = ?",
                [$s['id'], $batch]
            );
            $active = Database::fetch(
                "SELECT 1 FROM property_tenant WHERE tenant_id = ? AND moved_out_at IS NULL",
                [$tenantId]
            );
            if ($active) {
                Database::execute("UPDATE users SET archived_at = NULL WHERE id = ?", [$tenantId]);
            }
            $restored++;
        }
        return $restored;
    }

    public function index(): void
    {
        $user = Auth::instance()->user();
        $showArchived = !empty($_GET['show_archived']);
        $archivedClause = $showArchived ? '' : ' AND u.archived_at IS NULL';
        $orderBy = $showArchived ? 'ORDER BY u.archived_at IS NULL ASC, u.name' : 'ORDER BY u.name';

        // The main tenant on the same property stays the primary for secondary
        // tenants even after they move out; prefer an active main if there is one.
        $primaryTenantSelect = "(SELECT u2.name FROM property_tenant pt2
                 JOIN users u2 ON u2.id = pt2.tenant_id
                 WHERE pt2.property_id = p.id AND pt2.is_main_tenant = 1
                 ORDER BY (pt2.moved_out_at IS NULL) DESC, pt2.id ASC LIMIT 1) AS primary_tenant_name";

        if ($user['role'] === 'admin') {
            $tenants = Database::fetchAll(
                "SELECT u.*, pt.is_main_tenant, pt.moved_out_at, pt.assigned_at,
                 p.name as property_name, p.id as property_id,
                 {$primaryTenantSelect}
                 FROM users u 
                 LEFT JOIN property_tenant pt ON pt.tenant_id = u.id 
                 LEFT JOIN properties p ON p.id = pt.property_id 
                 WHERE u.role = 'tenant'{$archivedClause}
                 {$orderBy}"
            );
        } else {
            $companyIds = Database::fetchAll(
                "SELECT company_id FROM company_user WHERE user_id = ?",
                [Auth::instance()->id()]
            );
            $companyIdList = implode(',', array_column($companyIds, 'company_id')) ?: '0';

            $pmClause = $user['role'] === 'property_manager' ? ' AND p.property_manager_id = ?' : '';
            $params = $pmClause ? [Auth::instance()->id()] : [];

            $tenants = Database::fetchAll(
                "SELECT u.*, pt.is_main_tenant, pt.moved_out_at, pt.assigned_at,
                 p.name as property_name, p.id as property_id,
                 {$primaryTenantSelect}
                 FROM users u 
                 LEFT JOIN property_tenant pt ON pt.tenant_id = u.id 
                 LEFT JOIN properties p ON p.id = pt.property_id 
                 WHERE u.role = 'tenant' AND p.company_id IN ({$companyIdList}){$archivedClause}{$pmClause}
                 {$orderBy}",
                $params
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

            $pmClause = $user['role'] === 'property_manager' ? ' AND p.property_manager_id = ?' : '';
            $params = $pmClause ? [$user['id']] : [];

            $properties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name FROM properties p 
                 JOIN users u ON u.id = p.landlord_id 
                 WHERE p.company_id IN ({$companyIdList}) AND p.archived_at IS NULL{$pmClause}
                 ORDER BY p.name",
                $params
            );
        }

        // Fetch the *current* main tenant per property. This drives both the
        // secondary-tenant auto-fill and the form's rule that a property with
        // a main tenant cannot receive another one. Archived/moved-out main
        // tenants must not count, or their property can never be re-let.
        $mainTenants = [];
        foreach ($properties as $p) {
            $mt = Database::fetch(
                "SELECT u.name, pt.lease_start, pt.lease_end, pt.move_out_date
                 FROM property_tenant pt 
                 JOIN users u ON u.id = pt.tenant_id 
                 WHERE pt.property_id = ? AND pt.is_main_tenant = 1
                   AND " . Tenancy::activeLinkCondition('pt', 'u') . "
                 ORDER BY pt.id ASC
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
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirect('/tenants/create');
        }

        $archived = Database::fetch("SELECT id FROM users WHERE email = ? AND archived_at IS NOT NULL", [$_POST['email']]);
        if ($archived) {
            flash('error', __('Email exists in archived tenant.'));
            $_SESSION['_old'] = $_POST;
            redirect('/tenants/create');
        }

        $existingMain = Database::fetch(
            "SELECT pt.id, pt.lease_start, pt.lease_end, p.name as property_name FROM property_tenant pt 
             JOIN properties p ON p.id = pt.property_id
             JOIN users u ON u.id = pt.tenant_id
             WHERE pt.property_id = ? AND pt.is_main_tenant = 1
               AND " . Tenancy::activeLinkCondition('pt', 'u'),
            [$_POST['property_id']]
        );

        // Normalize before validation so the rule sees what will be stored.
        $_POST['phone'] = $this->normalizePhone((string)($_POST['phone'] ?? ''));

        $isMainRequest = !empty($_POST['is_main_tenant']) || !$existingMain;
        $rules = [
            'name' => 'required|max:255',
            'email' => 'required|email|unique:users,email',
            'property_id' => 'required|exists:properties,id',
            'phone' => 'required|max:20',
        ];
        if ($isMainRequest) {
            $rules['lease_start'] = 'required';
            $rules['lease_type'] = 'required';
        }

        $validator = new Validator();
        if (!$validator->validate($_POST, $rules)) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/tenants/create');
        }

        // A valid permission is not enough: the property must be one this user
        // actually manages. This also avoids creating an orphan tenant account
        // when a main tenant is requested on an already-occupied property.
        Access::require(Access::property((int)$_POST['property_id']));

        if ($existingMain && !empty($_POST['is_main_tenant'])) {
            flash('error', __('Main tenant already exists for :property. Uncheck "Main tenant" or choose a different property.', [':property' => h($existingMain['property_name'])]));
            $_SESSION['_old'] = $_POST;
            redirect('/tenants/create');
        }

        $phone = $_POST['phone'];

        $password = bin2hex(random_bytes(6));
        $timezone = $_POST['timezone'] ?: null;
        $language = $_POST['language'] ?: null;

        try {
            Database::beginTransaction();
            $tenantId = Database::insert(
                "INSERT INTO users (name, email, phone, password, role, theme, timezone, language, must_change_password, created_at, updated_at) VALUES (?, ?, ?, ?, 'tenant', 'system', ?, ?, 1, NOW(), NOW())",
                [$_POST['name'], $_POST['email'], $phone, password_hash($password, PASSWORD_DEFAULT), $timezone, $language]
            );

            $isMain = !$existingMain ? 1 : 0;

            // Secondary tenants do not store their own dates — always fetched from main tenant
            if ($isMain) {
                $leaseStart = $_POST['lease_start'] ?? null;
                $leaseEnd = $_POST['lease_end'] ?: null;
                $moveOutDate = $_POST['move_out_date'] ?: null;
                $leaseType = $_POST['lease_type'] ?: null;
                $emergencyName = $_POST['emergency_contact_name'] ?: null;
                $emergencyPhone = $_POST['emergency_contact_phone'] ?: null;
            } else {
                $leaseStart = null;
                $leaseEnd = null;
                $moveOutDate = null;
                $leaseType = null;
                $emergencyName = null;
                $emergencyPhone = null;
            }

            Database::insert(
                "INSERT INTO property_tenant (property_id, tenant_id, is_main_tenant, assigned_at, lease_start, lease_end, move_out_date, lease_type, emergency_contact_name, emergency_contact_phone, created_at, updated_at) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                [$_POST['property_id'], $tenantId, $isMain, $leaseStart, $leaseEnd, $moveOutDate, $leaseType, $emergencyName, $emergencyPhone]
            );
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            \App\Core\Logger::error('Tenant creation failed: ' . $e->getMessage());
            flash('error', __('Could not create tenant. Please try again.'));
            redirect('/tenants/create');
        }

        if (!empty($_POST['send_welcome_email'])) {
            $loginUrl = \site_url() . '/login';
            Mailer::sendTemplate(
                $_POST['email'],
                'Welcome to Turtle - Your Account Has Been Created',
                'Hello ' . h($_POST['name']) . ',',
                'Your account has been created on the Turtle Tenant Management Portal.<br><br><strong>Your temporary password is: ' . $password . '</strong><br><br>Please log in and change your password immediately.',
                $loginUrl,
                'Log In'
            );
            notifyInApp((int)$tenantId, 'tenant_welcome', 'Your Turtle tenant account has been created.');
        }

        log_activity('tenant.created', "Tenant '{$_POST['name']}' added");
        flash('success', __('Tenant added successfully.'));
        redirect('/tenants');
    }

    public function show(int $id): void
    {
        $tenant = Database::fetch(
            "SELECT u.*, pt.is_main_tenant, pt.moved_out_at, pt.assigned_at,
             pt.emergency_contact_name, pt.emergency_contact_phone,
             p.name as property_name, p.id as property_id 
             FROM users u 
             JOIN property_tenant pt ON pt.tenant_id = u.id 
             JOIN properties p ON p.id = pt.property_id 
             WHERE u.id = ? AND u.role = 'tenant'",
            [$id]
        );
        if (!$tenant) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        Access::require(Access::tenant($id));

        $tickets = Database::fetchAll(
            "SELECT t.*, p.name as property_name FROM tickets t 
             JOIN properties p ON p.id = t.property_id 
             WHERE t.tenant_id = ? AND t.archived_at IS NULL 
             ORDER BY t.created_at DESC LIMIT 10",
            [$id]
        );

        // Fetch main tenant lease dates if this tenant is not the main tenant
        $mainTenant = null;
        if (empty($tenant['is_main_tenant']) && !empty($tenant['property_id'])) {
            $mainTenant = Database::fetch(
                "SELECT pt.lease_start, pt.lease_end, pt.move_out_date
                 FROM property_tenant pt
                 WHERE pt.property_id = ? AND pt.is_main_tenant = 1
                 ORDER BY (pt.moved_out_at IS NULL) DESC, pt.id ASC
                 LIMIT 1",
                [$tenant['property_id']]
            );
        }

        $view = new View();
        $view->layout('layouts/main', ['title' => $tenant['name']]);
        $view->render('tenants/show', compact('tenant', 'tickets', 'mainTenant'));
    }

    public function edit(int $id): void
    {
        $tenant = Database::fetch("SELECT * FROM users WHERE id = ? AND role = 'tenant'", [$id]);
        if (!$tenant) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        Access::require(Access::tenant($id));

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

            $pmClause = $user['role'] === 'property_manager' ? ' AND p.property_manager_id = ?' : '';
            $params = $pmClause ? [$user['id']] : [];

            $properties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name FROM properties p 
                 JOIN users u ON u.id = p.landlord_id 
                 WHERE p.company_id IN ({$companyIdList}) AND p.archived_at IS NULL{$pmClause}
                 ORDER BY p.name",
                $params
            );
        }

        $tenant['is_main_tenant'] = $pt['is_main_tenant'] ?? 0;
        $tenant['lease_start'] = $pt['lease_start'] ?? '';
        $tenant['lease_end'] = $pt['lease_end'] ?? '';
        $tenant['move_out_date'] = $pt['move_out_date'] ?? '';
        $tenant['lease_type'] = $pt['lease_type'] ?? '';
        $tenant['emergency_contact_name'] = $pt['emergency_contact_name'] ?? '';
        $tenant['emergency_contact_phone'] = $pt['emergency_contact_phone'] ?? '';
        $tenant['property_id'] = $pt['property_id'] ?? '';

        // For secondary tenants, fetch main tenant dates for display
        $mainTenant = null;
        if (empty($tenant['is_main_tenant']) && !empty($tenant['property_id'])) {
            $mainTenant = Database::fetch(
                "SELECT pt.lease_start, pt.lease_end, pt.move_out_date
                 FROM property_tenant pt
                 WHERE pt.property_id = ? AND pt.is_main_tenant = 1
                 ORDER BY (pt.moved_out_at IS NULL) DESC, pt.id ASC
                 LIMIT 1",
                [$tenant['property_id']]
            );
        }

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Edit Tenant']);
        $view->render('tenants/edit', compact('tenant', 'properties', 'mainTenant'));
    }

    public function update(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirect('/tenants/' . $id . '/edit');
        }

        Access::require(Access::tenant($id));

        $currentUser = Auth::instance()->user();
        $canEditEmail = in_array($currentUser['role'] ?? '', ['admin', 'landlord']);

        // Normalize before validation so the rule sees what will be stored.
        $_POST['phone'] = $this->normalizePhone((string)($_POST['phone'] ?? ''));

        $validator = new Validator();
        $rules = ['name' => 'required|max:255'];
        if ($canEditEmail) {
            $rules['email'] = 'required|email|unique:users,email,' . $id;
        }
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

        $phone = $_POST['phone'];

        $timezone = $_POST['timezone'] ?: null;
        $language = $_POST['language'] ?: null;

        $sql = "UPDATE users SET name = ?, phone = ?, timezone = ?, language = ?, updated_at = NOW()";
        $params = [$_POST['name'], $phone, $timezone, $language];

        if ($canEditEmail) {
            $sql .= ", email = ?";
            $params[] = $_POST['email'];
        }

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

        if ($id === Auth::instance()->id()) {
            if ($language) {
                $_SESSION['_language'] = $language;
            } else {
                unset($_SESSION['_language']);
            }
        }

        $pt = $this->getPropertyTenant($id);
        if ($pt && !empty($pt['is_main_tenant'])) {
            $leaseStart = $_POST['lease_start'] ?? null;
            $leaseEnd = $_POST['lease_end'] ?: null;
            $moveOutDate = $_POST['move_out_date'] ?: null;
            $leaseType = $_POST['lease_type'] ?? null;
            $emergencyName = $_POST['emergency_contact_name'] ?: null;
            $emergencyPhone = $_POST['emergency_contact_phone'] ?: null;

            Database::execute(
                "UPDATE property_tenant SET lease_start = ?, lease_end = ?, move_out_date = ?, lease_type = ?, emergency_contact_name = ?, emergency_contact_phone = ?, updated_at = NOW() WHERE tenant_id = ?",
                [$leaseStart, $leaseEnd, $moveOutDate, $leaseType, $emergencyName, $emergencyPhone, $id]
            );
        }

        log_activity('tenant.updated', "Tenant '{$_POST['name']}' updated");
        flash('success', __('Tenant updated successfully.'));
        redirect('/tenants/' . $id);
    }

    public function restore(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
        Access::require(Access::tenant($id));

        $links = Database::fetchAll(
            "SELECT * FROM property_tenant WHERE tenant_id = ? AND moved_out_at IS NOT NULL",
            [$id]
        );
        $restored = 0;
        $skipped = 0;
        $batches = [];
        $mainProperties = [];

        foreach ($links as $link) {
            $batch = $link['archived_batch'] ?? null;
            // Without a batch marker this link may have ended years ago; it is
            // not safe to treat it as part of this restore.
            if (!$batch) {
                $skipped++;
                continue;
            }
            $elsewhere = Database::fetch(
                "SELECT 1 FROM property_tenant WHERE tenant_id = ? AND moved_out_at IS NULL AND property_id != ?",
                [$id, $link['property_id']]
            );
            if ($elsewhere) {
                $skipped++;
                continue;
            }

            Database::execute(
                "UPDATE property_tenant SET moved_out_at = NULL, archived_batch = NULL, updated_at = NOW() WHERE id = ?",
                [$link['id']]
            );
            Database::execute(
                "UPDATE leases SET archived_at = NULL, archived_batch = NULL WHERE tenant_id = ? AND archived_batch = ?",
                [$id, $batch]
            );
            Database::execute(
                "UPDATE payments SET archived_at = NULL, archived_batch = NULL WHERE property_tenant_id = ? AND archived_batch = ?",
                [$link['id'], $batch]
            );

            $batches[] = $batch;
            if (!empty($link['is_main_tenant'])) {
                $mainProperties[] = (int)$link['property_id'];
            }
            $restored++;
        }

        // Restore co-tenants only from the same archive operation.
        foreach ($mainProperties as $propertyId) {
            foreach ($batches as $batch) {
                $restored += $this->restoreSecondaryTenants($propertyId, $id, $batch);
            }
        }

        // Only re-enable the account if at least one tenancy is actually
        // current again — archived_at is the global login-eligibility flag.
        $active = Database::fetch(
            "SELECT 1 FROM property_tenant WHERE tenant_id = ? AND moved_out_at IS NULL",
            [$id]
        );
        if ($active) {
            Database::execute("UPDATE users SET archived_at = NULL WHERE id = ? AND role = 'tenant'", [$id]);
        }

        log_activity('tenant.restored', "Tenant #{$id} restored");
        $message = $restored > 0
            ? __('Tenant restored successfully.')
            : __('No current tenancy could be restored for this tenant.');
        if ($skipped > 0) {
            $message .= ' ' . __('Some historical tenancies were left archived because they predate batch tracking or the tenant is assigned elsewhere.');
        }
        flash($restored > 0 ? 'success' : 'error', $message);
        redirect('/tenants');
    }

    public function moveOut(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
        Access::require(Access::tenant($id));

        $pt = Database::fetch(
            "SELECT * FROM property_tenant WHERE tenant_id = ? AND moved_out_at IS NULL",
            [$id]
        );

        if ($pt) {
            // One batch id marks everything archived by this move-out.
            $batch = bin2hex(random_bytes(16));

            // Archive the tenant
            Database::execute(
                "UPDATE property_tenant SET moved_out_at = NOW(), archived_batch = ?, updated_at = NOW() WHERE tenant_id = ? AND moved_out_at IS NULL",
                [$batch, $id]
            );
            Database::execute(
                "UPDATE users SET archived_at = NOW() WHERE id = ?",
                [$id]
            );
            Database::execute(
                "UPDATE leases SET archived_at = NOW(), archived_batch = ? WHERE tenant_id = ? AND archived_at IS NULL",
                [$batch, $id]
            );
            Database::execute(
                "UPDATE payments SET archived_at = NOW(), archived_batch = ? WHERE property_tenant_id = ? AND archived_at IS NULL",
                [$batch, $pt['id']]
            );

            // Cascade archive to secondary tenants if this is a main tenant
            if (!empty($pt['is_main_tenant'])) {
                $this->archiveSecondaryTenants($pt['property_id'], $id, $batch);
            }
        }

        log_activity('tenant.archived', "Tenant #{$id} archived");
        flash('success', __('Tenant archived successfully.'));
        redirect('/tenants');
    }

    public function destroy(int $id): void
    {
        try {
            if (!verify_csrf($_POST['_csrf'] ?? '')) {
                flash('error', __('Invalid form token. Please try again.'));
                redirect('/tenants');
            }

            $target = Database::fetch("SELECT * FROM users WHERE id = ? AND role = 'tenant'", [$id]);
            if (!$target) {
                http_response_code(404);
                require base_path('www/Views/errors/404.php');
                return;
            }
            Access::require(Access::tenant($id));

            // Several tables reference users(id) without ON DELETE CASCADE
            // (activity_logs, documents, leases.uploaded_by, notifications,
            // etc.). A surviving admin must own those records, otherwise the
            // DELETE fails with a foreign key constraint error.
            $replacement = Database::fetch(
                "SELECT id FROM users WHERE role = 'admin' AND archived_at IS NULL AND id != ? ORDER BY id LIMIT 1",
                [$id]
            );
            if (!$replacement) {
                flash('error', __('Cannot permanently delete this tenant because no active admin is available to retain their records.'));
                redirect('/tenants');
            }
            $replacementId = (int)$replacement['id'];

            Database::beginTransaction();

            $deleteUser = function (int $userId) use ($replacementId): void {
                // Nullable references.
                Database::execute("UPDATE properties SET property_manager_id = NULL WHERE property_manager_id = ?", [$userId]);
                Database::execute("UPDATE tickets SET assigned_to = NULL WHERE assigned_to = ?", [$userId]);
                Database::execute("UPDATE leases SET tenant_id = NULL WHERE tenant_id = ?", [$userId]);

                // Required references are reassigned to a surviving admin.
                Database::execute("UPDATE properties SET landlord_id = ? WHERE landlord_id = ?", [$replacementId, $userId]);
                Database::execute("UPDATE payments SET recorded_by = ? WHERE recorded_by = ?", [$replacementId, $userId]);
                Database::execute("UPDATE leases SET uploaded_by = ? WHERE uploaded_by = ?", [$replacementId, $userId]);
                Database::execute("UPDATE ticket_comments SET user_id = ? WHERE user_id = ?", [$replacementId, $userId]);
                Database::execute("UPDATE ticket_files SET uploaded_by = ? WHERE uploaded_by = ?", [$replacementId, $userId]);
                Database::execute("UPDATE documents SET uploaded_by = ? WHERE uploaded_by = ?", [$replacementId, $userId]);
                Database::execute("UPDATE resources SET created_by = ? WHERE created_by = ?", [$replacementId, $userId]);
                Database::execute("UPDATE activity_logs SET user_id = ? WHERE user_id = ?", [$replacementId, $userId]);

                // Records that belong to the tenant itself.
                Database::execute("DELETE FROM tickets WHERE tenant_id = ?", [$userId]);
                Database::execute("DELETE FROM notifications WHERE user_id = ?", [$userId]);
                Database::execute("DELETE FROM sessions WHERE user_id = ?", [$userId]);
                Database::execute("DELETE FROM property_tenant WHERE tenant_id = ?", [$userId]);

                $userRow = Database::fetch("SELECT email FROM users WHERE id = ?", [$userId]);
                if ($userRow) {
                    Database::execute("DELETE FROM password_reset_tokens WHERE email = ?", [$userRow['email']]);
                }

                // messages, company_user and user_presence cascade on their own.
                Database::execute("DELETE FROM users WHERE id = ?", [$userId]);
            };

            // Only delete the requested tenant. Other tenants assigned to the
            // same property are separate accounts and must not be removed —
            // doing so silently deletes archived/co-tenants along with them.
            $deleteUser($id);

            Database::commit();

            log_activity('tenant.deleted', "Tenant '{$target['name']}' permanently deleted");
            flash('success', __('Tenant permanently deleted.'));
            redirect('/tenants');
        } catch (\Throwable $e) {
            Database::rollback();
            \App\Core\Logger::error('Tenant deletion failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            flash('error', __('Failed to delete tenant. Please try again.'));
            redirect('/tenants');
        }
    }
}
