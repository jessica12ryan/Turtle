<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Core\Validator;
use App\Core\Mailer;

class TenantController
{
    public function index(): void
    {
        $user = Auth::instance()->user();
        if ($user['role'] === 'admin') {
            $tenants = Database::fetchAll(
                "SELECT u.*, pt.is_main_tenant, pt.moved_out_at, pt.assigned_at,
                 p.name as property_name, p.id as property_id 
                 FROM users u 
                 JOIN property_tenant pt ON pt.tenant_id = u.id 
                 JOIN properties p ON p.id = pt.property_id 
                 WHERE u.archived_at IS NULL 
                 ORDER BY u.name"
            );
        } else {
            $landlordIds = Database::fetchAll(
                "SELECT landlord_id FROM properties WHERE archived_at IS NULL
                 AND landlord_id IN (SELECT cu.user_id FROM company_user cu WHERE cu.user_id = ?)
                 UNION SELECT ?",
                [Auth::instance()->id(), Auth::instance()->id()]
            );
            $landlordIdList = implode(',', array_column($landlordIds, 'landlord_id')) ?: '0';

            $tenants = Database::fetchAll(
                "SELECT u.*, pt.is_main_tenant, pt.moved_out_at, pt.assigned_at,
                 p.name as property_name, p.id as property_id 
                 FROM users u 
                 JOIN property_tenant pt ON pt.tenant_id = u.id 
                 JOIN properties p ON p.id = pt.property_id 
                 WHERE p.landlord_id IN ({$landlordIdList}) AND u.archived_at IS NULL 
                 ORDER BY u.name"
            );
        }

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Tenants']);
        $view->render('tenants/index', compact('tenants'));
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
            $landlordIds = Database::fetchAll(
                "SELECT landlord_id FROM properties WHERE archived_at IS NULL
                 AND landlord_id IN (SELECT cu.user_id FROM company_user cu WHERE cu.user_id = ?)
                 UNION SELECT ?",
                [$user['id'], $user['id']]
            );
            $landlordIdList = implode(',', array_column($landlordIds, 'landlord_id')) ?: '0';

            $properties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name FROM properties p 
                 JOIN users u ON u.id = p.landlord_id 
                 WHERE p.landlord_id IN ({$landlordIdList}) AND p.archived_at IS NULL 
                 ORDER BY p.name"
            );
        }

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Invite Tenant']);
        $view->render('tenants/create', compact('properties'));
    }

    public function store(): void
    {
        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'name' => 'required|max:255',
            'email' => 'required|email|unique:users,email',
            'property_id' => 'required|exists:properties,id',
            'phone' => 'required|max:20',
        ])) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/tenants/create');
        }

        // Format phone number
        $phone = $_POST['phone'] ?? '';
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 10) {
            $phone = '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6, 4);
        }

        $password = bin2hex(random_bytes(6));

        $tenantId = Database::insert(
            "INSERT INTO users (name, email, phone, password, role, must_change_password, created_at, updated_at) VALUES (?, ?, ?, ?, 'tenant', 1, NOW(), NOW())",
            [$_POST['name'], $_POST['email'], $phone, password_hash($password, PASSWORD_DEFAULT)]
        );

        $existingMain = Database::fetch(
            "SELECT pt.id, p.name as property_name FROM property_tenant pt 
             JOIN properties p ON p.id = pt.property_id
             WHERE pt.property_id = ? AND pt.is_main_tenant = 1 AND pt.moved_out_at IS NULL",
            [$_POST['property_id']]
        );

        if ($existingMain && !empty($_POST['is_main_tenant'])) {
            flash('error', 'Main tenant already exists for ' . h($existingMain['property_name']) . '. Uncheck "Main tenant" or choose a different property.');
            $_SESSION['_old'] = $_POST;
            redirect('/tenants/create');
        }

        $isMain = !$existingMain ? 1 : 0;

        Database::insert(
            "INSERT INTO property_tenant (property_id, tenant_id, is_main_tenant, assigned_at, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW(), NOW())",
            [$_POST['property_id'], $tenantId, $isMain]
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

        $user = Auth::instance()->user();
        if ($user['role'] === 'admin') {
            $properties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name FROM properties p 
                 JOIN users u ON u.id = p.landlord_id 
                 WHERE p.archived_at IS NULL 
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

            $properties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name FROM properties p 
                 JOIN users u ON u.id = p.landlord_id 
                 WHERE p.landlord_id IN ({$landlordIdList}) AND p.archived_at IS NULL 
                 ORDER BY p.name"
            );
        }

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
        if (!$validator->validate($_POST, $rules)) {
            $_SESSION['_errors'] = $validator->errors();
            redirect('/tenants/' . $id . '/edit');
        }

        $phone = $_POST['phone'] ?? '';
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 10) {
            $phone = '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6, 4);
        }

        Database::execute(
            "UPDATE users SET name = ?, phone = ?, updated_at = NOW() WHERE id = ?",
            [$_POST['name'], $phone, $id]
        );

        flash('success', 'Tenant updated successfully.');
        redirect('/tenants/' . $id);
    }

    public function moveOut(int $id): void
    {
        Database::execute(
            "UPDATE property_tenant SET moved_out_at = NOW(), updated_at = NOW() WHERE tenant_id = ? AND moved_out_at IS NULL",
            [$id]
        );
        Database::execute(
            "UPDATE users SET archived_at = NOW() WHERE id = ?",
            [$id]
        );

        flash('success', 'Tenant archived successfully.');
        redirect('/tenants');
    }

    public function destroy(int $id): void
    {
        $target = Database::fetch("SELECT * FROM users WHERE id = ? AND role = 'tenant'", [$id]);
        if (!$target) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        if ($target['role'] === 'admin') {
            $adminCount = Database::fetch("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin' AND archived_at IS NULL AND id != ?", [$id]);
            if (!$adminCount || $adminCount['cnt'] === 0) {
                flash('error', 'Cannot delete the only active admin account.');
                redirect('/tenants');
            }
        }

        Database::execute("DELETE FROM property_tenant WHERE tenant_id = ?", [$id]);
        Database::execute("DELETE FROM users WHERE id = ?", [$id]);

        flash('success', 'Tenant permanently deleted.');
        redirect('/tenants');
    }
}
