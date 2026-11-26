<?php

namespace App\Controllers;

use App\Core\Access;
use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Core\Validator;
use App\Core\Tenancy;

class PropertyController
{
    private function ensureLandlordCompany(int $landlordId): int
    {
        $existing = Database::fetch(
            "SELECT cu.company_id FROM company_user cu WHERE cu.user_id = ? LIMIT 1",
            [$landlordId]
        );
        if ($existing) {
            return (int)$existing['company_id'];
        }
        $landlord = Database::fetch("SELECT name FROM users WHERE id = ?", [$landlordId]);
        $companyId = Database::insert(
            "INSERT INTO companies (name, created_at, updated_at) VALUES (?, NOW(), NOW())",
            [$landlord ? $landlord['name'] . '\'s Properties' : 'Properties']
        );
        Database::execute(
            "INSERT INTO company_user (company_id, user_id) VALUES (?, ?)",
            [$companyId, $landlordId]
        );
        return $companyId;
    }

    public function index(): void
    {
        $this->ensurePhotosTable();
        $auth = Auth::instance();
        $user = $auth->user();
        $showArchived = !empty($_GET['show_archived']);

        $baseQuery = "p.*, u.name as landlord_name";
        if ($user['role'] !== 'tenant') {
            $baseQuery .= ",
                 " . Tenancy::tenantCountSql('p') . " as tenants_count,
                 (SELECT COUNT(*) FROM tickets WHERE property_id = p.id AND archived_at IS NULL) as tickets_count";
        }

        if ($user['role'] === 'tenant') {
            $properties = Database::fetchAll(
                "SELECT {$baseQuery} FROM properties p 
                 JOIN users u ON u.id = p.landlord_id
                 JOIN property_tenant pt ON pt.property_id = p.id 
                 WHERE pt.tenant_id = ? AND pt.moved_out_at IS NULL AND p.archived_at IS NULL",
                [$auth->id()]
            );
        } elseif ($user['role'] === 'admin') {
            $archivedClause = $showArchived ? '' : ' AND p.archived_at IS NULL';
            $properties = Database::fetchAll(
                "SELECT {$baseQuery}, p.archived_at
                 FROM properties p 
                 JOIN users u ON u.id = p.landlord_id 
                 WHERE 1=1{$archivedClause}
                 ORDER BY p.archived_at IS NULL DESC, p.name"
            );
        } else {
            $companyIds = Database::fetchAll(
                "SELECT company_id FROM company_user WHERE user_id = ?",
                [$auth->id()]
            );
            $companyIdList = implode(',', array_column($companyIds, 'company_id')) ?: '0';
            $archivedClause = $showArchived ? '' : ' AND p.archived_at IS NULL';

            $pmClause = $user['role'] === 'property_manager' ? ' AND p.property_manager_id = ?' : '';
            $params = $pmClause ? [$auth->id()] : [];

            $properties = Database::fetchAll(
                "SELECT {$baseQuery}, p.archived_at
                 FROM properties p 
                 JOIN users u ON u.id = p.landlord_id 
                 WHERE p.company_id IN ({$companyIdList}){$archivedClause}{$pmClause}
                 ORDER BY p.archived_at IS NULL DESC, p.name",
                $params
            );
        }

        // Attach main photo to each property — only for visible properties (scoped)
        $photoMap = [];
        if (!empty($properties)) {
            $ids = array_column($properties, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $photoIds = Database::fetchAll(
                "SELECT property_id, id, file_path, original_name, mime_type FROM property_photos WHERE is_main = 1 AND property_id IN ({$placeholders})",
                $ids
            );
            foreach ($photoIds as $ph) {
                $photoMap[$ph['property_id']] = $ph;
            }
        }
        foreach ($properties as &$prop) {
            $prop['main_photo'] = $photoMap[$prop['id']] ?? null;
        }
        unset($prop);

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Properties']);
        $view->render('properties/index', compact('properties', 'showArchived'));
    }

    public function create(): void
    {
        $landlords = Database::fetchAll(
            "SELECT id, name, email FROM users WHERE (role = 'landlord' OR FIND_IN_SET('landlord', REPLACE(COALESCE(secondary_roles, ''), ' ', ''))) AND archived_at IS NULL ORDER BY name"
        );

        $propertyManagers = Database::fetchAll(
            "SELECT id, name, email FROM users WHERE (role = 'property_manager' OR FIND_IN_SET('property_manager', REPLACE(COALESCE(secondary_roles, ''), ' ', ''))) AND archived_at IS NULL ORDER BY name"
        );

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Add Property']);
        $view->render('properties/create', compact('landlords', 'propertyManagers'));
    }

    public function store(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
        $validator = new Validator();
        if (
            !$validator->validate($_POST, [
            'landlord_id' => 'required|exists:users,id',
            'property_manager_id' => 'required|exists:users,id',
            'name' => 'required|max:255',
            'address' => 'required|max:255',
            'apt_suite' => 'max:100',
            'city' => 'required|max:255',
            'province' => 'required|max:255',
            'postal_code' => 'required|max:20',
            'country' => 'required|max:2',
            ])
        ) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/properties/create');
        }

        $companyId = $this->ensureLandlordCompany((int)$_POST['landlord_id']);

        $rentAmount = !empty($_POST['rent_amount']) ? (float)$_POST['rent_amount'] : null;
        $rentDueDay = !empty($_POST['rent_due_day']) ? (int)$_POST['rent_due_day'] : null;

        $heatingType = !empty($_POST['heating_type']) ? $_POST['heating_type'] : null;
        $securityDeposit = !empty($_POST['security_deposit']) ? (float)$_POST['security_deposit'] : null;

        $propertyId = Database::insert(
            "INSERT INTO properties (landlord_id, company_id, property_manager_id, name, address, apt_suite, city, province, postal_code, country, rent_amount, rent_due_day, heating_type, security_deposit, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [(int)$_POST['landlord_id'], $companyId, (int)$_POST['property_manager_id'], $_POST['name'], $_POST['address'] ?? '', $_POST['apt_suite'] ?? '', $_POST['city'] ?? '', $_POST['province'] ?? '', $_POST['postal_code'] ?? '', $_POST['country'] ?? 'CA', $rentAmount, $rentDueDay, $heatingType, $securityDeposit]
        );

        log_activity('property.created', "Property '{$_POST['name']}' created");
        flash('success', __('Property created successfully.'));
        redirect('/properties/' . $propertyId);
    }

