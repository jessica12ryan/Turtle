<?php

namespace App\Controllers;

use App\Core\Access;
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
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
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
        $theme = $_POST['theme'] ?? 'system';
        $language = $_POST['language'] ?: null;

        $userId = (int)Auth::instance()->id();
        $hasAvatarColumn = $this->avatarColumnExists();
        $avatarPath = null;

        if ($hasAvatarColumn) {
            $current = Database::fetch("SELECT avatar_path FROM users WHERE id = ?", [$userId]);
            $avatarPath = $current['avatar_path'] ?? null;
            $avatarError = $this->handleAvatar($userId, $avatarPath);
            if ($avatarError !== null) {
                flash('error', $avatarError);
                redirect('/profile');
            }
        }

        $sql = "UPDATE users SET name = ?, timezone = ?, language = ?, theme = ?, updated_at = NOW()";
        $params = [$_POST['name'], $timezone, $language, $theme];
        if ($hasAvatarColumn) {
            $sql = "UPDATE users SET name = ?, timezone = ?, language = ?, theme = ?, avatar_path = ?, updated_at = NOW()";
            $params[] = $avatarPath;
        }

        if (!empty($_POST['password'])) {
            $sql .= ", password = ?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = ?";
        $params[] = Auth::instance()->id();

        Database::execute($sql, $params);

        if ($language) {
            $_SESSION['_language'] = $language;
        } else {
            unset($_SESSION['_language']);
        }

        log_activity('profile.updated', __('Profile updated'));
        flash('success', __('Profile updated successfully.'));
        redirect('/profile');
    }

    private function avatarColumnExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }
        try {
            Database::fetch("SELECT avatar_path FROM users LIMIT 1", []);
            $exists = true;
        } catch (\Throwable $e) {
            $exists = false;
        }
        return $exists;
    }

    private function handleAvatar(int $userId, ?string &$avatarPath): ?string
    {
        if (!empty($_POST['remove_avatar'])) {
            $this->deleteAvatarFile($avatarPath);
            $avatarPath = null;
            return null;
        }

        $file = $_FILES['avatar'] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return __('Unable to upload profile picture.');
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            return __('Profile picture must be smaller than 5 MB.');
        }

        $prepared = prepare_image_upload($file);
        if ($prepared === null) {
            return __('Only JPG, PNG, GIF, WebP, and HEIC images are allowed.');
        }

        $dir = base_path('storage/uploads/avatars/' . $userId);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return __('Unable to upload profile picture.');
        }

        $filename = 'avatar_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $prepared['ext'];
        $destination = $dir . '/' . $filename;
        if (!store_image_upload($prepared, $destination)) {
            return __('Unable to upload profile picture.');
        }

        $this->deleteAvatarFile($avatarPath);
        $avatarPath = 'storage/uploads/avatars/' . $userId . '/' . $filename;
        return null;
    }

    private function deleteAvatarFile(?string $path): void
    {
        if (!$path) {
            return;
        }
        $full = base_path($path);
        $real = realpath($full);
        $base = realpath(base_path('storage/uploads/avatars'));
        if ($real !== false && $base !== false && str_starts_with($real, $base) && file_exists($real)) {
            @unlink($real);
        }
    }

    public function avatar(int $id): void
    {
        Access::require(Access::avatar($id));

        if (!$this->avatarColumnExists()) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        $user = Database::fetch("SELECT avatar_path FROM users WHERE id = ? AND archived_at IS NULL", [$id]);
        if (!$user || empty($user['avatar_path'])) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }

        $full = base_path($user['avatar_path']);
        $real = realpath($full);
        $base = realpath(base_path('storage/uploads/avatars'));
        if ($real === false || $base === false || !str_starts_with($real, $base) || !file_exists($real)) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        header('Content-Type: ' . ($finfo->file($real) ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($real));
        header('Cache-Control: public, max-age=86400');
        readfile($real);
        exit;
    }
}
