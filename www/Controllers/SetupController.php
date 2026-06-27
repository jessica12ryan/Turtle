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
        $admin = Database::fetch("SELECT id FROM users WHERE role = 'admin' AND archived_at IS NULL LIMIT 1");
        if ($admin) {
            if (Auth::instance()->check()) {
                redirect('/home');
            }
            redirect('/login');
        }

        $timezones = \DateTimeZone::listIdentifiers();
        $selectedTz = 'America/New_York';

        $tzByCountry = [];
        foreach (['CA', 'US'] as $c) {
            $tzByCountry[$c] = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, $c);
        }
        $tzByCountry['generic'] = \DateTimeZone::listIdentifiers(\DateTimeZone::UTC);

        $view = new View();
        $view->layout('layouts/guest', ['title' => 'Setup']);
        $view->render('setup/create', compact('timezones', 'selectedTz', 'tzByCountry', 'languages'));
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

        // Handle logo upload before seed (file must be uploaded early)
        $logoPath = '';
        $keepDefault = !empty($_POST['logo_default']);
        if (!$keepDefault && isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml'];
            $type = $_FILES['logo']['type'];
            if (in_array($type, $allowed)) {
                $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $filename = 'logo.' . $ext;
                $uploadDir = base_path('www/assets/uploads/logo/');
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $filename)) {
                    $logoPath = 'assets/uploads/logo/' . $filename;
                }
            }
        }

        // Create admin account
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

        // Save site name (after seed to prevent seed data from overwriting)
        $siteName = trim($_POST['site_name'] ?? '');
        if ($siteName === '') {
            $siteName = 'Turtle';
        }
        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES ('site_name', ?) ON DUPLICATE KEY UPDATE `value` = ?",
            [$siteName, $siteName]
        );

        // Save logo path
        if ($keepDefault) {
            Database::execute(
                "INSERT INTO settings (`key`, `value`) VALUES ('logo_path', '') ON DUPLICATE KEY UPDATE `value` = ''",
                []
            );
        } elseif ($logoPath !== '') {
            Database::execute(
                "INSERT INTO settings (`key`, `value`) VALUES ('logo_path', ?) ON DUPLICATE KEY UPDATE `value` = ?",
                [$logoPath, $logoPath]
            );
        }

        // Save timezone
        $allowedTz = \DateTimeZone::listIdentifiers();
        $tz = $_POST['timezone'] ?? '';
        if (!in_array($tz, $allowedTz)) {
            $tz = 'America/New_York';
        }
        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES ('timezone', ?) ON DUPLICATE KEY UPDATE `value` = ?",
            [$tz, $tz]
        );

        // Save default country
        $country = $_POST['default_country'] ?? 'CA';
        if (!in_array($country, ['CA', 'US'])) {
            $country = 'CA';
        }
        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES ('default_country', ?) ON DUPLICATE KEY UPDATE `value` = ?",
            [$country, $country]
        );

        // Save NTP server
        $ntpServer = trim($_POST['ntp_server'] ?? '');
        if ($ntpServer === '') {
            $ntpServer = 'time.gov';
        }
        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES ('ntp_server', ?) ON DUPLICATE KEY UPDATE `value` = ?",
            [$ntpServer, $ntpServer]
        );

        // Save default language
        $lang = $_POST['default_language'] ?? 'en';
        if (!in_array($lang, ['en', 'fr', 'es'])) {
            $lang = 'en';
        }
        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES ('default_language', ?) ON DUPLICATE KEY UPDATE `value` = ?",
            [$lang, $lang]
        );

        // Save mail settings
        $mailKeys = ['mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name'];
        foreach ($mailKeys as $key) {
            $value = $_POST[$key] ?? '';
            Database::execute(
                "INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?",
                [$key, $value, $value]
            );
        }

        Auth::instance()->login($_POST['email'], $_POST['password']);
        redirect('/home');
    }
}