    public function show(int $id): void
    {
        $property = Database::fetch(
            "SELECT p.*, l.name as landlord_name, pm.name as property_manager_name FROM properties p JOIN users l ON l.id = p.landlord_id LEFT JOIN users pm ON pm.id = p.property_manager_id WHERE p.id = ?",
            [$id]
        );
        if (!$property) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        Access::require(Access::property($id));

        try {
            $tenants = Database::fetchAll(
                "SELECT u.*, pt.id as property_tenant_id, pt.is_main_tenant, pt.assigned_at, pt.lease_type FROM users u 
                 JOIN property_tenant pt ON pt.tenant_id = u.id 
                 WHERE pt.property_id = ? AND pt.moved_out_at IS NULL AND u.archived_at IS NULL",
                [$id]
            );
        } catch (\Throwable $e) {
            $tenants = Database::fetchAll(
                "SELECT u.*, pt.id as property_tenant_id, pt.is_main_tenant, pt.assigned_at FROM users u 
                 JOIN property_tenant pt ON pt.tenant_id = u.id 
                 WHERE pt.property_id = ? AND pt.moved_out_at IS NULL AND u.archived_at IS NULL",
                [$id]
            );
        }

        $leases = Database::fetchAll(
            "SELECT l.*, (SELECT COUNT(*) FROM documents WHERE documentable_type = 'lease' AND documentable_id = l.id AND archived_at IS NULL) as documents_count 
             FROM leases l WHERE l.property_id = ? AND l.archived_at IS NULL ORDER BY l.created_at DESC",
            [$id]
        );

        $tickets = Database::fetchAll(
            "SELECT t.*, u.name as tenant_name FROM tickets t 
             JOIN users u ON u.id = t.tenant_id 
             WHERE t.property_id = ? AND t.archived_at IS NULL 
             ORDER BY t.created_at DESC LIMIT 10",
            [$id]
        );

        $photos = Database::fetchAll(
            "SELECT * FROM property_photos WHERE property_id = ? ORDER BY is_main DESC, sort_order ASC, created_at ASC, id ASC",
            [$id]
        );

        $payments = Database::fetchAll(
            "SELECT p.*, u.name as tenant_name, r.name as recorded_by_name
             FROM payments p
             JOIN property_tenant pt ON pt.id = p.property_tenant_id
             JOIN users u ON u.id = pt.tenant_id
             JOIN users r ON r.id = p.recorded_by
             WHERE pt.property_id = ? AND p.archived_at IS NULL
             ORDER BY p.payment_date DESC",
            [$id]
        );

        // Extract main tenant's lease type for Property Details display
        $mainTenantLeaseType = null;
        foreach ($tenants as $t) {
            if (!empty($t['is_main_tenant'])) {
                $mainTenantLeaseType = $t['lease_type'] ?? null;
                break;
            }
        }

        // Calculate current month status
        $currentMonth = date('Y-m');
        $totalRent = $property['rent_amount'] ?? 0;
        $paidThisMonth = 0;
        foreach ($payments as $pym) {
            // Deposits are held separately and must not make a unit look paid.
            if (!empty($pym['is_security_deposit'])) {
                continue;
            }
            if (str_starts_with($pym['payment_date'], $currentMonth)) {
                $paidThisMonth += $pym['amount'];
            }
        }
        $rentStatus = $totalRent > 0
            ? ($paidThisMonth >= $totalRent ? 'paid' : ($paidThisMonth > 0 ? 'partial' : 'unpaid'))
            : 'not_set';

        $view = new View();
        $view->layout('layouts/main', ['title' => $property['name']]);
        $view->render('properties/show', compact('property', 'tenants', 'leases', 'tickets', 'photos', 'payments', 'rentStatus', 'paidThisMonth', 'mainTenantLeaseType'));
    }

