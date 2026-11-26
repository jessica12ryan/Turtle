<?php

namespace App\Core;

class Auth
{
    private static ?Auth $instance = null;

    private function __construct()
    {
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public function user(): ?array
    {
        if (!$this->check()) {
            return null;
        }

        try {
            return Database::fetch(
                "SELECT id, name, email, phone, role, secondary_roles, avatar_path, theme, language, timezone, must_change_password, archived_at FROM users WHERE id = ? AND archived_at IS NULL",
                [$_SESSION['user_id']]
            );
        } catch (\Throwable $e) {
            // Older installs may not have run the avatar_path migration yet.
            return Database::fetch(
                "SELECT id, name, email, phone, role, secondary_roles, theme, language, timezone, must_change_password, archived_at FROM users WHERE id = ? AND archived_at IS NULL",
                [$_SESSION['user_id']]
            );
        }
    }

    public function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public function login(string $email, string $password): bool
    {
        $user = Database::fetch(
            "SELECT id, name, email, phone, password, role, language FROM users WHERE email = ? AND archived_at IS NULL",
            [$email]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        $user['password'] = self::rehashPasswordIfNeeded((int)$user['id'], $user['password'], $password);

        $_SESSION['user_id'] = (int) $user['id'];
        session_regenerate_id(true);

        // A token issued before authentication must not survive the privilege
        // change; issue a fresh one for the logged-in session.
        unset($_SESSION['_csrf_token']);
        \csrf_token();

        if (!empty($user['language'])) {
            $_SESSION['_language'] = $user['language'];
        }

        return true;
    }

    /**
     * Refresh a stored hash when PASSWORD_DEFAULT's cost/algorithm changed.
     * Best-effort: failures are logged and the old hash is kept so login works.
     */
    public static function rehashPasswordIfNeeded(int $userId, string $hash, string $password): string
    {
        if (!password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            return $hash;
        }

        $newHash = password_hash($password, PASSWORD_DEFAULT);
        try {
            Database::execute("UPDATE users SET password = ? WHERE id = ?", [$newHash, $userId]);
            return $newHash;
        } catch (\Throwable $e) {
            Logger::warning('Could not rehash password for user #' . $userId . ': ' . $e->getMessage());
            return $hash;
        }
    }

    public function logout(): void
    {
        unset($_SESSION['user_id'], $_SESSION['_csrf_token']);
        session_regenerate_id(true);
    }

    public function mustChangePassword(): bool
    {
        $user = $this->user();
        return $user && (bool) $user['must_change_password'];
    }

    public function isStaff(): bool
    {
        $user = $this->user();
        return $user && in_array($user['role'], ['admin', 'landlord', 'property_manager', 'maintenance']);
    }
}
