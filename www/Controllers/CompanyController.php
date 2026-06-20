<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Core\Validator;

class CompanyController
{
    public function index(): void
    {
        $companies = Database::fetchAll(
            "SELECT c.*, (SELECT COUNT(*) FROM properties WHERE company_id = c.id AND archived_at IS NULL) as properties_count 
             FROM companies c 
             JOIN company_user cu ON cu.company_id = c.id 
             WHERE cu.user_id = ? AND c.archived_at IS NULL 
             ORDER BY c.name",
            [Auth::instance()->id()]
        );

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Companies']);
        $view->render('companies/index', compact('companies'));
    }

    public function create(): void
    {
        $view = new View();
        $view->layout('layouts/main', ['title' => 'Create Company']);
        $view->render('companies/create');
    }

    public function store(): void
    {
        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'name' => 'required|max:255',
            'address' => 'max:255',
            'city' => 'max:255',
            'province' => 'max:255',
            'postal_code' => 'max:20',
            'phone' => 'max:20',
        ])) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/companies/create');
        }

        $companyId = Database::insert(
            "INSERT INTO companies (name, address, city, province, postal_code, phone, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [$_POST['name'], $_POST['address'] ?? '', $_POST['city'] ?? '', $_POST['province'] ?? '', $_POST['postal_code'] ?? '', $_POST['phone'] ?? '']
        );

        Database::execute(
            "INSERT INTO company_user (company_id, user_id) VALUES (?, ?)",
            [$companyId, Auth::instance()->id()]
        );

        flash('success', 'Company created successfully.');
        redirect('/companies/' . $companyId);
    }

    public function show(int $id): void
    {
        $company = Database::fetch(
            "SELECT * FROM companies WHERE id = ? AND archived_at IS NULL",
            [$id]
        );
        if (!$company) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $properties = Database::fetchAll(
            "SELECT * FROM properties WHERE company_id = ? AND archived_at IS NULL ORDER BY name",
            [$id]
        );

        $users = Database::fetchAll(
            "SELECT u.* FROM users u JOIN company_user cu ON cu.user_id = u.id WHERE cu.company_id = ? AND u.archived_at IS NULL",
            [$id]
        );

        $view = new View();
        $view->layout('layouts/main', ['title' => $company['name']]);
        $view->render('companies/show', compact('company', 'properties', 'users'));
    }

    public function edit(int $id): void
    {
        $company = Database::fetch(
            "SELECT * FROM companies WHERE id = ? AND archived_at IS NULL",
            [$id]
        );
        if (!$company) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Edit ' . $company['name']]);
        $view->render('companies/edit', compact('company'));
    }

    public function update(int $id): void
    {
        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'name' => 'required|max:255',
            'address' => 'max:255',
            'city' => 'max:255',
            'province' => 'max:255',
            'postal_code' => 'max:20',
            'phone' => 'max:20',
        ])) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/companies/' . $id . '/edit');
        }

        Database::execute(
            "UPDATE companies SET name = ?, address = ?, city = ?, province = ?, postal_code = ?, phone = ?, updated_at = NOW() WHERE id = ?",
            [$_POST['name'], $_POST['address'] ?? '', $_POST['city'] ?? '', $_POST['province'] ?? '', $_POST['postal_code'] ?? '', $_POST['phone'] ?? '', $id]
        );

        flash('success', 'Company updated successfully.');
        redirect('/companies/' . $id);
    }

    public function destroy(int $id): void
    {
        Database::execute(
            "UPDATE companies SET archived_at = NOW() WHERE id = ?",
            [$id]
        );
        flash('success', 'Company archived successfully.');
        redirect('/companies');
    }
}