    public function edit(int $id): void
    {
        $property = Database::fetch("SELECT * FROM properties WHERE id = ? AND archived_at IS NULL", [$id]);
        if (!$property) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        Access::require(Access::property($id));

        $landlords = Database::fetchAll(
            "SELECT id, name, email FROM users WHERE (role = 'landlord' OR FIND_IN_SET('landlord', REPLACE(COALESCE(secondary_roles, ''), ' ', ''))) AND archived_at IS NULL ORDER BY name"
        );

        $propertyManagers = Database::fetchAll(
            "SELECT id, name, email FROM users WHERE (role = 'property_manager' OR FIND_IN_SET('property_manager', REPLACE(COALESCE(secondary_roles, ''), ' ', ''))) AND archived_at IS NULL ORDER BY name"
        );

        $photos = Database::fetchAll(
            "SELECT * FROM property_photos WHERE property_id = ? ORDER BY is_main DESC, sort_order ASC, created_at ASC, id ASC",
            [$id]
        );

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Edit Property']);
        $view->render('properties/edit', compact('property', 'landlords', 'propertyManagers', 'photos'));
    }

    public function update(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
        Access::require(Access::property($id));

        $validator = new Validator();
        if (
            !$validator->validate($_POST, [
            'landlord_id' => 'required|exists:users,id',
            'property_manager_id' => 'required|exists:users,id',
            'name' => 'required|max:255',
            'address' => 'required|max:255',
            'apt_suite' => 'max:100',
            'city' => 'required|max:255',
            'province' => 'required|max:255',
            'postal_code' => 'required|max:20',
            'country' => 'required|max:2',
            'ad_description' => 'max:5000',
            ])
        ) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/properties/' . $id . '/edit');
        }

        $companyId = $this->ensureLandlordCompany((int)$_POST['landlord_id']);

        $rentAmount = !empty($_POST['rent_amount']) ? (float)$_POST['rent_amount'] : null;
        $rentDueDay = !empty($_POST['rent_due_day']) ? (int)$_POST['rent_due_day'] : null;

        $heatingType = !empty($_POST['heating_type']) ? $_POST['heating_type'] : null;
        $securityDeposit = !empty($_POST['security_deposit']) ? (float)$_POST['security_deposit'] : null;

