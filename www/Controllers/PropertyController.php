<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Core\Validator;

class PropertyController
{
    public function index(): void
    {
        $auth = Auth::instance();
        $user = $auth->user();

        if ($user['role'] === 'tenant') {
            $properties = Database::fetchAll(
                "SELECT p.*, c.name as company_name FROM properties p 
                 JOIN property_tenant pt ON pt.property_id = p.id 
                 JOIN companies c ON c.id = p.company_id 
                 WHERE pt.tenant_id = ? AND pt.moved_out_at IS NULL AND p.archived_at IS NULL",
                [$auth->id()]
            );
        } else {
            $companyIds = Database::fetchAll(
                "SELECT company_id FROM company_user WHERE user_id = ?",
                [$auth->id()]
            );
            $companyIdList = implode(',', array_column($companyIds, 'company_id')) ?: '0';

            $properties = Database::fetchAll(
                "SELECT p.*, c.name as company_name,
                 (SELECT COUNT(*) FROM property_tenant WHERE property_id = p.id AND moved_out_at IS NULL) as tenants_count,
                 (SELECT COUNT(*) FROM tickets WHERE property_id = p.id AND archived_at IS NULL) as tickets_count
                 FROM properties p 
                 JOIN companies c ON c.id = p.company_id 
                 WHERE p.company_id IN ({$companyIdList}) AND p.archived_at IS NULL 
                 ORDER BY p.name"
            );
        }

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Properties']);
        $view->render('properties/index', compact('properties'));
    }

    public function create(): void
    {
        $companies = Database::fetchAll(
            "SELECT c.* FROM companies c 
             JOIN company_user cu ON cu.company_id = c.id 
             WHERE cu.user_id = ? AND c.archived_at IS NULL 
             ORDER BY c.name",
            [Auth::instance()->id()]
        );

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Add Property']);
        $view->render('properties/create', compact('companies'));
    }

    public function store(): void
    {
        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|max:255',
            'address' => 'max:255',
            'city' => 'max:255',
            'province' => 'max:255',
            'postal_code' => 'max:20',
        ])) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/properties/create');
        }

        $propertyId = Database::insert(
            "INSERT INTO properties (company_id, name, address, city, province, postal_code, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [$_POST['company_id'], $_POST['name'], $_POST['address'] ?? '', $_POST['city'] ?? '', $_POST['province'] ?? '', $_POST['postal_code'] ?? '']
        );

        flash('success', 'Property created successfully.');
        redirect('/properties/' . $propertyId);
    }

    public function show(int $id): void
    {
        $property = Database::fetch(
            "SELECT p.*, c.name as company_name FROM properties p JOIN companies c ON c.id = p.company_id WHERE p.id = ? AND p.archived_at IS NULL",
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

        $view = new View();
        $view->layout('layouts/main', ['title' => $property['name']]);
        $view->render('properties/show', compact('property', 'tenants', 'leases', 'tickets'));
    }

    public function edit(int $id): void
    {
        $property = Database::fetch("SELECT * FROM properties WHERE id = ? AND archived_at IS NULL", [$id]);
        if (!$property) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $companies = Database::fetchAll(
            "SELECT c.* FROM companies c JOIN company_user cu ON cu.company_id = c.id WHERE cu.user_id = ? AND c.archived_at IS NULL ORDER BY c.name",
            [Auth::instance()->id()]
        );

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Edit Property']);
        $view->render('properties/edit', compact('property', 'companies'));
    }

    public function update(int $id): void
    {
        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|max:255',
            'address' => 'max:255',
            'city' => 'max:255',
            'province' => 'max:255',
            'postal_code' => 'max:20',
        ])) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/properties/' . $id . '/edit');
        }

        Database::execute(
            "UPDATE properties SET company_id = ?, name = ?, address = ?, city = ?, province = ?, postal_code = ?, updated_at = NOW() WHERE id = ?",
            [$_POST['company_id'], $_POST['name'], $_POST['address'] ?? '', $_POST['city'] ?? '', $_POST['province'] ?? '', $_POST['postal_code'] ?? '', $id]
        );

        flash('success', 'Property updated successfully.');
        redirect('/properties/' . $id);
    }

    public function destroy(int $id): void
    {
        Database::execute("UPDATE properties SET archived_at = NOW() WHERE id = ?", [$id]);
        flash('success', 'Property archived successfully.');
        redirect('/properties');
    }
}
