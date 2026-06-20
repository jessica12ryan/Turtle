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

        if ($user['role'] === 'tenant') {
            $leases = Database::fetchAll(
                "SELECT l.*, p.name as property_name FROM leases l 
                 JOIN properties p ON p.id = l.property_id 
                 JOIN property_tenant pt ON pt.property_id = l.property_id 
                 WHERE pt.tenant_id = ? AND pt.moved_out_at IS NULL AND l.archived_at IS NULL 
                 ORDER BY l.created_at DESC",
                [$auth->id()]
            );
        } elseif ($user['role'] === 'admin') {
            $leases = Database::fetchAll(
                "SELECT l.*, p.name as property_name, c.name as company_name,
                 (SELECT COUNT(*) FROM documents WHERE documentable_type = 'lease' AND documentable_id = l.id AND archived_at IS NULL) as documents_count
                 FROM leases l 
                 JOIN properties p ON p.id = l.property_id 
                 JOIN companies c ON c.id = p.company_id 
                 WHERE l.archived_at IS NULL 
                 ORDER BY l.created_at DESC"
            );
        } else {
            $companyIds = Database::fetchAll(
                "SELECT company_id FROM company_user WHERE user_id = ?",
                [$auth->id()]
            );
            $companyIdList = implode(',', array_column($companyIds, 'company_id')) ?: '0';

            $leases = Database::fetchAll(
                "SELECT l.*, p.name as property_name, c.name as company_name,
                 (SELECT COUNT(*) FROM documents WHERE documentable_type = 'lease' AND documentable_id = l.id AND archived_at IS NULL) as documents_count
                 FROM leases l 
                 JOIN properties p ON p.id = l.property_id 
                 JOIN companies c ON c.id = p.company_id 
                 WHERE p.company_id IN ({$companyIdList}) AND l.archived_at IS NULL 
                 ORDER BY l.created_at DESC"
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
        $view->render('leases/index', compact('leases'));
    }

    public function create(): void
    {
        $user = Auth::instance()->user();
        if ($user['role'] === 'admin') {
            $properties = Database::fetchAll(
                "SELECT p.*, c.name as company_name FROM properties p 
                 JOIN companies c ON c.id = p.company_id 
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
                "SELECT p.*, c.name as company_name FROM properties p 
                 JOIN companies c ON c.id = p.company_id 
                 WHERE p.company_id IN ({$companyIdList}) AND p.archived_at IS NULL 
                 ORDER BY p.name"
            );
        }

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Upload Lease']);
        $view->render('leases/create', compact('properties'));
    }

    public function store(): void
    {
        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'property_id' => 'required|exists:properties,id',
            'title' => 'required|max:255',
            'description' => 'max:5000',
        ])) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/leases/create');
        }

        $leaseId = Database::insert(
            "INSERT INTO leases (property_id, title, description, uploaded_by, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())",
            [$_POST['property_id'], $_POST['title'], $_POST['description'] ?? '', Auth::instance()->id()]
        );

        if (!empty($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
            $uploadDir = base_path('storage/uploads/leases/' . $leaseId);
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            foreach ($_FILES['documents']['name'] as $i => $name) {
                if ($_FILES['documents']['error'][$i] !== UPLOAD_ERR_OK) continue;

                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $storedName = uniqid() . '.' . $ext;
                $destPath = $uploadDir . '/' . $storedName;

                if (move_uploaded_file($_FILES['documents']['tmp_name'][$i], $destPath)) {
                    Database::insert(
                        "INSERT INTO documents (documentable_type, documentable_id, file_path, original_name, size, mime_type, uploaded_by, created_at, updated_at) VALUES ('lease', ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                        [$leaseId, 'storage/uploads/leases/' . $leaseId . '/' . $storedName, $name, filesize($destPath), $_FILES['documents']['type'][$i] ?? '', Auth::instance()->id()]
                    );
                }
            }
        }

        flash('success', 'Lease uploaded successfully.');
        redirect('/leases/' . $leaseId);
    }

    public function show(int $id): void
    {
        $lease = Database::fetch(
            "SELECT l.*, p.name as property_name, p.company_id, u.name as uploader_name 
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
}