        Database::execute(
            "UPDATE properties SET landlord_id = ?, company_id = ?, property_manager_id = ?, name = ?, address = ?, apt_suite = ?, city = ?, province = ?, postal_code = ?, country = ?, rent_amount = ?, rent_due_day = ?, heating_type = ?, security_deposit = ?, ad_description = ?, updated_at = NOW() WHERE id = ?",
            [(int)$_POST['landlord_id'], $companyId, (int)$_POST['property_manager_id'], $_POST['name'], $_POST['address'] ?? '', $_POST['apt_suite'] ?? '', $_POST['city'] ?? '', $_POST['province'] ?? '', $_POST['postal_code'] ?? '', $_POST['country'] ?? 'CA', $rentAmount, $rentDueDay, $heatingType, $securityDeposit, trim((string)($_POST['ad_description'] ?? '')) !== '' ? trim((string)$_POST['ad_description']) : null, $id]
        );

        log_activity('property.updated', "Property '{$_POST['name']}' updated");
        flash('success', __('Property updated successfully.'));
        redirect('/properties/' . $id);
    }

    public function toggleListed(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
        $property = Database::fetch("SELECT id, name, listed FROM properties WHERE id = ? AND archived_at IS NULL", [$id]);
        if (!$property) {
            flash('error', __('Property not found.'));
            redirect('/properties');
        }
        Access::require(Access::property($id));

        $newListed = $property['listed'] ? 0 : 1;
        Database::execute("UPDATE properties SET listed = ?, updated_at = NOW() WHERE id = ?", [$newListed, $id]);

        $action = $newListed ? 'listed' : 'unlisted';
        log_activity("property.{$action}", "Property '{$property['name']}' {$action}");
        flash('success', $newListed ? __('Property listed for applications.') : __('Property unlisted from applications.'));
        redirect('/properties/' . $id);
    }

    private function ensurePhotosTable(): void
    {
        try {
            Database::query("SELECT 1 FROM property_photos LIMIT 1");
        } catch (\Throwable $e) {
            Database::query("CREATE TABLE IF NOT EXISTS property_photos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                property_id INT NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(100) DEFAULT '',
                is_main TINYINT(1) DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
                INDEX idx_property (property_id),
                INDEX idx_main (property_id, is_main),
                INDEX idx_sort (property_id, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    private function getPhotoUploadDir(int $propertyId): array
    {
        $dir = base_path('storage/uploads/property_photos/' . $propertyId);

        if (is_dir($dir) && is_writable($dir)) {
            return ['dir' => $dir, 'prefix' => 'storage/uploads/property_photos'];
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        // Ownership is managed by the deployment (www-data on Debian, apache
        // on Alpine); the application must not shell out to platform-specific
        // chown/chmod commands. Loosen permissions only if the inherited mode
        // prevents the web server from writing.
        if (is_dir($dir) && !is_writable($dir)) {
            @chmod($dir, 0775);
        }
        if (is_dir($dir) && is_writable($dir)) {
            return ['dir' => $dir, 'prefix' => 'storage/uploads/property_photos'];
        }
        return ['dir' => '', 'prefix' => ''];
    }

    public function uploadPhoto(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid form token.']);
            return;
        }
        $this->ensurePhotosTable();
        $property = Database::fetch("SELECT id, name FROM properties WHERE id = ? AND archived_at IS NULL", [$id]);
        if (!$property) {
            http_response_code(404);
            echo json_encode(['error' => 'Property not found.']);
            return;
        }
        Access::require(Access::property($id));

        header('Content-Type: application/json');

        if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid photo uploaded.']);
            return;
        }

        // Trust the file on disk, not the client-reported size.
        $actualSize = @filesize($_FILES['photo']['tmp_name']);
        if ($actualSize === false || $actualSize > 5 * 1024 * 1024) {
            http_response_code(413);
            echo json_encode(['error' => 'Photo must be smaller than 5 MB.']);
            return;
        }

        $prepared = prepare_image_upload($_FILES['photo']);
        if ($prepared === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Only JPG, PNG, GIF, WebP, and HEIC images are allowed.']);
            return;
        }
        $type = $prepared['mime'];

        $loc = $this->getPhotoUploadDir($id);
        if (!$loc['dir']) {
            http_response_code(500);
            echo json_encode(['error' => 'Upload directory is not writable.']);
            return;
        }

        // Extension comes from the detected MIME type, never from the client's
        // filename (a polyglot named "photo.php" must not be stored as PHP).
        $storedName = uniqid() . '.' . $prepared['ext'];
        $destPath = $loc['dir'] . '/' . $storedName;

        if (!store_image_upload($prepared, $destPath)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save photo.']);
            return;
        }

        $existingPhotos = Database::fetch("SELECT COUNT(*) as cnt, COALESCE(MAX(sort_order), -1) + 1 AS next_order FROM property_photos WHERE property_id = ?", [$id]);
        $isMain = ($existingPhotos && (int)$existingPhotos['cnt'] === 0) ? 1 : 0;
        $sortOrder = (int) ($existingPhotos['next_order'] ?? 0);

        $photoId = Database::insert(
            "INSERT INTO property_photos (property_id, file_path, original_name, mime_type, is_main, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$id, $loc['prefix'] . '/' . $id . '/' . $storedName, $_FILES['photo']['name'], $type, $isMain, $sortOrder]
        );

        echo json_encode([
            'id' => $photoId,
            'original_name' => $_FILES['photo']['name'],
            'url' => '/properties/' . $id . '/photos/' . $photoId,
        ]);
    }

    /** AJAX requests from the photo manager get JSON instead of a redirect. */
    private function wantsJson(): bool
    {
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        return $requestedWith === 'xmlhttprequest' || str_contains($accept, 'application/json');
    }

    public function setMainPhoto(int $id, int $photoId): void
    {
        $json = $this->wantsJson();
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            if ($json) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Invalid form token.']);
                return;
            }
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
        Access::require(Access::property($id));
        $this->ensurePhotosTable();
        $photo = Database::fetch("SELECT id FROM property_photos WHERE id = ? AND property_id = ?", [$photoId, $id]);
        if ($photo === null) {
            if ($json) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Photo not found.']);
                return;
            }
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        Database::execute("UPDATE property_photos SET is_main = 0 WHERE property_id = ?", [$id]);
        Database::execute("UPDATE property_photos SET is_main = 1 WHERE id = ? AND property_id = ?", [$photoId, $id]);
        log_activity('property.photo_main', "Photo #{$photoId} set as main for property #{$id}");
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'photo_id' => $photoId]);
            return;
        }
        flash('success', __('Main photo updated.'));
        redirect('/properties/' . $id . '/edit');
    }

    /** Persist a new display order. The main photo is kept first by the ORDER BY. */
    public function reorderPhotos(int $id): void
    {
        header('Content-Type: application/json');

        $payload = json_decode((string) file_get_contents('php://input'), true);
        $token = is_array($payload) ? (string) ($payload['_csrf'] ?? '') : '';
        if (!verify_csrf($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid form token.']);
            return;
        }

        Access::require(Access::property($id));
        $this->ensurePhotosTable();

        $submitted = is_array($payload) ? ($payload['ids'] ?? null) : null;
        if (!is_array($submitted)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid photo order.']);
            return;
        }

        $orderedIds = array_values(array_map('intval', $submitted));
        if (
            $orderedIds === [] || min($orderedIds) < 1
            || count(array_unique($orderedIds)) !== count($orderedIds)
        ) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid photo order.']);
            return;
        }

        $rows = Database::fetchAll("SELECT id FROM property_photos WHERE property_id = ?", [$id]);
        $existingIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $checkSubmitted = $orderedIds;
        sort($checkSubmitted);
        sort($existingIds);
        if ($checkSubmitted !== $existingIds) {
            http_response_code(400);
            echo json_encode(['error' => 'Photos do not belong to this property.']);
            return;
        }

        Database::beginTransaction();
        try {
            foreach ($orderedIds as $position => $photoId) {
                Database::execute(
                    "UPDATE property_photos SET sort_order = ? WHERE id = ? AND property_id = ?",
                    [$position, $photoId, $id]
                );
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Could not reorder photos.']);
            return;
        }

        log_activity('property.photos_reordered', "Photos reordered for property #{$id}");
        echo json_encode(['success' => true]);
    }

    public function deletePhoto(int $id, int $photoId): void
    {
        $json = $this->wantsJson();
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            if ($json) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Invalid form token.']);
                return;
            }
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
        Access::require(Access::property($id));
        $this->ensurePhotosTable();
        $photo = Database::fetch("SELECT * FROM property_photos WHERE id = ? AND property_id = ?", [$photoId, $id]);
        if ($photo) {
            $path = $photo['file_path'];
            $fullPath = str_starts_with($path, '/') ? $path : base_path($path);
            $allowedBase = realpath(base_path('storage/uploads/property_photos'));
            $realFull = realpath($fullPath);
            if ($realFull !== false && $allowedBase !== false && str_starts_with($realFull, $allowedBase) && file_exists($realFull)) {
                unlink($realFull);
            } elseif (file_exists($fullPath)) {
                $normalized = str_replace('\\', '/', $fullPath);
                if (str_contains($normalized, 'storage/uploads/property_photos/')) {
                    @unlink($fullPath);
                }
            }
            Database::execute("DELETE FROM property_photos WHERE id = ?", [$photoId]);
        }
        log_activity('property.photo_deleted', "Photo #{$photoId} deleted from property #{$id}");
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'photo_id' => $photoId]);
            return;
        }
        flash('success', __('Photo deleted.'));
        redirect('/properties/' . $id . '/edit');
    }

    public function servePhoto(int $id, int $photoId): void
    {
        Access::require(Access::property($id) && Access::photo($photoId));
        $this->ensurePhotosTable();
        $photo = Database::fetch("SELECT * FROM property_photos WHERE id = ? AND property_id = ?", [$photoId, $id]);
        if (!$photo) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }

        $path = $photo['file_path'];
        $fullPath = str_starts_with($path, '/') ? $path : base_path($path);

        if (!file_exists($fullPath)) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }

        $mime = $photo['mime_type'] ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: max-age=86400');
        readfile($fullPath);
        exit;
    }

    public function downloadPhoto(int $id, int $photoId): void
    {
        Access::require(Access::property($id) && Access::photo($photoId));
        $this->ensurePhotosTable();
        $photo = Database::fetch("SELECT * FROM property_photos WHERE id = ? AND property_id = ?", [$photoId, $id]);
        if (!$photo) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }

        $path = $photo['file_path'];
        $fullPath = str_starts_with($path, '/') ? $path : base_path($path);

        if (!file_exists($fullPath)) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }

        $mime = $photo['mime_type'] ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . sanitize_filename($photo['original_name']) . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }

    public function restore(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
        $property = Database::fetch("SELECT id, name, archived_batch FROM properties WHERE id = ?", [$id]);
        if (!$property) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        Access::require(Access::property($id));

        $batch = $property['archived_batch'] ?? null;
        if (!$batch) {
            // Legacy archives have no batch marker. Restoring them wholesale
            // would re-open links that ended years ago, so only the property
            // itself is re-activated.
            Database::execute("UPDATE properties SET archived_at = NULL WHERE id = ?", [$id]);
            log_activity('property.restored', "Property '{$property['name']}' restored (legacy archive)");
            flash('success', __('Property restored. Related records from this legacy archive were left unchanged.'));
            redirect('/properties');
        }

        $skipped = 0;
        $links = Database::fetchAll(
            "SELECT tenant_id FROM property_tenant
             WHERE property_id = ? AND moved_out_at IS NOT NULL AND archived_batch = ?",
            [$id, $batch]
        );
        foreach ($links as $link) {
            $tenantId = (int)$link['tenant_id'];
            // A tenant who has since moved to another property must not gain a
            // second active link by restoring this historical one.
            $elsewhere = Database::fetch(
                "SELECT 1 FROM property_tenant WHERE tenant_id = ? AND moved_out_at IS NULL AND property_id != ?",
                [$tenantId, $id]
            );
            if ($elsewhere) {
                $skipped++;
                continue;
            }
            Database::execute(
                "UPDATE property_tenant SET moved_out_at = NULL, archived_batch = NULL, updated_at = NOW()
                 WHERE property_id = ? AND tenant_id = ? AND archived_batch = ?",
                [$id, $tenantId, $batch]
            );
            $stillActive = Database::fetch(
                "SELECT 1 FROM property_tenant WHERE tenant_id = ? AND moved_out_at IS NULL",
                [$tenantId]
            );
            if ($stillActive) {
                Database::execute("UPDATE users SET archived_at = NULL WHERE id = ?", [$tenantId]);
            }
        }

        Database::execute(
            "UPDATE leases SET archived_at = NULL, archived_batch = NULL WHERE archived_batch = ?",
            [$batch]
        );
        Database::execute(
            "UPDATE tickets SET archived_at = NULL, archived_batch = NULL WHERE archived_batch = ?",
            [$batch]
        );
        Database::execute(
            "UPDATE payments SET archived_at = NULL, archived_batch = NULL WHERE archived_batch = ?",
            [$batch]
        );
        Database::execute(
            "UPDATE properties SET archived_at = NULL, archived_batch = NULL WHERE id = ?",
            [$id]
        );

        log_activity('property.restored', "Property '{$property['name']}' restored");
        $message = __('Property restored successfully. Related tenants, leases, and tickets have also been restored.');
        if ($skipped > 0) {
            $message .= ' ' . __('Some historical tenancies were left archived because those tenants are assigned elsewhere.');
        }
        flash('success', $message);
        redirect('/properties');
    }

    public function destroy(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
        $property = Database::fetch("SELECT id, name FROM properties WHERE id = ?", [$id]);
        if (!$property) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        Access::require(Access::property($id));

        // Tag every row touched by this archive so a later restore can undo
        // exactly this operation instead of every historical move-out.
        $batch = bin2hex(random_bytes(16));

        Database::execute(
            "UPDATE properties SET archived_at = NOW(), archived_batch = ? WHERE id = ?",
            [$batch, $id]
        );

        // Cascade archive to related records
        $tenantIds = Database::fetchAll("SELECT tenant_id FROM property_tenant WHERE property_id = ? AND moved_out_at IS NULL", [$id]);
        foreach ($tenantIds as $t) {
            Database::execute(
                "UPDATE property_tenant SET moved_out_at = NOW(), archived_batch = ?, updated_at = NOW() WHERE property_id = ? AND tenant_id = ? AND moved_out_at IS NULL",
                [$batch, $id, $t['tenant_id']]
            );
            Database::execute("UPDATE users SET archived_at = NOW() WHERE id = ?", [$t['tenant_id']]);
        }
        Database::execute(
            "UPDATE leases SET archived_at = NOW(), archived_batch = ? WHERE property_id = ? AND archived_at IS NULL",
            [$batch, $id]
        );
        Database::execute(
            "UPDATE tickets SET archived_at = NOW(), archived_batch = ?, status = 'closed' WHERE property_id = ? AND archived_at IS NULL",
            [$batch, $id]
        );
        Database::execute(
            "UPDATE payments SET archived_at = NOW(), archived_batch = ?
             WHERE property_tenant_id IN (SELECT id FROM property_tenant WHERE property_id = ?) AND archived_at IS NULL",
            [$batch, $id]
        );

        log_activity('property.archived', "Property '{$property['name']}' archived");
        flash('success', __('Property archived successfully. Related tenants, leases, and tickets have also been archived.'));
        redirect('/properties');
    }

    public function hardDelete(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
        $property = Database::fetch("SELECT id, name FROM properties WHERE id = ?", [$id]);
        if (!$property) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        Access::require(Access::property($id));

        try {
            Database::beginTransaction();
            // Related leases, tickets, property_tenant rows and their children
            // are removed by ON DELETE CASCADE.
            Database::execute("DELETE FROM property_photos WHERE property_id = ?", [$id]);
            Database::execute("DELETE FROM documents WHERE documentable_type = 'property' AND documentable_id = ?", [$id]);
            Database::execute("DELETE FROM properties WHERE id = ?", [$id]);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            \App\Core\Logger::error('property hardDelete failed: ' . $e->getMessage());
            flash('error', __('Could not permanently delete this property. No changes were made.'));
            redirect('/properties?show_archived=1');
        }

        log_activity('property.deleted', "Property '{$property['name']}' permanently deleted");
        flash('success', __('Property permanently deleted.'));
        redirect('/properties?show_archived=1');
    }
}
