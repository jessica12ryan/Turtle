<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Core\Validator;

class SetupController
{
    public function create(): void
    {
        // If admin already exists, redirect away
        $admin = Database::fetch("SELECT id FROM users WHERE role = 'admin' AND archived_at IS NULL LIMIT 1");
        if ($admin) {
            if (Auth::instance()->check()) {
                redirect('/home');
            }
            redirect('/login');
        }

        $view = new View();
        $view->layout('layouts/guest', ['title' => 'Setup']);
        $view->render('setup/create');
    }

    public function store(): void
    {
        $admin = Database::fetch("SELECT id FROM users WHERE role = 'admin' AND archived_at IS NULL LIMIT 1");
        if ($admin) {
            if (Auth::instance()->check()) {
                redirect('/home');
            }
            redirect('/login');
        }

        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'name' => 'required|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
        ])) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/setup');
        }

        Database::insert(
            "INSERT INTO users (name, email, password, role, must_change_password, created_at, updated_at) VALUES (?, ?, ?, 'admin', 0, NOW(), NOW())",
            [$_POST['name'], $_POST['email'], password_hash($_POST['password'], PASSWORD_DEFAULT)]
        );

        // Optionally seed sample data
        if (!empty($_POST['load_sample_data'])) {
            $seedFile = base_path('database/seed.sql');
            if (file_exists($seedFile)) {
                $pdo = \App\Core\Database::instance()->getConnection();
                $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, 1);
                $raw = file_get_contents($seedFile);
                $raw = preg_replace('/^-- .*/m', '', $raw);
                $statements = explode(';', $raw);
                foreach ($statements as $stmt) {
                    $stmt = trim($stmt);
                    if ($stmt !== '') {
                        try {
                            $pdo->exec($stmt);
                        } catch (\Throwable $e) {
                            error_log('Seed data exec failed: ' . $e->getMessage());
                        }
                    }
                }
            }
        }

        // Save branding
        $siteName = trim($_POST['site_name'] ?? '');
        if ($siteName === '') {
            $siteName = 'Turtle';
        }
        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES ('site_name', ?) ON DUPLICATE KEY UPDATE `value` = ?",
            [$siteName, $siteName]
        );

        $keepDefault = !empty($_POST['logo_default']);
        if ($keepDefault) {
            Database::execute(
                "INSERT INTO settings (`key`, `value`) VALUES ('logo_path', '') ON DUPLICATE KEY UPDATE `value` = ''",
                []
            );
        } elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml'];
            $type = $_FILES['logo']['type'];
            if (in_array($type, $allowed)) {
                $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $filename = 'logo.' . $ext;
                $uploadDir = base_path('storage/uploads/logo/');
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $filename)) {
                    Database::execute(
                        "INSERT INTO settings (`key`, `value`) VALUES ('logo_path', ?) ON DUPLICATE KEY UPDATE `value` = ?",
                        ['storage/uploads/logo/' . $filename, 'storage/uploads/logo/' . $filename]
                    );
                }
            }
        }

        Auth::instance()->login($_POST['email'], $_POST['password']);
        redirect('/home');
    }
}
