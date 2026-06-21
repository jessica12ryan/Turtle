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
                // Strip comment lines and split by semicolons for multi-statement exec
                $sql = preg_replace('/^-- .*/m', '', file_get_contents($seedFile));
                $pdo->exec($sql);
            }
        }

        Auth::instance()->login($_POST['email'], $_POST['password']);
        redirect('/home');
    }
}
