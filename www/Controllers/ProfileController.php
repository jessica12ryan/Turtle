<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Core\Validator;

class ProfileController
{
    public function edit(): void
    {
        $user = Auth::instance()->user();
        $view = new View();
        $view->layout('layouts/main', ['title' => 'Profile']);
        $view->render('profile/edit', compact('user'));
    }

    public function update(): void
    {
        $validator = new Validator();
        $rules = ['name' => 'required|max:255'];

        if (!empty($_POST['password'])) {
            $rules['password'] = 'min:8|confirmed';
        }

        if (!$validator->validate($_POST, $rules)) {
            $_SESSION['_errors'] = $validator->errors();
            redirect('/profile');
        }

        $timezone = $_POST['timezone'] ?: null;

        $sql = "UPDATE users SET name = ?, timezone = ?, updated_at = NOW()";
        $params = [$_POST['name'], $timezone];

        if (!empty($_POST['password'])) {
            $sql .= ", password = ?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = ?";
        $params[] = Auth::instance()->id();

        Database::execute($sql, $params);

        flash('success', 'Profile updated successfully.');
        redirect('/profile');
    }
}
