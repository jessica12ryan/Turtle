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
        if (!in_array($tab, ['general', 'updates', 'permissions', 'backup', 'logging', 'reset'])) {
            $tab = 'general';
        }

        $data = ['tab' => $tab];

        if ($tab === 'general') {
            $keys = ['mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name', 'timezone', 'ntp_server', 'site_name', 'logo_path', 'default_country', 'default_language', 'openai_api_key'];
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
        } elseif ($tab === 'logging') {
            $row = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'log_level'");
            $data['logLevel'] = $row['value'] ?? 'debug';

            // Activity logs from DB
            $actionFilter = $_GET['action_filter'] ?? '';
            $actionFilterSql = $actionFilter ? ' WHERE action = ?' : '';
            $params = $actionFilter ? [$actionFilter] : [];
            $data['activityLogs'] = Database::fetchAll(
                "SELECT * FROM activity_logs{$actionFilterSql} ORDER BY created_at DESC LIMIT 100",
                $params
            );
            $data['activityActions'] = Database::fetchAll(
                "SELECT DISTINCT action FROM activity_logs ORDER BY action"
            );

            // PHP error log
            $phpLogPath = ini_get('error_log') ?: '';
            if ($phpLogPath === '' || !file_exists($phpLogPath)) {
                // HA add-on: PHP errors go to Apache stderr → /data/logs/apache_error.log
                $haApacheError = '/data/logs/apache_error.log';
                if (file_exists($haApacheError)) {
                    $phpLogPath = $haApacheError;
                }
            }
            if ($phpLogPath === '' || !file_exists($phpLogPath)) {
                $phpLogPath = '/var/log/php_errors.log';
            }
            $data['phpLog'] = [];
            if (file_exists($phpLogPath) && is_readable($phpLogPath)) {
                $lines = file($phpLogPath);
                $data['phpLog'] = array_slice($lines, -100);
            }
            $data['phpLogPath'] = $phpLogPath;

            // Apache logs
            $apachePaths = [
                '/var/log/apache2/access.log',
                '/var/log/apache2/error.log',
                '/var/log/httpd/access_log',
                '/var/log/httpd/error_log',
                '/data/logs/apache_error.log',
                '/data/logs/apache_access.log',
            ];
            $data['apacheLogs'] = [];
            foreach ($apachePaths as $ap) {
                if (file_exists($ap) && is_readable($ap)) {
                    $lines = file($ap);
                    $data['apacheLogs'][basename($ap)] = array_slice($lines, -100);
                }
            }

            // MySQL logs
            $mysqlLogPaths = [
                '/var/log/mysql/error.log',
                '/var/log/mysql/mysql.log',
                '/var/log/mariadb/mariadb.log',
            ];
            $data['mysqlLogs'] = [];
            foreach ($mysqlLogPaths as $mp) {
                if (file_exists($mp) && is_readable($mp)) {
                    $lines = file($mp);
                    $data['mysqlLogs'][basename($mp)] = array_slice($lines, -100);
                }
            }
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

        if (isset($_POST['openai_api_key'])) {
            $key = trim($_POST['openai_api_key']);
            Database::execute(
                "INSERT INTO settings (`key`, `value`) VALUES ('openai_api_key', ?) ON DUPLICATE KEY UPDATE `value` = ?",
                [$key, $key]
            );
        }

        log_activity('settings.general_saved', 'General settings saved');
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

        log_activity('settings.mail_saved', 'Mail settings saved');
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
            Database::execute("DELETE FROM property_photos WHERE 1=1", []);
            Database::execute("DELETE FROM activity_logs WHERE 1=1", []);
            // Reset all user-configurable settings (setup re-creates what it needs)
            $settingsKeys = ['site_name', 'logo_path', 'timezone', 'ntp_server', 'default_country', 'default_language', 'openai_api_key', 'log_level', 'mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name'];
            $placeholders = implode(',', array_fill(0, count($settingsKeys), '?'));
            Database::execute("DELETE FROM settings WHERE `key` IN ({$placeholders})", $settingsKeys);
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

            redirect('/setup');
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

        log_activity('settings.data_reset', 'Data reset completed');
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

        log_activity('settings.permissions_saved', 'Permissions saved');
        flash('success', 'Permissions saved successfully.');
        redirect('/settings?tab=permissions');
    }

    public function saveLogging(): void
    {
        if (!isset($_POST['_csrf']) || !verify_csrf($_POST['_csrf'])) {
            flash('error', 'Invalid security token.');
            redirect('/settings?tab=logging');
        }

        $level = $_POST['log_level'] ?? 'debug';
        if (!in_array($level, ['debug', 'info', 'notice', 'warning', 'error'])) {
            $level = 'debug';
        }

        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES ('log_level', ?) ON DUPLICATE KEY UPDATE `value` = ?",
            [$level, $level]
        );

        log_activity('settings.logging_saved', "Log level changed to {$level}");
        flash('success', 'Logging settings saved successfully.');
        redirect('/settings?tab=logging');
    }

    public function downloadLogs(string $type = 'php'): void
    {
        $content = '';
        $filename = 'turtle-logs.txt';

        switch ($type) {
            case 'activity':
                $rows = Database::fetchAll("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 1000");
                $content = "Action\tUser\tDescription\tTime\n";
                foreach ($rows as $r) {
                    $content .= "{$r['action']}\t{$r['user_name']}\t{$r['description']}\t{$r['created_at']}\n";
                }
                $filename = 'activity-logs.tsv';
                break;

            case 'php':
                $logPath = ini_get('error_log') ?: '';
                if ($logPath === '' || !file_exists($logPath)) {
                    $haApacheError = '/data/logs/apache_error.log';
                    if (file_exists($haApacheError)) {
                        $logPath = $haApacheError;
                    }
                }
                if ($logPath === '' || !file_exists($logPath)) {
                    $logPath = '/var/log/php_errors.log';
                }
                if (file_exists($logPath) && is_readable($logPath)) {
                    $content = file_get_contents($logPath);
                }
                $filename = 'php-error.log';
                break;

            case 'apache':
                $paths = [
                    '/var/log/apache2/access.log',
                    '/var/log/apache2/error.log',
                    '/var/log/httpd/access_log',
                    '/var/log/httpd/error_log',
                    '/data/logs/apache_error.log',
                    '/data/logs/apache_access.log',
                ];
                foreach ($paths as $p) {
                    if (file_exists($p) && is_readable($p)) {
                        $content .= "=== $p ===\n\n";
                        $content .= file_get_contents($p) . "\n\n";
                    }
                }
                $filename = 'apache-logs.txt';
                break;

            case 'mysql':
                $paths = [
                    '/var/log/mysql/error.log',
                    '/var/log/mysql/mysql.log',
                    '/var/log/mariadb/mariadb.log',
                ];
                foreach ($paths as $p) {
                    if (file_exists($p) && is_readable($p)) {
                        $content .= "=== $p ===\n\n";
                        $content .= file_get_contents($p) . "\n\n";
                    }
                }
                $filename = 'mysql-logs.txt';
                break;

            default:
                flash('error', 'Unknown log type.');
                redirect('/settings?tab=logging');
        }

        if (empty($content)) {
            flash('error', 'No log content found.');
            redirect('/settings?tab=logging');
        }

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $content;
        exit;
    }

    public function exportBackup(): void
    {
        set_time_limit(0);

        if (!isset($_POST['_csrf']) || !verify_csrf($_POST['_csrf'])) {
            flash('error', 'Invalid security token.');
            redirect('/settings');
        }

        $db = \App\Core\Database::getConnection();

        // Collect all table names
        $tables = $db->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);

        // Build SQL dump
        $sql = "-- Turtle Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $table) {
            // CREATE TABLE
            $stmt = $db->query("SHOW CREATE TABLE `{$table}`");
            $row = $stmt->fetch(\PDO::FETCH_NUM);
            $createSql = $row[1];
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sql .= "{$createSql};\n\n";

            // Data
            $rows = $db->query("SELECT * FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($rows)) continue;

            $columns = array_keys($rows[0]);
            $colList = '`' . implode('`,`', $columns) . '`';

            foreach ($rows as $row) {
                $vals = [];
                foreach ($columns as $col) {
                    $v = $row[$col];
                    if ($v === null) {
                        $vals[] = 'NULL';
                    } else {
                        $vals[] = $db->quote($v);
                    }
                }
                $sql .= "INSERT INTO `{$table}` ({$colList}) VALUES (" . implode(',', $vals) . ");\n";
            }
            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        // Create temp dir for the backup
        $tmpDir = sys_get_temp_dir() . '/turtle_backup_' . bin2hex(random_bytes(8));
        mkdir($tmpDir, 0755, true);

        // Write SQL
        file_put_contents("{$tmpDir}/database.sql", $sql);

        // Include .env for exact system replication (db creds, app key, mail settings, etc.)
        $envFile = base_path('.env');
        if (file_exists($envFile)) {
            copy($envFile, "{$tmpDir}/.env");
        }

        // Collect uploaded files
        $uploadDirs = [
            'www/assets/uploads/logo' => 'uploads/logo',
            'storage/uploads/property_photos' => 'uploads/property_photos',
            'storage/uploads/leases' => 'uploads/leases',
        ];

        foreach ($uploadDirs as $srcRel => $destRel) {
            $src = realpath(base_path($srcRel));
            if ($src === false || !is_dir($src)) continue;
            $destDir = "{$tmpDir}/{$destRel}";
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            $files = new \FilesystemIterator($src, \FilesystemIterator::SKIP_DOTS);
            foreach ($files as $file) {
                if ($file->isFile()) {
                    copy($file->getRealPath(), $destDir . '/' . $file->getFilename());
                }
            }
        }

        // Check ZipArchive availability
        if (!class_exists('\ZipArchive')) {
            self::_rrmdir($tmpDir);
            flash('error', 'ZipArchive PHP extension is required for backup.');
            redirect('/settings?tab=backup');
        }

        // Create zip archive
        $zipFile = sys_get_temp_dir() . '/turtle_backup_' . bin2hex(random_bytes(8)) . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::CREATE) !== true) {
            self::_rrmdir($tmpDir);
            flash('error', 'Failed to create backup archive.');
            redirect('/settings?tab=backup');
        }

        // Add files to zip
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tmpDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            $localPath = substr($file->getPathname(), strlen($tmpDir) + 1);
            $zip->addFile($file->getPathname(), $localPath);
        }

        $zip->close();

        // Send zip as download with .turtle extension
        $date = date('ymd');
        $fileSize = filesize($zipFile);
        if ($fileSize === false) {
            self::_rrmdir($tmpDir);
            flash('error', 'Failed to read backup archive.');
            redirect('/settings?tab=backup');
        }

        // Clear any output buffering so the file download isn't corrupted
        while (ob_get_level()) ob_end_clean();

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="turtle-bak-' . $date . '.turtle"');
        header('Content-Length: ' . $fileSize);
        header('Pragma: public');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');

        // Stream file in chunks
        $fh = fopen($zipFile, 'rb');
        if ($fh) {
            while (!feof($fh)) {
                echo fread($fh, 8192);
                flush();
            }
            fclose($fh);
        }

        // Clean up
        unlink($zipFile);
        self::_rrmdir($tmpDir);

        log_activity('settings.backup_created', 'Full backup downloaded');
        exit;
    }

    public function importRestore(): void
    {
        set_time_limit(0);

        if (!isset($_POST['_csrf']) || !verify_csrf($_POST['_csrf'])) {
            flash('error', 'Invalid security token.');
            redirect('/settings');
        }

        // Validate upload
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'No backup file uploaded or upload failed.');
            redirect('/settings?tab=backup');
        }

        $ext = pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION);
        if ($ext !== 'turtle') {
            flash('error', 'Invalid file format. Please upload a .turtle backup file.');
            redirect('/settings?tab=backup');
        }

        // Extract to temp dir
        $tmpDir = sys_get_temp_dir() . '/turtle_restore_' . bin2hex(random_bytes(8));
        mkdir($tmpDir, 0755, true);

        $zipPath = $_FILES['backup_file']['tmp_name'];
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            self::_rrmdir($tmpDir);
            flash('error', 'Failed to open backup file. It may be corrupted.');
            redirect('/settings?tab=backup');
        }

        $zip->extractTo($tmpDir);
        $zip->close();

        // Check for database.sql
        $sqlFile = "{$tmpDir}/database.sql";
        if (!file_exists($sqlFile)) {
            self::_rrmdir($tmpDir);
            flash('error', 'Invalid backup file: database.sql not found.');
            redirect('/settings?tab=backup');
        }

        $db = \App\Core\Database::getConnection();

        try {
            // Disable foreign key checks (SET and DDL can't be in a transaction)
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");

            // Drop all existing tables so the backup SQL fully replaces everything
            $existingTables = $db->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($existingTables as $table) {
                $db->exec("DROP TABLE IF EXISTS `{$table}`");
            }

            // Execute backup SQL — generated with one statement per line ending in ;\n
            $sql = file_get_contents($sqlFile);
            $statements = explode(";\n", $sql);
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '' || str_starts_with($stmt, '--') || str_starts_with($stmt, '#')) continue;
                $db->exec($stmt);
            }

            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
        } catch (\Throwable $e) {
            self::_rrmdir($tmpDir);
            flash('error', 'Database restore failed: ' . $e->getMessage());
            redirect('/settings?tab=backup');
        }

        // Restore .env if included in backup (exact system replication)
        $envBak = "{$tmpDir}/.env";
        $envDest = base_path('.env');
        if (file_exists($envBak) && is_writable(dirname($envDest))) {
            copy($envBak, $envDest);
        }

        // Restore uploaded files
        $uploadMappings = [
            'uploads/logo' => 'www/assets/uploads/logo',
            'uploads/property_photos' => 'storage/uploads/property_photos',
            'uploads/leases' => 'storage/uploads/leases',
        ];

        foreach ($uploadMappings as $srcRel => $destRel) {
            $srcDir = "{$tmpDir}/{$srcRel}";
            if (!is_dir($srcDir)) continue;

            $destDir = base_path($destRel);
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }

            $files = glob($srcDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $target = $destDir . '/' . basename($file);
                    copy($file, $target);
                }
            }
        }

        // Clean up temp directory
        self::_rrmdir($tmpDir);

        log_activity('settings.restore_completed', 'Full backup restored from ' . $_FILES['backup_file']['name']);

        // Log the user out and redirect to login — everything changed
        flash('success', 'Backup restored successfully. Please log in with your restored admin account.');
        $auth = \App\Core\Auth::instance();
        if ($auth->check()) {
            $auth->logout();
        }
        redirect('/login');
    }

    private static function _rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }
}
