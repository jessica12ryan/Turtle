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
             WHERE p.company_id IN ({$companyIdList}) AND u.archived_at IS NULL 
             ORDER BY u.name"
        );

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Tenants']);
        $view->render('tenants/index', compact('tenants'));
    }

    public function create(): void
    {
        $companyIds = Database::fetchAll(
            "SELECT company_id FROM company_user WHERE user_id = ?",
            [Auth::instance()->id()]
        );
        $companyIdList = implode(',', array_column($companyIds, 'company_id')) ?: '0';

        $properties = Database::fetchAll(
            "SELECT p.*, c.name as company_name FROM properties p 
             JOIN companies c ON c.id = p.company_id 
             WHERE p.company_id IN ({$companyIdList}) AND p.archived_at IS NULL 
             ORDER BY p.name"
        );

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
        ])) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/tenants/create');
        }

        $password = bin2hex(random_bytes(6));

        $tenantId = Database::insert(
            "INSERT INTO users (name, email, password, role, must_change_password, created_at, updated_at) VALUES (?, ?, ?, 'tenant', 1, NOW(), NOW())",
            [$_POST['name'], $_POST['email'], password_hash($password, PASSWORD_DEFAULT)]
        );

        $existingMain = Database::fetch(
            "SELECT id FROM property_tenant WHERE property_id = ? AND is_main_tenant = 1 AND moved_out_at IS NULL",
            [$_POST['property_id']]
        );

        $isMain = !$existingMain ? 1 : (int)($_POST['is_main_tenant'] ?? 0);

        Database::insert(
            "INSERT INTO property_tenant (property_id, tenant_id, is_main_tenant, assigned_at, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW(), NOW())",
            [$_POST['property_id'], $tenantId, $isMain]
        );

        $loginUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/login';
        Mailer::sendTemplate(
            $_POST['email'],
            'Welcome to Turtle - Your Account Has Been Created',
            'Hello ' . h($_POST['name']) . ',',
            'Your account has been created on the Turtle Tenant Management Portal.<br><br><strong>Your temporary password is: ' . $password . '</strong><br><br>Please log in and change your password immediately.',
            $loginUrl,
            'Log In'
        );

        flash('success', 'Tenant invited successfully. An email has been sent with their temporary password.');
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

        $companyIds = Database::fetchAll(
            "SELECT company_id FROM company_user WHERE user_id = ?",
            [Auth::instance()->id()]
        );
        $companyIdList = implode(',', array_column($companyIds, 'company_id')) ?: '0';

        $properties = Database::fetchAll(
            "SELECT p.*, c.name as company_name FROM properties p 
             JOIN companies c ON c.id = p.company_id 
             WHERE p.company_id IN ({$companyIdList}) AND p.archived_at IS NULL 
             ORDER BY p.name"
        );

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Edit Tenant']);
        $view->render('tenants/edit', compact('tenant', 'properties'));
    }

    public function update(int $id): void
    {
        $validator = new Validator();
        if (!$validator->validate($_POST, ['name' => 'required|max:255'])) {
            $_SESSION['_errors'] = $validator->errors();
            redirect('/tenants/' . $id . '/edit');
        }

        Database::execute(
            "UPDATE users SET name = ?, updated_at = NOW() WHERE id = ?",
            [$_POST['name'], $id]
        );

        flash('success', 'Tenant updated successfully.');
        redirect('/tenants/' . $id);
    }

    public function moveOut(int $id): void
    {
        $date = $_POST['moved_out_at'] ?? date('Y-m-d H:i:s');

        Database::execute(
            "UPDATE property_tenant SET moved_out_at = ?, updated_at = NOW() WHERE tenant_id = ? AND moved_out_at IS NULL",
            [$date, $id]
        );

        flash('success', 'Tenant move-out scheduled successfully.');
        redirect('/tenants/' . $id);
    }
}
