<?php

namespace App\Core;

class Auth
{
    private static ?Auth $instance = null;

    private function __construct() {}

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
        if (!$this->check()) return null;

        return Database::fetch(
            "SELECT * FROM users WHERE id = ? AND archived_at IS NULL",
            [$_SESSION['user_id']]
        );
    }

    public function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public function login(string $email, string $password): bool
    {
        $user = Database::fetch(
            "SELECT * FROM users WHERE email = ? AND archived_at IS NULL",
            [$email]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        $_SESSION['user_id'] = (int) $user['id'];
        session_regenerate_id(true);

        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['user_id']);
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
