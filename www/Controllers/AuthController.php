<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Core\Validator;
use App\Core\Mailer;

class AuthController
{
    public function login(): void
    {
        $view = new View();
        $view->layout('layouts/guest', ['title' => 'Login']);
        $view->render('auth/login');
    }

    public function loginPost(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', 'Invalid form token.');
            redirect('/login');
        }

        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'email' => 'required|email',
            'password' => 'required',
        ])) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_errors'] = $validator->errors();
            redirect('/login');
        }

        $auth = Auth::instance();
        if (!$auth->login($_POST['email'], $_POST['password'])) {
            flash('error', 'Invalid email or password.');
            redirect('/login');
        }

        if ($auth->mustChangePassword()) {
            redirect('/password/change');
        }

        redirect('/dashboard');
    }

    public function logout(): void
    {
        Auth::instance()->logout();
        redirect('/login');
    }

    public function forgotPassword(): void
    {
        $view = new View();
        $view->layout('layouts/guest', ['title' => 'Forgot Password']);
        $view->render('auth/forgot-password');
    }

    public function forgotPasswordPost(): void
    {
        $validator = new Validator();
        if (!$validator->validate($_POST, ['email' => 'required|email'])) {
            $_SESSION['_errors'] = $validator->errors();
            redirect('/forgot-password');
        }

        $user = Database::fetch("SELECT id FROM users WHERE email = ? AND archived_at IS NULL", [$_POST['email']]);

        if ($user) {
            $token = bin2hex(random_bytes(32));
            Database::execute(
                "DELETE FROM password_reset_tokens WHERE email = ?",
                [$_POST['email']]
            );
            Database::execute(
                "INSERT INTO password_reset_tokens (email, token, created_at) VALUES (?, ?, NOW())",
                [$_POST['email'], $token]
            );

            $resetUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/reset-password/' . $token;
            Mailer::sendTemplate(
                $_POST['email'],
                'Reset Your Password',
                'Hello,',
                'We received a request to reset your password. Click the button below to choose a new one.',
                $resetUrl,
                'Reset Password'
            );
        }

        flash('success', 'If that email exists, a reset link has been sent.');
        redirect('/login');
    }

    public function resetPassword(string $token): void
    {
        $record = Database::fetch(
            "SELECT email, created_at FROM password_reset_tokens WHERE token = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$token]
        );

        if (!$record) {
            flash('error', 'Invalid or expired reset link.');
            redirect('/login');
        }

        $view = new View();
        $view->layout('layouts/guest', ['title' => 'Reset Password']);
        $view->render('auth/reset-password', ['token' => $token, 'email' => $record['email']]);
    }

    public function resetPasswordPost(): void
    {
        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ])) {
            $_SESSION['_errors'] = $validator->errors();
            redirect('/reset-password/' . $_POST['token']);
        }

        $record = Database::fetch(
            "SELECT email FROM password_reset_tokens WHERE token = ? AND email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$_POST['token'], $_POST['email']]
        );

        if (!$record) {
            flash('error', 'Invalid or expired reset link.');
            redirect('/login');
        }

        Database::execute(
            "UPDATE users SET password = ?, must_change_password = 0 WHERE email = ?",
            [password_hash($_POST['password'], PASSWORD_DEFAULT), $_POST['email']]
        );
        Database::execute("DELETE FROM password_reset_tokens WHERE email = ?", [$_POST['email']]);

        flash('success', 'Password reset successfully. Please log in.');
        redirect('/login');
    }

    public function onboarding(): void
    {
        $view = new View();
        $view->layout('layouts/guest', ['title' => 'Tenant Onboarding']);
        $view->render('auth/onboarding');
    }

    public function onboardingPost(): void
    {
        $validator = new Validator();
        if (!$validator->validate($_POST, ['email' => 'required|email'])) {
            $_SESSION['_errors'] = $validator->errors();
            redirect('/onboarding');
        }

        $user = Database::fetch(
            "SELECT id, name, must_change_password FROM users WHERE email = ? AND role = 'tenant' AND archived_at IS NULL",
            [$_POST['email']]
        );

        if (!$user) {
            flash('error', 'No tenant account found with that email.');
            redirect('/onboarding');
        }

        if (!$user['must_change_password']) {
            flash('error', 'This account has already been onboarded. Please use the Forgot Password link if you need to reset your password.');
            redirect('/onboarding');
        }

        $password = bin2hex(random_bytes(6));
        Database::execute(
            "UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?",
            [password_hash($password, PASSWORD_DEFAULT), $user['id']]
        );

        $loginUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/login';
        Mailer::sendTemplate(
            $_POST['email'],
            'Welcome to Turtle - Complete Your Onboarding',
            'Hello ' . h($user['name']) . ',',
            'Your landlord has invited you to the Turtle Tenant Management Portal.<br><br><strong>Your temporary password is: ' . $password . '</strong><br><br>Please log in with your email and this temporary password. You will be prompted to set a new password.',
            $loginUrl,
            'Log In'
        );

        flash('success', 'Welcome email sent! Please check your inbox for your temporary password.');
        redirect('/login');
    }

    public function changePassword(): void
    {
        $view = new View();
        $view->layout('layouts/guest', ['title' => 'Change Password']);
        $view->render('auth/change-password');
    }

    public function changePasswordPost(): void
    {
        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'password' => 'required|min:8|confirmed',
        ])) {
            $_SESSION['_errors'] = $validator->errors();
            redirect('/password/change');
        }

        Database::execute(
            "UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?",
            [password_hash($_POST['password'], PASSWORD_DEFAULT), Auth::instance()->id()]
        );

        flash('success', 'Password changed successfully.');
        redirect('/dashboard');
    }
}
