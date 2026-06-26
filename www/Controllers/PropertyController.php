<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Core\Validator;

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
                 (SELECT COUNT(*) FROM property_tenant WHERE property_id = p.id AND moved_out_at IS NULL) as tenants_count,
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

            $properties = Database::fetchAll(
                "SELECT {$baseQuery}, p.archived_at
                 FROM properties p 
                 JOIN users u ON u.id = p.landlord_id 
                 WHERE p.company_id IN ({$companyIdList}){$archivedClause}
                 ORDER BY p.archived_at IS NULL DESC, p.name"
            );
        }

        // Attach main photo to each property
        $photoIds = Database::fetchAll(
            "SELECT property_id, id, file_path, original_name, mime_type FROM property_photos WHERE is_main = 1 AND property_id IN (SELECT id FROM properties WHERE 1=1)"
        );
        $photoMap = [];
        foreach ($photoIds as $ph) {
            $photoMap[$ph['property_id']] = $ph;
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
            "SELECT id, name, email FROM users WHERE role = 'landlord' AND archived_at IS NULL ORDER BY name"
        );

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Add Property']);
        $view->render('properties/create', compact('landlords'));
    }

    public function store(): void
    {
        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'landlord_id' => 'required|exists:users,id',
            'name' => 'required|max:255',
            'address' => 'required|max:255',
            'apt_suite' => 'max:100',
            'city' => 'required|max:255',
            'province' => 'required|max:255',
            'postal_code' => 'required|max:20',
            'country' => 'required|max:2',
        ])) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/properties/create');
        }

        $companyId = $this->ensureLandlordCompany((int)$_POST['landlord_id']);

        $propertyId = Database::insert(
            "INSERT INTO properties (landlord_id, company_id, name, address, apt_suite, city, province, postal_code, country, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [$_POST['landlord_id'], $companyId, $_POST['name'], $_POST['address'] ?? '', $_POST['apt_suite'] ?? '', $_POST['city'] ?? '', $_POST['province'] ?? '', $_POST['postal_code'] ?? '', $_POST['country'] ?? 'CA']
        );

        flash('success', 'Property created successfully.');
        redirect('/properties/' . $propertyId);
    }

    public function show(int $id): void
    {
        $property = Database::fetch(
            "SELECT p.*, u.name as landlord_name FROM properties p JOIN users u ON u.id = p.landlord_id WHERE p.id = ?",
            [$id]
        );
        if (!$property) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $tenants = Database::fetchAll(
            "SELECT u.*, pt.is_main_tenant, pt.assigned_at FROM users u 
             JOIN property_tenant pt ON pt.tenant_id = u.id 
             WHERE pt.property_id = ? AND pt.moved_out_at IS NULL AND u.archived_at IS NULL",
            [$id]
        );

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
            "SELECT * FROM property_photos WHERE property_id = ? ORDER BY is_main DESC, created_at ASC",
            [$id]
        );

        $view = new View();
        $view->layout('layouts/main', ['title' => $property['name']]);
        $view->render('properties/show', compact('property', 'tenants', 'leases', 'tickets', 'photos'));
    }

    public function edit(int $id): void
    {
        $property = Database::fetch("SELECT * FROM properties WHERE id = ? AND archived_at IS NULL", [$id]);
        if (!$property) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $landlords = Database::fetchAll(
            "SELECT id, name, email FROM users WHERE role = 'landlord' AND archived_at IS NULL ORDER BY name"
        );

        $photos = Database::fetchAll(
            "SELECT * FROM property_photos WHERE property_id = ? ORDER BY is_main DESC, created_at ASC",
            [$id]
        );

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Edit Property']);
        $view->render('properties/edit', compact('property', 'landlords', 'photos'));
    }

    public function update(int $id): void
    {
        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'landlord_id' => 'required|exists:users,id',
            'name' => 'required|max:255',
            'address' => 'required|max:255',
            'apt_suite' => 'max:100',
            'city' => 'required|max:255',
            'province' => 'required|max:255',
            'postal_code' => 'required|max:20',
            'country' => 'required|max:2',
        ])) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/properties/' . $id . '/edit');
        }

        $companyId = $this->ensureLandlordCompany((int)$_POST['landlord_id']);

        Database::execute(
            "UPDATE properties SET landlord_id = ?, company_id = ?, name = ?, address = ?, apt_suite = ?, city = ?, province = ?, postal_code = ?, country = ?, updated_at = NOW() WHERE id = ?",
            [$_POST['landlord_id'], $companyId, $_POST['name'], $_POST['address'] ?? '', $_POST['apt_suite'] ?? '', $_POST['city'] ?? '', $_POST['province'] ?? '', $_POST['postal_code'] ?? '', $_POST['country'] ?? 'CA', $id]
        );

        flash('success', 'Property updated successfully.');
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
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
                INDEX idx_property (property_id),
                INDEX idx_main (property_id, is_main)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    private function getPhotoUploadDir(int $propertyId): array
    {
        $primaryDir = base_path('storage/uploads/property_photos/' . $propertyId);
        $fallbackBase = sys_get_temp_dir() . '/turtle_photos';
        $fallbackDir = $fallbackBase . '/' . $propertyId;

        if (is_dir($primaryDir) && is_writable($primaryDir)) {
            return ['dir' => $primaryDir, 'prefix' => 'storage/uploads/property_photos'];
        }
        if (!is_dir($fallbackDir)) {
            @mkdir($fallbackDir, 0777, true);
        }
        if (is_dir($fallbackDir) && is_writable($fallbackDir)) {
            return ['dir' => $fallbackDir, 'prefix' => $fallbackBase];
        }
        if (!is_dir($primaryDir)) {
            @mkdir($primaryDir, 0777, true);
            @chmod($primaryDir, 0777);
            $escaped = escapeshellarg($primaryDir);
            exec("chmod -R 777 {$escaped} 2>/dev/null; chown -R www-data:www-data {$escaped} 2>/dev/null");
        }
        if (is_dir($primaryDir) && is_writable($primaryDir)) {
            return ['dir' => $primaryDir, 'prefix' => 'storage/uploads/property_photos'];
        }
        return ['dir' => '', 'prefix' => ''];
    }

    public function uploadPhoto(int $id): void
    {
        $this->ensurePhotosTable();
        $property = Database::fetch("SELECT id, name FROM properties WHERE id = ? AND archived_at IS NULL", [$id]);
        if (!$property) {
            http_response_code(404);
            echo json_encode(['error' => 'Property not found.']);
            return;
        }

        header('Content-Type: application/json');

        if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid photo uploaded.']);
            return;
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $type = $_FILES['photo']['type'] ?? '';
        if (!in_array($type, $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['error' => 'Only JPG, PNG, GIF, and WebP images are allowed.']);
            return;
        }

        $loc = $this->getPhotoUploadDir($id);
        if (!$loc['dir']) {
            http_response_code(500);
            echo json_encode(['error' => 'Upload directory is not writable.']);
            return;
        }

        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $storedName = uniqid() . '.' . $ext;
        $destPath = $loc['dir'] . '/' . $storedName;

        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $destPath)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save photo.']);
            return;
        }

        $photoId = Database::insert(
            "INSERT INTO property_photos (property_id, file_path, original_name, mime_type, is_main, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
            [$id, $loc['prefix'] . '/' . $id . '/' . $storedName, $_FILES['photo']['name'], $type, 0]
        );

        echo json_encode([
            'id' => $photoId,
            'original_name' => $_FILES['photo']['name'],
            'url' => '/properties/' . $id . '/photos/' . $photoId,
        ]);
    }

    public function setMainPhoto(int $id, int $photoId): void
    {
        $this->ensurePhotosTable();
        Database::execute("UPDATE property_photos SET is_main = 0 WHERE property_id = ?", [$id]);
        Database::execute("UPDATE property_photos SET is_main = 1 WHERE id = ? AND property_id = ?", [$photoId, $id]);
        flash('success', 'Main photo updated.');
        redirect('/properties/' . $id . '/edit');
    }

    public function deletePhoto(int $id, int $photoId): void
    {
        $this->ensurePhotosTable();
        $photo = Database::fetch("SELECT * FROM property_photos WHERE id = ? AND property_id = ?", [$photoId, $id]);
        if ($photo) {
            $path = $photo['file_path'];
            $fullPath = str_starts_with($path, '/') ? $path : base_path($path);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            Database::execute("DELETE FROM property_photos WHERE id = ?", [$photoId]);
        }
        flash('success', 'Photo deleted.');
        redirect('/properties/' . $id . '/edit');
    }

    public function servePhoto(int $id, int $photoId): void
    {
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
            echo 'File not found.';
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
            echo 'File not found.';
            return;
        }

        $mime = $photo['mime_type'] ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $photo['original_name'] . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }

    public function restore(int $id): void
    {
        Database::execute("UPDATE properties SET archived_at = NULL WHERE id = ?", [$id]);

        // Cascade restore tenants, leases, and tickets that were archived with this property
        $tenantIds = Database::fetchAll("SELECT tenant_id FROM property_tenant WHERE property_id = ? AND moved_out_at IS NOT NULL", [$id]);
        foreach ($tenantIds as $t) {
            Database::execute("UPDATE property_tenant SET moved_out_at = NULL WHERE property_id = ? AND tenant_id = ?", [$id, $t['tenant_id']]);
            Database::execute("UPDATE users SET archived_at = NULL WHERE id = ?", [$t['tenant_id']]);
        }
        Database::execute("UPDATE leases SET archived_at = NULL WHERE property_id = ? AND archived_at IS NOT NULL", [$id]);
        Database::execute("UPDATE tickets SET archived_at = NULL WHERE property_id = ? AND archived_at IS NOT NULL", [$id]);

        flash('success', 'Property restored successfully. Related tenants, leases, and tickets have also been restored.');
        redirect('/properties');
    }

    public function destroy(int $id): void
    {
        Database::execute("UPDATE properties SET archived_at = NOW() WHERE id = ?", [$id]);

        // Cascade archive to related records
        $tenantIds = Database::fetchAll("SELECT tenant_id FROM property_tenant WHERE property_id = ? AND moved_out_at IS NULL", [$id]);
        foreach ($tenantIds as $t) {
            Database::execute("UPDATE property_tenant SET moved_out_at = NOW() WHERE property_id = ? AND tenant_id = ?", [$id, $t['tenant_id']]);
            Database::execute("UPDATE users SET archived_at = NOW() WHERE id = ?", [$t['tenant_id']]);
        }
        Database::execute("UPDATE leases SET archived_at = NOW() WHERE property_id = ? AND archived_at IS NULL", [$id]);
        Database::execute("UPDATE tickets SET archived_at = NOW() WHERE property_id = ? AND archived_at IS NULL", [$id]);

        flash('success', 'Property archived successfully. Related tenants, leases, and tickets have also been archived.');
        redirect('/properties');
    }
}
