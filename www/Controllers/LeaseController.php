<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Core\Validator;

class LeaseController
{
    public function index(): void
    {
        $auth = Auth::instance();
        $user = $auth->user();
        $showArchived = !empty($_GET['show_archived']);
        $archivedClause = $showArchived ? '' : ' AND l.archived_at IS NULL';

        if ($user['role'] === 'tenant') {
            $leases = Database::fetchAll(
                "SELECT l.*, p.name as property_name FROM leases l 
                 JOIN properties p ON p.id = l.property_id 
                 JOIN property_tenant pt ON pt.property_id = l.property_id 
                 WHERE pt.tenant_id = ? AND pt.moved_out_at IS NULL{$archivedClause} 
                 ORDER BY l.archived_at IS NULL DESC, l.created_at DESC",
                [$auth->id()]
            );
        } elseif ($user['role'] === 'admin') {
            $leases = Database::fetchAll(
                "SELECT l.*, p.name as property_name, u.name as landlord_name,
                 (SELECT COUNT(*) FROM documents WHERE documentable_type = 'lease' AND documentable_id = l.id AND archived_at IS NULL) as documents_count,
                 l.archived_at
                 FROM leases l 
                 JOIN properties p ON p.id = l.property_id 
                 JOIN users u ON u.id = p.landlord_id 
                 WHERE 1=1{$archivedClause}
                 ORDER BY l.archived_at IS NULL DESC, l.created_at DESC"
            );
        } else {
            $companyIds = Database::fetchAll(
                "SELECT company_id FROM company_user WHERE user_id = ?",
                [$auth->id()]
            );
            $companyIdList = implode(',', array_column($companyIds, 'company_id')) ?: '0';

            $leases = Database::fetchAll(
                "SELECT l.*, p.name as property_name, u.name as landlord_name,
                 (SELECT COUNT(*) FROM documents WHERE documentable_type = 'lease' AND documentable_id = l.id AND archived_at IS NULL) as documents_count,
                 l.archived_at
                 FROM leases l 
                 JOIN properties p ON p.id = l.property_id 
                 JOIN users u ON u.id = p.landlord_id 
                 WHERE p.company_id IN ({$companyIdList}){$archivedClause}
                 ORDER BY l.archived_at IS NULL DESC, l.created_at DESC"
            );
        }

        // Get document counts for each lease
        foreach ($leases as &$lease) {
            $lease['documents'] = Database::fetchAll(
                "SELECT * FROM documents WHERE documentable_type = 'lease' AND documentable_id = ? AND archived_at IS NULL",
                [$lease['id']]
            );
        }
        unset($lease);

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Leases']);
        $view->render('leases/index', compact('leases', 'showArchived'));
    }

