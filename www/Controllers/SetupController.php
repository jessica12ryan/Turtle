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
        $view->render('setup/create', compact('timezones', 'selectedTz', 'tzByCountry'));
    }

    public function store(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirect('/setup');
        }
        try {
            $admin = Database::fetch("SELECT id FROM users WHERE role = 'admin' AND archived_at IS NULL LIMIT 1");
            if ($admin) {
                if (Auth::instance()->check()) {
                    redirect('/home');
                }
                redirect('/login');
            }

            $validator = new Validator();
            if (
                !$validator->validate($_POST, [
                'name' => 'required|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:8|confirmed',
                ])
            ) {
                $_SESSION['_errors'] = $validator->errors();
                $_SESSION['_old'] = $_POST;
                redirect('/setup');
            }
        } catch (\Throwable $e) {
            \App\Core\Logger::error('SetupController::store (validation): ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            throw $e;
        }

        try {
            // Handle logo upload before seed (file must be uploaded early)
            $logoPath = '';
            $keepDefault = !empty($_POST['logo_default']);
            if (!$keepDefault && isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/png', 'image/jpeg', 'image/gif'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $type = finfo_file($finfo, $_FILES['logo']['tmp_name']);
                finfo_close($finfo);
                if (in_array($type, $allowed)) {
                    // Never trust the client-supplied extension: this file is
                    // written inside the web root and a polyglot uploaded as
                    // "logo.php" would be executed by Apache.
                    $extByMime = [
                        'image/png' => 'png',
                        'image/jpeg' => 'jpg',
                        'image/gif' => 'gif',
                    ];
                    $filename = 'logo.' . $extByMime[$type];
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
                "INSERT INTO users (name, email, password, role, theme, must_change_password, created_at, updated_at) VALUES (?, ?, ?, 'admin', 'system', 0, NOW(), NOW())",
                [$_POST['name'], $_POST['email'], password_hash($_POST['password'], PASSWORD_DEFAULT)]
            );

            // Optionally seed sample data
            if (!empty($_POST['load_sample_data'])) {
                $seedFile = base_path('database/seed.sql');
                if (file_exists($seedFile)) {
                    // Quote-aware splitting: semicolons inside sample text must
                    // not break statements. A failure aborts setup loudly.
                    \App\Core\Database::executeSql((string)file_get_contents($seedFile));
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

            // Save site URL
            $siteUrl = trim($_POST['site_url'] ?? '');
            Database::execute(
                "INSERT INTO settings (`key`, `value`) VALUES ('site_url', ?) ON DUPLICATE KEY UPDATE `value` = ?",
                [$siteUrl, $siteUrl]
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
            $country = $_POST['default_country'] ?? 'US';
            if (!in_array($country, ['CA', 'US'])) {
                $country = 'US';
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

            // Save telemetry preference (opt-out, default ON: checkbox absent => disabled)
            \App\Core\Telemetry::setEnabled(isset($_POST['telemetry_enabled']) && $_POST['telemetry_enabled'] === '1');

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
        } catch (\Throwable $e) {
            \App\Core\Logger::error('SetupController::store (execution): ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            throw $e; // Let the global handler render the 500 page
        }
    }

    public function restore(): void
    {
        // This endpoint exists for restoring a backup on a fresh install,
        // where there is no account to log in with yet. Once an admin exists it
        // must be disabled entirely: the restore drops every table, executes
        // SQL from the archive and replaces .env. Admins use Settings restore
        // instead, which never touches .env.
        $admin = Database::fetch("SELECT id FROM users WHERE role = 'admin' AND archived_at IS NULL LIMIT 1");
        if ($admin) {
            http_response_code(403);
            require base_path('www/Views/errors/403.php');
            return;
        }

        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirect('/setup');
        }
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            flash('error', __('Please select a backup file.'));
            redirect('/setup');
        }

        $tmpPath = $_FILES['backup_file']['tmp_name'];
        $origName = $_FILES['backup_file']['name'];

        // Validate extension
        if (!str_ends_with(strtolower($origName), '.turtle')) {
            flash('error', __('Invalid backup file. Expected a .turtle file.'));
            redirect('/setup');
        }

        $dataDir = base_path('storage/app/backups/');
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        $destPath = $dataDir . uniqid('restore_') . '.turtle';
        move_uploaded_file($tmpPath, $destPath);

        $zip = new \ZipArchive();
        if ($zip->open($destPath) !== true) {
            unlink($destPath);
            flash('error', __('Could not open backup archive.'));
            redirect('/setup');
        }

        // Extract SQL dump
        $sqlContent = $zip->getFromName('database.sql');
        if ($sqlContent === false) {
            $zip->close();
            unlink($destPath);
            flash('error', __('Backup file is missing database.sql.'));
            redirect('/setup');
        }

        // Extract .env
        $envContent = $zip->getFromName('.env');
        if ($envContent !== false) {
            $envPath = base_path('.env');
            file_put_contents($envPath, $envContent);
        }

        try {
            // Drop all tables
            $pdo = Database::instance()->getConnection();
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS `$table`");
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            // Quote-aware splitting; abort on the first failing statement.
            \App\Core\Database::executeSql($sqlContent);
        } catch (\Throwable $e) {
            $zip->close();
            unlink($destPath);
            \App\Core\Logger::error('Setup restore error: ' . $e->getMessage());
            flash('error', __('Database restore failed.') . ' ' . $e->getMessage());
            redirect('/setup');
        }

        $zip->close();
        unlink($destPath);
        flash('success', __('Backup restored successfully. Please log in with your restored credentials.'));
        redirect('/login');
    }
}
