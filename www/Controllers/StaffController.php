<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Core\Validator;
use App\Core\Mailer;

class StaffController
{
    public function index(): void
    {
        $auth = Auth::instance();
        $user = $auth->user();

        if ($user['role'] === 'admin') {
            $staff = Database::fetchAll(
                "SELECT u.*, GROUP_CONCAT(c.name SEPARATOR ', ') as company_names
                 FROM users u
                 LEFT JOIN company_user cu ON cu.user_id = u.id
                 LEFT JOIN companies c ON c.id = cu.company_id
                 WHERE u.archived_at IS NULL
                 AND u.role IN ('admin','landlord','property_manager','maintenance')
                 GROUP BY u.id
                 ORDER BY u.name"
            );
        } else {
            $companyIds = Database::fetchAll(
                "SELECT company_id FROM company_user WHERE user_id = ?",
                [$auth->id()]
            );
            $companyIdList = implode(',', array_column($companyIds, 'company_id')) ?: '0';

            $staff = Database::fetchAll(
                "SELECT u.*, GROUP_CONCAT(c.name SEPARATOR ', ') as company_names
                 FROM users u
                 JOIN company_user cu ON cu.user_id = u.id
                 JOIN companies c ON c.id = cu.company_id
                 WHERE c.id IN ({$companyIdList}) AND u.archived_at IS NULL
                 AND u.role IN ('landlord','property_manager','maintenance')
                 GROUP BY u.id
                 ORDER BY u.name"
            );
        }

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Staff']);
        $view->render('staff/index', compact('staff'));
    }

    public function create(): void
    {
        $user = Auth::instance()->user();
        if ($user['role'] === 'admin') {
            $companies = Database::fetchAll(
                "SELECT c.* FROM companies c WHERE c.archived_at IS NULL ORDER BY c.name"
            );
        } else {
            $companies = Database::fetchAll(
                "SELECT c.* FROM companies c
                 JOIN company_user cu ON cu.company_id = c.id
                 WHERE cu.user_id = ? AND c.archived_at IS NULL
                 ORDER BY c.name",
                [$user['id']]
            );
        }

        $roles = ['property_manager', 'maintenance'];

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Invite Staff']);
        $view->render('staff/create', compact('companies', 'roles'));
    }

    public function store(): void
    {
        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'name' => 'required|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:property_manager,maintenance',
            'company_id' => 'required|exists:companies,id',
        ])) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/staff/create');
        }

        $password = bin2hex(random_bytes(6));

        $userId = Database::insert(
            "INSERT INTO users (name, email, password, role, must_change_password, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())",
            [$_POST['name'], $_POST['email'], password_hash($password, PASSWORD_DEFAULT), $_POST['role']]
        );

        Database::execute(
            "INSERT INTO company_user (company_id, user_id) VALUES (?, ?)",
            [$_POST['company_id'], $userId]
        );

        $loginUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/login';
        Mailer::sendTemplate(
            $_POST['email'],
            'Welcome to Turtle - Your Account Has Been Created',
            'Hello ' . h($_POST['name']) . ',',
            'You have been added as a ' . ucfirst(str_replace('_', ' ', $_POST['role'])) . ' on the Turtle portal.<br><br><strong>Your temporary password is: ' . $password . '</strong><br><br>Please log in and change your password immediately.',
            $loginUrl,
            'Log In'
        );

        flash('success', 'Staff member invited successfully. An email has been sent with their temporary password.');
        redirect('/staff');
    }

    public function show(int $id): void
    {
        $staff = Database::fetch(
            "SELECT u.* FROM users u WHERE u.id = ? AND u.archived_at IS NULL     AND u.role IN ('admin','landlord','property_manager','maintenance')",
            [$id]
        );
        if (!$staff) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $companies = Database::fetchAll(
            "SELECT c.* FROM companies c
             JOIN company_user cu ON cu.company_id = c.id
             WHERE cu.user_id = ? AND c.archived_at IS NULL",
            [$id]
        );

        $assignedTickets = Database::fetchAll(
            "SELECT t.*, p.name as property_name FROM tickets t
             JOIN properties p ON p.id = t.property_id
             WHERE t.assigned_to = ? AND t.archived_at IS NULL
             ORDER BY t.created_at DESC LIMIT 10",
            [$id]
        );

        $view = new View();
        $view->layout('layouts/main', ['title' => $staff['name']]);
        $view->render('staff/show', compact('staff', 'companies', 'assignedTickets'));
    }

    public function edit(int $id): void
    {
        $staff = Database::fetch(
            "SELECT u.* FROM users u WHERE u.id = ? AND u.archived_at IS NULL AND u.role IN ('admin','landlord','property_manager','maintenance')",
            [$id]
        );
        if (!$staff) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $user = Auth::instance()->user();
        if ($user['role'] === 'admin') {
            $companies = Database::fetchAll(
                "SELECT c.* FROM companies c WHERE c.archived_at IS NULL ORDER BY c.name"
            );
        } else {
            $companies = Database::fetchAll(
                "SELECT c.* FROM companies c
                 JOIN company_user cu ON cu.company_id = c.id
                 WHERE cu.user_id = ? AND c.archived_at IS NULL
                 ORDER BY c.name",
                [$user['id']]
            );
        }

        $assignedCompanyIds = Database::fetchAll(
            "SELECT company_id FROM company_user WHERE user_id = ?",
            [$id]
        );
        $assignedCompanyIds = array_column($assignedCompanyIds, 'company_id');

        $roles = ['property_manager', 'maintenance'];

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Edit Staff']);
        $view->render('staff/edit', compact('staff', 'companies', 'assignedCompanyIds', 'roles'));
    }

    public function update(int $id): void
    {
        $staff = Database::fetch(
            "SELECT * FROM users WHERE id = ? AND archived_at IS NULL     AND role IN ('admin','landlord','property_manager','maintenance')",
            [$id]
        );
        if (!$staff) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $validator = new Validator();
        $rules = ['name' => 'required|max:255'];
        if (!empty($_POST['password'])) {
            $rules['password'] = 'min:8|confirmed';
        }
        if (!$validator->validate($_POST, $rules)) {
            $_SESSION['_errors'] = $validator->errors();
            redirect('/staff/' . $id . '/edit');
        }

        $sql = "UPDATE users SET name = ?, updated_at = NOW()";
        $params = [$_POST['name']];

        if (!empty($_POST['password'])) {
            $sql .= ", password = ?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;
        Database::execute($sql, $params);

        if (!empty($_POST['company_ids']) && in_array(Auth::instance()->user()['role'], ['admin', 'landlord'])) {
            Database::execute("DELETE FROM company_user WHERE user_id = ?", [$id]);
            foreach ($_POST['company_ids'] as $companyId) {
                Database::execute(
                    "INSERT INTO company_user (company_id, user_id) VALUES (?, ?)",
                    [(int)$companyId, $id]
                );
            }
        }

        flash('success', 'Staff updated successfully.');
        redirect('/staff/' . $id);
    }

    public function destroy(int $id): void
    {
        Database::execute("UPDATE users SET archived_at = NOW() WHERE id = ?", [$id]);
        flash('success', 'Staff member archived successfully.');
        redirect('/staff');
    }
}
