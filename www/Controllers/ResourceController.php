<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Core\Validator;

class ResourceController
{
    public function index(): void
    {
        $links = Database::fetchAll("SELECT r.*, u.name as created_by_name FROM resources r JOIN users u ON u.id = r.created_by ORDER BY r.title");

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
        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'title' => 'required|max:255',
            'url' => 'required|max:500',
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

        Database::insert(
            "INSERT INTO resources (title, url, description, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())",
            [$_POST['title'], $url, $_POST['description'] ?? '', Auth::instance()->id()]
        );

        flash('success', 'Resource added successfully.');
        redirect('/resources');
    }

    public function edit(int $id): void
    {
        $link = Database::fetch("SELECT * FROM resources WHERE id = ?", [$id]);
        if (!$link) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Edit Resource']);
        $view->render('resources/edit', compact('link'));
    }

    public function update(int $id): void
    {
        $link = Database::fetch("SELECT * FROM resources WHERE id = ?", [$id]);
        if (!$link) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'title' => 'required|max:255',
            'url' => 'required|max:500',
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

        Database::execute(
            "UPDATE resources SET title = ?, url = ?, description = ?, updated_at = NOW() WHERE id = ?",
            [$_POST['title'], $url, $_POST['description'] ?? '', $id]
        );

        flash('success', 'Resource updated successfully.');
        redirect('/resources');
    }

    public function destroy(int $id): void
    {
        $link = Database::fetch("SELECT * FROM resources WHERE id = ?", [$id]);
        if (!$link) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        Database::execute("DELETE FROM resources WHERE id = ?", [$id]);
        flash('success', 'Resource deleted successfully.');
        redirect('/resources');
    }
}
