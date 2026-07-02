<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Core\Validator;

class ResourceController
{
    private ?string $tableCreateError = null;

    private function ensureTableExists(): void
    {
        try {
            Database::execute("SELECT 1 FROM resources LIMIT 1");
        } catch (\Throwable $e) {
            $pdo = \App\Core\Database::instance()->getConnection();

            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS resources (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    url VARCHAR(500) NOT NULL,
                    description TEXT,
                    created_by INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (\Throwable $e2) {
                $this->tableCreateError = $e2->getMessage();
                error_log('ResourceController ensureTableExists failed: ' . $e2->getMessage());
                return;
            }

            try {
                $pdo->exec("ALTER TABLE resources ADD CONSTRAINT fk_resources_created_by FOREIGN KEY (created_by) REFERENCES users(id)");
            } catch (\Throwable $e3) {
                error_log('ResourceController ensureTableExists FK failed: ' . $e3->getMessage());
            }
        }
    }

    public function index(): void
    {
        try {
            $this->ensureTableExists();
            $links = Database::fetchAll("SELECT r.*, u.name as created_by_name FROM resources r JOIN users u ON u.id = r.created_by ORDER BY r.title");
        } catch (\Throwable $e) {
            error_log('ResourceController@index query failed: ' . $e->getMessage());
            $links = [];
        }

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Resources']);
        $view->render('resources/index', compact('links'));
    }

    public function create(): void
    {
        $view = new View();
        $view->layout('layouts/main', ['title' => 'Add Resource']);
        $view->render('resources/create');
    }

    public function store(): void
    {
        $this->ensureTableExists();
        if (!isset($_POST['_csrf']) || !verify_csrf($_POST['_csrf'])) {
            flash('error', 'Invalid security token.');
            redirect('/resources/create');
        }

        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'title' => 'required|max:255',
            'url' => 'required',
            'description' => 'max:1000',
        ])) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/resources/create');
        }

        $url = $_POST['url'];
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        if (strlen($url) > 500) {
            flash('error', 'URL must not exceed 500 characters.');
            $_SESSION['_old'] = $_POST;
            redirect('/resources/create');
        }

        $userId = Auth::instance()->id();
        if (!$userId) {
            flash('error', 'Your session has expired. Please log in again.');
            redirect('/login');
        }

        try {
            Database::insert(
                "INSERT INTO resources (title, url, description, created_by) VALUES (?, ?, ?, ?)",
                [$_POST['title'], $url, $_POST['description'] ?? '', $userId]
            );
        } catch (\Throwable $e) {
            error_log('ResourceController@store insert failed: ' . $e->getMessage());
            $msg = 'Failed to add resource: ' . $e->getMessage();
            if ($this->tableCreateError) {
                $msg .= ' | Table creation error: ' . $this->tableCreateError;
            }
            flash('error', $msg);
            $_SESSION['_old'] = $_POST;
            redirect('/resources/create');
        }

        log_activity('resource.created', "Resource '{$_POST['title']}' added");
        flash('success', 'Resource added successfully.');
        redirect('/resources');
    }

    public function edit(int $id): void
    {
        $this->ensureTableExists();
        $link = Database::fetch("SELECT * FROM resources WHERE id = ?", [$id]);
        if (!$link) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Edit Resource']);
        $view->render('resources/edit', compact('link'));
    }

    public function update(int $id): void
    {
        $this->ensureTableExists();
        $link = Database::fetch("SELECT * FROM resources WHERE id = ?", [$id]);
        if (!$link) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'title' => 'required|max:255',
            'url' => 'required',
            'description' => 'max:1000',
        ])) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/resources/' . $id . '/edit');
        }

        $url = $_POST['url'];
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        if (strlen($url) > 500) {
            flash('error', 'URL must not exceed 500 characters.');
            $_SESSION['_old'] = $_POST;
            redirect('/resources/' . $id . '/edit');
        }

        try {
            Database::execute(
                "UPDATE resources SET title = ?, url = ?, description = ?, updated_at = NOW() WHERE id = ?",
                [$_POST['title'], $url, $_POST['description'] ?? '', $id]
            );
        } catch (\Throwable $e) {
            error_log('ResourceController@update failed: ' . $e->getMessage());
            flash('error', 'Failed to update resource: ' . $e->getMessage());
            $_SESSION['_old'] = $_POST;
            redirect('/resources/' . $id . '/edit');
        }

        log_activity('resource.updated', "Resource '{$_POST['title']}' updated");
        flash('success', 'Resource updated successfully.');
        redirect('/resources');
    }

    public function destroy(int $id): void
    {
        $this->ensureTableExists();
        $link = Database::fetch("SELECT * FROM resources WHERE id = ?", [$id]);
        if (!$link) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        log_activity('resource.deleted', "Resource '" . ($link['title'] ?? '') . "' deleted");
        Database::execute("DELETE FROM resources WHERE id = ?", [$id]);
        flash('success', 'Resource deleted successfully.');
        redirect('/resources');
    }
}
