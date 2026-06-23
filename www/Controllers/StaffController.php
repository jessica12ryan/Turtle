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
        $showArchived = !empty($_GET['show_archived']);
        $archivedClause = $showArchived ? '' : ' AND u.archived_at IS NULL';

        if ($user['role'] === 'admin' || $user['role'] === 'landlord') {
            $staff = Database::fetchAll(
                "SELECT u.* FROM users u
                 WHERE 1=1{$archivedClause}
                 AND u.role IN ('landlord','property_manager','maintenance')
                 ORDER BY u.archived_at IS NULL DESC, u.name"
            );
        } else {
            $companyIds = Database::fetchAll(
                "SELECT company_id FROM company_user WHERE user_id = ?",
                [$auth->id()]
            );
            $companyIdList = implode(',', array_column($companyIds, 'company_id')) ?: '0';

            $staff = Database::fetchAll(
                "SELECT u.* FROM users u
                 JOIN company_user cu ON cu.user_id = u.id
                 WHERE cu.company_id IN ({$companyIdList}){$archivedClause}
                 AND u.role IN ('property_manager','maintenance')
                 ORDER BY u.archived_at IS NULL DESC, u.name"
            );
        }

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Staff']);
        $view->render('staff/index', compact('staff', 'showArchived'));
    }

    public function create(): void
    {
        $roles = ['property_manager', 'maintenance'];
        if (Auth::instance()->user()['role'] === 'admin') {
            $roles[] = 'landlord';
        }

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Invite Staff']);
        $view->render('staff/create', compact('roles'));
    }

    public function store(): void
    {
        $allowedRoles = ['property_manager', 'maintenance'];
        if (Auth::instance()->user()['role'] === 'admin') {
            $allowedRoles[] = 'landlord';
        }

        // Check for archived duplicate email
        $archived = Database::fetch("SELECT id, role FROM users WHERE email = ? AND archived_at IS NOT NULL", [$_POST['email']]);
        if ($archived) {
            $roleLabel = $archived['role'] === 'tenant' ? 'tenant' : 'staff member';
            flash('error', 'Email exists in archived ' . $roleLabel . '.');
            $_SESSION['_old'] = $_POST;
            redirect('/staff/create');
        }

        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'name' => 'required|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:' . implode(',', $allowedRoles),
        ])) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/staff/create');
        }

        $password = bin2hex(random_bytes(6));

        $timezone = $_POST['timezone'] ?: null;

        $userId = Database::insert(
            "INSERT INTO users (name, email, password, role, timezone, must_change_password, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())",
            [$_POST['name'], $_POST['email'], password_hash($password, PASSWORD_DEFAULT), $_POST['role'], $timezone]
        );

        $creatorCompanies = Database::fetchAll(
            "SELECT company_id FROM company_user WHERE user_id = ?",
            [Auth::instance()->id()]
        );
        foreach ($creatorCompanies as $cc) {
            Database::execute(
                "INSERT IGNORE INTO company_user (company_id, user_id) VALUES (?, ?)",
                [$cc['company_id'], $userId]
            );
        }

        if (!empty($_POST['send_welcome_email'])) {
            $loginUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/login';
            Mailer::sendTemplate(
                $_POST['email'],
                'Welcome to Turtle - Your Account Has Been Created',
                'Hello ' . h($_POST['name']) . ',',
                'You have been added as a ' . ucfirst(str_replace('_', ' ', $_POST['role'])) . ' on the Turtle portal.<br><br><strong>Your temporary password is: ' . $password . '</strong><br><br>Please log in and change your password immediately.',
                $loginUrl,
                'Log In'
            );
        }

        flash('success', 'Staff member added successfully.');
        redirect('/staff');
    }

    public function show(int $id): void
    {
        $staff = Database::fetch(
            "SELECT u.* FROM users u WHERE u.id = ? AND u.archived_at IS NULL AND u.role IN ('landlord','property_manager','maintenance')",
            [$id]
        );
        if (!$staff) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $assignedTickets = Database::fetchAll(
            "SELECT t.*, p.name as property_name FROM tickets t
             JOIN properties p ON p.id = t.property_id
             WHERE t.assigned_to = ? AND t.archived_at IS NULL
             ORDER BY t.created_at DESC LIMIT 10",
            [$id]
        );

        $view = new View();
        $view->layout('layouts/main', ['title' => $staff['name']]);
        $view->render('staff/show', compact('staff', 'assignedTickets'));
    }

    public function edit(int $id): void
    {
        $staff = Database::fetch(
            "SELECT u.* FROM users u WHERE u.id = ? AND u.archived_at IS NULL AND u.role IN ('landlord','property_manager','maintenance')",
            [$id]
        );
        if (!$staff) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $roles = ['property_manager', 'maintenance'];
        if ($staff['role'] === 'landlord') {
            $roles[] = 'landlord';
        }

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Edit Staff']);
        $view->render('staff/edit', compact('staff', 'roles'));
    }

    public function update(int $id): void
    {
        $staff = Database::fetch(
            "SELECT * FROM users WHERE id = ? AND archived_at IS NULL AND role IN ('landlord','property_manager','maintenance')",
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

        $timezone = $_POST['timezone'] ?: null;

        $sql = "UPDATE users SET name = ?, timezone = ?, updated_at = NOW()";
        $params = [$_POST['name'], $timezone];

        if (!empty($_POST['password'])) {
            $sql .= ", password = ?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;
        Database::execute($sql, $params);

        flash('success', 'Staff updated successfully.');
        redirect('/staff/' . $id);
    }

    public function restore(int $id): void
    {
        Database::execute("UPDATE users SET archived_at = NULL WHERE id = ? AND role IN ('admin','landlord','property_manager','maintenance')", [$id]);
        flash('success', 'Staff member restored successfully.');
        redirect('/staff');
    }

    public function destroy(int $id): void
    {
        $target = Database::fetch("SELECT * FROM users WHERE id = ? AND archived_at IS NULL", [$id]);
        if (!$target) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        if ($target['role'] === 'admin') {
            $adminCount = Database::fetch("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin' AND archived_at IS NULL AND id != ?", [$id]);
            if (!$adminCount || $adminCount['cnt'] === 0) {
                flash('error', 'Cannot archive the only active admin account.');
                redirect('/staff/' . $id);
            }
        }

        Database::execute("UPDATE users SET archived_at = NOW() WHERE id = ?", [$id]);
        flash('success', 'Staff member archived successfully.');
        redirect('/staff');
    }

    public function hardDelete(int $id): void
    {
        $target = Database::fetch("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$target) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        if ($target['role'] === 'admin') {
            $adminCount = Database::fetch("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin' AND archived_at IS NULL AND id != ?", [$id]);
            if (!$adminCount || $adminCount['cnt'] === 0) {
                flash('error', 'Cannot permanently delete the only active admin account.');
                redirect('/staff');
            }
        }

        Database::execute("DELETE FROM company_user WHERE user_id = ?", [$id]);
        Database::execute("DELETE FROM users WHERE id = ?", [$id]);
        flash('success', 'Staff member permanently deleted.');
        redirect('/staff');
    }
}
