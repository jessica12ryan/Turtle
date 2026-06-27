<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

class SettingsController
{
    public function index(): void
    {
        $tab = $_GET['tab'] ?? 'general';
        if (!in_array($tab, ['general', 'updates', 'permissions', 'reset'])) {
            $tab = 'general';
        }

        $data = ['tab' => $tab];

        if ($tab === 'general') {
            $keys = ['mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name', 'timezone', 'ntp_server', 'site_name', 'logo_path', 'default_country', 'default_language'];
            $rows = Database::fetchAll("SELECT `key`, `value` FROM settings WHERE `key` IN ('" . implode("','", $keys) . "')");
            $data['mail'] = [];
            foreach ($rows as $row) {
                $data['mail'][$row['key']] = $row['value'];
            }
            foreach ($keys as $k) {
                if (!isset($data['mail'][$k])) {
                    $data['mail'][$k] = '';
                }
            }

            $tz = $data['mail']['timezone'] ?: 'America/New_York';
            $data['timezones'] = \DateTimeZone::listIdentifiers();
            $data['selectedTz'] = $tz;
            $data['siteName'] = $data['mail']['site_name'] ?: 'Turtle';

            $tzByCountry = [];
            foreach (['CA', 'US'] as $c) {
                $tzByCountry[$c] = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, $c);
            }
            $tzByCountry['generic'] = \DateTimeZone::listIdentifiers(\DateTimeZone::UTC);
            $data['tzByCountry'] = $tzByCountry;
        } elseif ($tab === 'updates') {
            $currentVersion = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'app_version'");
            $latestVersion = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'latest_version'");
            $lastCheck = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'last_update_check'");
            $channel = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'update_channel'");

            $data['currentVersion'] = $currentVersion['value'] ?? '0.0.0';
            $data['latestVersion'] = $latestVersion['value'] ?? '';
            $data['lastCheck'] = $lastCheck['value'] ?? '';
            $data['channel'] = $channel['value'] ?? 'stable';
        } elseif ($tab === 'permissions') {
            $rows = Database::fetchAll("SELECT role, permission FROM role_permissions ORDER BY role, permission", []);
            $data['overrides'] = [];
            foreach ($rows as $row) {
                $data['overrides'][$row['role']][] = $row['permission'];
            }
            $data['roles'] = ['landlord', 'property_manager', 'maintenance', 'tenant'];
            $data['defaults'] = defaultPermissions();
            $row = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'permissions_mode'");
            $data['permissionsMode'] = $row['value'] ?? 'default';
        }

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Settings']);
        $view->render('settings/index', $data);
    }

    public function saveGeneral(): void
    {
        if (!isset($_POST['_csrf']) || !verify_csrf($_POST['_csrf'])) {
            flash('error', 'Invalid security token.');
            redirect('/settings?tab=general');
        }

        $allowedTz = \DateTimeZone::listIdentifiers();
        $tz = $_POST['timezone'] ?? '';
        if (!in_array($tz, $allowedTz)) {
            $tz = 'America/New_York';
        }

        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES ('timezone', ?) ON DUPLICATE KEY UPDATE `value` = ?",
            [$tz, $tz]
        );

        $ntpServer = $_POST['ntp_server'] ?? 'time.gov';
        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES ('ntp_server', ?) ON DUPLICATE KEY UPDATE `value` = ?",
            [$ntpServer, $ntpServer]
        );

        $siteName = trim($_POST['site_name'] ?? '');
        if ($siteName === '') {
            $siteName = 'Turtle';
        }
        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES ('site_name', ?) ON DUPLICATE KEY UPDATE `value` = ?",
            [$siteName, $siteName]
        );

        $keepDefault = !empty($_POST['logo_default']);
        if ($keepDefault) {
            Database::execute(
                "INSERT INTO settings (`key`, `value`) VALUES ('logo_path', '') ON DUPLICATE KEY UPDATE `value` = ''",
                []
            );
        } elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml'];
            $type = $_FILES['logo']['type'];
            if (!in_array($type, $allowed)) {
                flash('error', 'Logo must be PNG, JPEG, GIF, or SVG.');
                redirect('/settings?tab=general');
            }

            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $filename = 'logo.' . $ext;
            $uploadDir = base_path('www/assets/uploads/logo/');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $dest = $uploadDir . $filename;

            if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                Database::execute(
                    "INSERT INTO settings (`key`, `value`) VALUES ('logo_path', ?) ON DUPLICATE KEY UPDATE `value` = ?",
                    ['assets/uploads/logo/' . $filename, 'assets/uploads/logo/' . $filename]
                );
            } else {
                flash('error', 'Failed to upload logo. Check directory permissions.');
                redirect('/settings?tab=general');
            }
        }

        if (isset($_POST['default_country'])) {
            $country = $_POST['default_country'] === 'US' ? 'US' : 'CA';
            Database::execute(
                "INSERT INTO settings (`key`, `value`) VALUES ('default_country', ?) ON DUPLICATE KEY UPDATE `value` = ?",
                [$country, $country]
            );
        }

        if (isset($_POST['default_language'])) {
            $lang = in_array($_POST['default_language'], ['en', 'fr', 'es']) ? $_POST['default_language'] : 'en';
            Database::execute(
                "INSERT INTO settings (`key`, `value`) VALUES ('default_language', ?) ON DUPLICATE KEY UPDATE `value` = ?",
                [$lang, $lang]
            );
        }

        flash('success', 'General settings saved successfully.');
        redirect('/settings?tab=general');
    }

    public function saveMail(): void
    {
        if (!isset($_POST['_csrf']) || !verify_csrf($_POST['_csrf'])) {
            flash('error', 'Invalid security token.');
            redirect('/settings?tab=general');
        }

        $fields = [
            'mail_host' => 'required|max:255',
            'mail_port' => 'required|numeric|max:65535',
            'mail_username' => 'max:255',
            'mail_password' => 'max:255',
            'mail_from_address' => 'required|max:255',
            'mail_from_name' => 'required|max:255',
        ];

        $validator = new \App\Core\Validator();
        if (!$validator->validate($_POST, $fields)) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/settings?tab=general');
        }

        $keys = ['mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name'];
        foreach ($keys as $key) {
            $value = $_POST[$key] ?? '';
            Database::execute(
                "INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?",
                [$key, $value, $value]
            );
        }

        flash('success', 'Mail settings saved successfully.');
        redirect('/settings?tab=general');
    }

    public function reset(): void
    {
        if (!isset($_POST['_csrf']) || !verify_csrf($_POST['_csrf'])) {
            flash('error', 'Invalid security token.');
            redirect('/settings');
        }

        $admin = Auth::instance()->user();
        $resetAll = !empty($_POST['reset_all']);

        if ($resetAll) {
            Database::execute("DELETE FROM documents WHERE 1=1", []);
            Database::execute("DELETE FROM ticket_comments WHERE 1=1", []);
            Database::execute("DELETE FROM tickets WHERE 1=1", []);
            Database::execute("DELETE FROM leases WHERE 1=1", []);
            Database::execute("DELETE FROM property_tenant WHERE 1=1", []);
            Database::execute("DELETE FROM properties WHERE 1=1", []);
            Database::execute("DELETE FROM company_user WHERE 1=1", []);
            Database::execute("DELETE FROM companies WHERE 1=1", []);
            Database::execute("DELETE FROM resources WHERE 1=1", []);
            Database::execute("DELETE FROM notifications WHERE 1=1", []);
            Database::execute("DELETE FROM password_reset_tokens WHERE 1=1", []);
            Database::execute("DELETE FROM sessions WHERE 1=1", []);
            // Reset general settings to defaults (setup re-creates site_name, logo, timezone, ntp, mail)
            $generalKeys = ['site_name', 'logo_path', 'timezone', 'ntp_server', 'default_language', 'mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name'];
            $placeholders = implode(',', array_fill(0, count($generalKeys), '?'));
            Database::execute("DELETE FROM settings WHERE `key` IN ({$placeholders})", $generalKeys);
            // Reset permissions to "Use defaults"
            Database::execute(
                "INSERT INTO settings (`key`, `value`) VALUES ('permissions_mode', 'default') ON DUPLICATE KEY UPDATE `value` = 'default'",
                []
            );
            Database::execute("DELETE FROM role_permissions WHERE 1=1", []);

            Database::execute("DELETE FROM users WHERE 1=1", []);

            // Remove uploaded logo from filesystem
            $logoDir = base_path('www/assets/uploads/logo/');
            if (is_dir($logoDir)) {
                array_map('unlink', glob($logoDir . '*'));
                rmdir($logoDir);
            }

            session_unset();
            session_destroy();
            $_SESSION = [];

            header('Location: /setup');
            exit;
        }

        if (!empty($_POST['reset_leases'])) {
            $leases = Database::fetchAll("SELECT id FROM leases WHERE archived_at IS NULL", []);
            foreach ($leases as $lease) {
                $docs = Database::fetchAll("SELECT file_path FROM documents WHERE documentable_type = 'lease' AND documentable_id = ?", [$lease['id']]);
                foreach ($docs as $doc) {
                    $path = $doc['file_path'];
                    $fullPath = str_starts_with($path, '/') ? $path : base_path($path);
                    if (file_exists($fullPath)) unlink($fullPath);
                }
                Database::execute("DELETE FROM documents WHERE documentable_type = 'lease' AND documentable_id = ?", [$lease['id']]);
            }
            Database::execute("DELETE FROM leases WHERE 1=1", []);
        }

        if (!empty($_POST['reset_tickets'])) {
            Database::execute("DELETE FROM ticket_comments WHERE 1=1", []);
            Database::execute("DELETE FROM tickets WHERE 1=1", []);
        }

        if (!empty($_POST['reset_tenants'])) {
            $tenantIds = Database::fetchAll("SELECT id FROM users WHERE role = 'tenant' AND archived_at IS NULL", []);
            $idList = array_column($tenantIds, 'id');
            if (!empty($idList)) {
                $idStr = implode(',', $idList);
                Database::execute("DELETE FROM property_tenant WHERE tenant_id IN ({$idStr})", []);
                Database::execute("DELETE FROM users WHERE id IN ({$idStr})", []);
            }
        }

        if (!empty($_POST['reset_properties'])) {
            Database::execute("DELETE FROM property_tenant WHERE 1=1", []);
            Database::execute("DELETE FROM properties WHERE 1=1", []);
        }

        if (!empty($_POST['reset_staff'])) {
            $staffIds = Database::fetchAll(
                "SELECT id FROM users WHERE role IN ('landlord','property_manager','maintenance') AND id != ? AND archived_at IS NULL",
                [$admin['id']]
            );
            $idList = array_column($staffIds, 'id');
            if (!empty($idList)) {
                $idStr = implode(',', $idList);
                Database::execute("DELETE FROM company_user WHERE user_id IN ({$idStr})", []);
                Database::execute("DELETE FROM users WHERE id IN ({$idStr})", []);
            }
        }

        if (!empty($_POST['reset_resources'])) {
            Database::execute("DELETE FROM resources WHERE 1=1", []);
        }

        flash('success', 'Reset completed successfully.');
        redirect('/settings');
    }

    public function testMail(): void
    {
        header('Content-Type: application/json');

        if (!isset($_POST['_csrf']) || !verify_csrf($_POST['_csrf'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid security token.']);
            return;
        }

        $user = Auth::instance()->user();
        $to = $user['email'] ?? '';

        if (!$to) {
            echo json_encode(['error' => 'No email address on your account.']);
            return;
        }

        try {
            $sent = \App\Core\Mailer::send(
                $to,
                'Test Email from Turtle',
                '<p>This is a test email sent from the Turtle settings page.</p><p>If you received this, your SMTP configuration is working correctly.</p>'
            );
            if ($sent) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'SMTP server rejected the connection. Check the error log for details.']);
            }
        } catch (\Throwable $e) {
            echo json_encode(['error' => 'Exception: ' . $e->getMessage()]);
        }
    }

    public function setUpdateChannel(): void
    {
        if (!isset($_POST['_csrf']) || !verify_csrf($_POST['_csrf'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid security token.']);
            return;
        }

        $channel = $_POST['channel'] ?? 'stable';
        if (!in_array($channel, ['stable', 'development'])) {
            $channel = 'stable';
        }

        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES ('update_channel', ?) ON DUPLICATE KEY UPDATE `value` = ?",
            [$channel, $channel]
        );

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'channel' => $channel]);
    }

    public function savePermissions(): void
    {
        if (!isset($_POST['_csrf']) || !verify_csrf($_POST['_csrf'])) {
            flash('error', 'Invalid security token.');
            redirect('/settings?tab=permissions');
        }

        $mode = $_POST['permissions_mode'] ?? 'default';
        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES ('permissions_mode', ?) ON DUPLICATE KEY UPDATE `value` = ?",
            [$mode, $mode]
        );

        if ($mode === 'custom') {
            if (isset($_POST['perms'])) {
                $roles = ['landlord', 'property_manager', 'maintenance', 'tenant'];
                Database::execute("DELETE FROM role_permissions WHERE 1=1", []);
                foreach ($roles as $role) {
                    $granted = $_POST['perms'][$role] ?? [];
                    foreach ($granted as $perm) {
                        Database::execute("INSERT INTO role_permissions (role, permission) VALUES (?, ?)", [$role, $perm]);
                    }
                }
            } else {
                $row = Database::fetch("SELECT COUNT(*) as c FROM role_permissions");
                if ($row && $row['c'] == 0) {
                    $defaults = defaultPermissions();
                    foreach ($defaults as $role => $perms) {
                        foreach ($perms as $perm) {
                            Database::execute("INSERT IGNORE INTO role_permissions (role, permission) VALUES (?, ?)", [$role, $perm]);
                        }
                    }
                }
            }
        } else {
            Database::execute("DELETE FROM role_permissions WHERE 1=1", []);
        }

        flash('success', 'Permissions saved successfully.');
        redirect('/settings?tab=permissions');
    }
}