    public function create(): void
    {
        $user = Auth::instance()->user();
        if ($user['role'] === 'admin') {
            $properties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name,
                 (SELECT pt.tenant_id FROM property_tenant pt WHERE pt.property_id = p.id AND pt.moved_out_at IS NULL AND pt.is_main_tenant = 1 LIMIT 1) as main_tenant_id
                 FROM properties p 
                 JOIN users u ON u.id = p.landlord_id 
                 WHERE p.archived_at IS NULL
                 AND EXISTS (SELECT 1 FROM property_tenant pt WHERE pt.property_id = p.id AND pt.moved_out_at IS NULL)
                 ORDER BY p.name"
            );
        } else {
            $companyIds = Database::fetchAll(
                "SELECT company_id FROM company_user WHERE user_id = ?",
                [$user['id']]
            );
            $companyIdList = implode(',', array_column($companyIds, 'company_id')) ?: '0';

            $properties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name,
                 (SELECT pt.tenant_id FROM property_tenant pt WHERE pt.property_id = p.id AND pt.moved_out_at IS NULL AND pt.is_main_tenant = 1 LIMIT 1) as main_tenant_id
                 FROM properties p 
                 JOIN users u ON u.id = p.landlord_id 
                 WHERE p.company_id IN ({$companyIdList}) AND p.archived_at IS NULL
                 AND EXISTS (SELECT 1 FROM property_tenant pt WHERE pt.property_id = p.id AND pt.moved_out_at IS NULL)
                 ORDER BY p.name"
            );
        }

        // Fetch tenant names for main tenants
        $tenantNames = [];
        foreach ($properties as $p) {
            if (!empty($p['main_tenant_id'])) {
                $tenant = Database::fetch("SELECT id, name FROM users WHERE id = ?", [$p['main_tenant_id']]);
                if ($tenant) {
                    $tenantNames[$p['id']] = $tenant['name'];
                }
            }
        }

        $noTenantProperties = [];
        if ($user['role'] === 'admin') {
            $noTenantProperties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name FROM properties p 
                 JOIN users u ON u.id = p.landlord_id
                 WHERE p.archived_at IS NULL
                 AND NOT EXISTS (SELECT 1 FROM property_tenant pt WHERE pt.property_id = p.id AND pt.moved_out_at IS NULL)
                 ORDER BY p.name"
            );
        } else {
            $landlordIds = Database::fetchAll(
                "SELECT landlord_id FROM properties WHERE archived_at IS NULL
                 AND landlord_id IN (SELECT cu.user_id FROM company_user cu WHERE cu.user_id = ?)
                 UNION SELECT ?",
                [$user['id'], $user['id']]
            );
            $landlordIdList = implode(',', array_column($landlordIds, 'landlord_id')) ?: '0';
            if ($landlordIdList !== '0') {
                $noTenantProperties = Database::fetchAll(
                    "SELECT p.*, u.name as landlord_name FROM properties p 
                     JOIN users u ON u.id = p.landlord_id
                     WHERE p.landlord_id IN ({$landlordIdList}) AND p.archived_at IS NULL
                     AND NOT EXISTS (SELECT 1 FROM property_tenant pt WHERE pt.property_id = p.id AND pt.moved_out_at IS NULL)
                     ORDER BY p.name"
                );
            }
        }

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Upload Lease']);
        $view->render('leases/create', compact('properties', 'tenantNames', 'noTenantProperties'));
    }

    public function store(): void
    {
        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'property_id' => 'required|exists:properties,id',
            'tenant_id' => 'required|exists:users,id',
            'title' => 'required|max:255',
            'description' => 'max:5000',
        ])) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/leases/create');
        }

        $leaseId = Database::insert(
            "INSERT INTO leases (property_id, tenant_id, title, description, uploaded_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
            [$_POST['property_id'], $_POST['tenant_id'], $_POST['title'], $_POST['description'] ?? '', Auth::instance()->id()]
        );

        if (!empty($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
            $uploadDir = base_path('storage/uploads/leases/' . $leaseId);
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $uploaded = 0;
            $errors = 0;
            foreach ($_FILES['documents']['name'] as $i => $name) {
                if (empty($name)) continue;
                if ($_FILES['documents']['error'][$i] !== UPLOAD_ERR_OK) {
                    $errors++;
                    continue;
                }

                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $storedName = uniqid() . '.' . $ext;
                $destPath = $uploadDir . '/' . $storedName;

                if (move_uploaded_file($_FILES['documents']['tmp_name'][$i], $destPath)) {
                    Database::insert(
                        "INSERT INTO documents (documentable_type, documentable_id, file_path, original_name, size, mime_type, uploaded_by, created_at, updated_at) VALUES ('lease', ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                        [$leaseId, 'storage/uploads/leases/' . $leaseId . '/' . $storedName, $name, filesize($destPath), $_FILES['documents']['type'][$i] ?? '', Auth::instance()->id()]
                    );
                    $uploaded++;
                } else {
                    $errors++;
                }
            }

            if ($errors > 0) {
                flash('error', $uploaded > 0 ? "{$uploaded} file(s) uploaded, but {$errors} file(s) failed." : 'File upload failed. Check file size and permissions.');
            }
        }

        flash('success', 'Lease uploaded successfully.');
        redirect('/leases/' . $leaseId);
    }

    public function show(int $id): void
    {
        $lease = Database::fetch(
            "SELECT l.*, p.name as property_name, u.name as uploader_name 
             FROM leases l 
             JOIN properties p ON p.id = l.property_id 
             JOIN users u ON u.id = l.uploaded_by 
             WHERE l.id = ? AND l.archived_at IS NULL",
            [$id]
        );
        if (!$lease) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $documents = Database::fetchAll(
            "SELECT * FROM documents WHERE documentable_type = 'lease' AND documentable_id = ? AND archived_at IS NULL",
            [$id]
        );

        $view = new View();
        $view->layout('layouts/main', ['title' => $lease['title']]);
        $view->render('leases/show', compact('lease', 'documents'));
    }

    public function destroy(int $id): void
    {
        Database::execute("UPDATE leases SET archived_at = NOW() WHERE id = ?", [$id]);
        flash('success', 'Lease archived successfully.');
        redirect('/leases');
    }

    public function hardDelete(int $id): void
    {
        $documents = Database::fetchAll(
            "SELECT file_path FROM documents WHERE documentable_type = 'lease' AND documentable_id = ?",
            [$id]
        );
        foreach ($documents as $doc) {
            $path = base_path($doc['file_path']);
            if (file_exists($path)) {
                unlink($path);
            }
        }
        Database::execute("DELETE FROM documents WHERE documentable_type = 'lease' AND documentable_id = ?", [$id]);
        Database::execute("DELETE FROM leases WHERE id = ?", [$id]);
        flash('success', 'Lease permanently deleted.');
        redirect('/leases');
    }
}
