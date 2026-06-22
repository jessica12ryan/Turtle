<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/functions.php';

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, '"\'');
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect root
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($requestUri === '/' || $requestUri === '/index.php') {
    if (\App\Core\Auth::instance()->check()) {
        header('Location: /home');
    } else {
        header('Location: /login');
    }
    exit;
}

// Set timezone from settings
try {
    $tzSetting = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'timezone'");
    if ($tzSetting && $tzSetting['value']) {
        date_default_timezone_set($tzSetting['value']);
    }
} catch (\Throwable $e) {}

// Process scheduled move-outs (limit: once per hour)
try {
    $lastCheck = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'last_moveout_check'");
    $lastTs = $lastCheck['value'] ?? '';
    $now = date('Y-m-d H:i:s');
    if (!$lastTs || (strtotime($now) - strtotime($lastTs)) > 3600) {
        $expired = \App\Core\Database::fetchAll(
            "SELECT pt.* FROM property_tenant pt 
             WHERE pt.move_out_date IS NOT NULL 
             AND pt.move_out_date <= CURDATE() 
             AND pt.moved_out_at IS NULL"
        );
        foreach ($expired as $pt) {
            \App\Core\Database::execute(
                "UPDATE property_tenant SET moved_out_at = NOW(), updated_at = NOW() WHERE id = ? AND moved_out_at IS NULL",
                [$pt['id']]
            );
            \App\Core\Database::execute(
                "UPDATE users SET archived_at = NOW() WHERE id = ? AND archived_at IS NULL",
                [$pt['tenant_id']]
            );
            \App\Core\Database::execute(
                "UPDATE leases SET archived_at = NOW() WHERE tenant_id = ? AND archived_at IS NULL",
                [$pt['tenant_id']]
            );
            // If main tenant, cascade to secondary tenants
            if (!empty($pt['is_main_tenant'])) {
                $secondaries = \App\Core\Database::fetchAll(
                    "SELECT tenant_id FROM property_tenant 
                     WHERE property_id = ? AND tenant_id != ? AND moved_out_at IS NULL",
                    [$pt['property_id'], $pt['tenant_id']]
                );
                foreach ($secondaries as $s) {
                    \App\Core\Database::execute("UPDATE property_tenant SET moved_out_at = NOW(), updated_at = NOW() WHERE tenant_id = ? AND moved_out_at IS NULL", [$s['tenant_id']]);
                    \App\Core\Database::execute("UPDATE users SET archived_at = NOW() WHERE id = ? AND archived_at IS NULL", [$s['tenant_id']]);
                    \App\Core\Database::execute("UPDATE leases SET archived_at = NOW() WHERE tenant_id = ? AND archived_at IS NULL", [$s['tenant_id']]);
                }
            }
        }
        \App\Core\Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES ('last_moveout_check', ?) ON DUPLICATE KEY UPDATE `value` = ?",
            [$now, $now]
        );
    }
} catch (\Throwable $e) {
    error_log('Scheduled move-out check failed: ' . $e->getMessage());
}

// Boot check: redirect to setup if no admin or landlord user exists
$needsSetup = false;
try {
    $userExists = \App\Core\Database::fetch("SELECT id FROM users WHERE role IN ('admin','landlord') AND archived_at IS NULL LIMIT 1");
    $needsSetup = !$userExists;
} catch (\Throwable $e) {
    $needsSetup = true;
}
if ($needsSetup && !str_starts_with($requestUri, '/setup')) {
    header('Location: /setup');
    exit;
}

$router = new \App\Core\Router();

// Setup (no middleware — boot check handles redirects)
$router->get('/setup', 'SetupController@create');
$router->post('/setup', 'SetupController@store');

// Auth routes
$router->get('/login', 'AuthController@login', ['guest']);
$router->post('/login', 'AuthController@loginPost', ['guest']);
$router->post('/logout', 'AuthController@logout', ['auth']);
$router->get('/onboarding', 'AuthController@onboarding', ['guest']);
$router->post('/onboarding', 'AuthController@onboardingPost', ['guest']);
$router->get('/forgot-password', 'AuthController@forgotPassword', ['guest']);
$router->post('/forgot-password', 'AuthController@forgotPasswordPost', ['guest']);
$router->get('/reset-password/{token}', 'AuthController@resetPassword', ['guest']);
$router->post('/reset-password', 'AuthController@resetPasswordPost', ['guest']);
$router->get('/password/change', 'AuthController@changePassword', ['auth']);
$router->post('/password/change', 'AuthController@changePasswordPost', ['auth']);

// Home
$router->get('/home', 'HomeController@index', ['auth']);

// Resources
$router->get('/resources', 'ResourceController@index', ['auth']);
$router->get('/resources/create', 'ResourceController@create', ['auth', 'role:admin,landlord,property_manager']);
$router->post('/resources', 'ResourceController@store', ['auth', 'role:admin,landlord,property_manager']);
$router->get('/resources/{id}/edit', 'ResourceController@edit', ['auth', 'role:admin,landlord,property_manager']);
$router->post('/resources/{id}/update', 'ResourceController@update', ['auth', 'role:admin,landlord,property_manager']);
$router->post('/resources/{id}/delete', 'ResourceController@destroy', ['auth', 'role:admin,landlord,property_manager']);

// Calendar
$router->get('/calendar', 'CalendarController@index', ['auth']);
$router->get('/calendar/events', 'CalendarController@events', ['auth']);

// Staff
$router->get('/staff', 'StaffController@index', ['auth', 'role:landlord,property_manager']);
$router->get('/staff/create', 'StaffController@create', ['auth', 'role:landlord']);
$router->post('/staff', 'StaffController@store', ['auth', 'role:landlord']);
$router->get('/staff/{id}', 'StaffController@show', ['auth', 'role:landlord,property_manager']);
$router->get('/staff/{id}/edit', 'StaffController@edit', ['auth', 'role:landlord,property_manager']);
$router->post('/staff/{id}/update', 'StaffController@update', ['auth', 'role:landlord,property_manager']);
$router->post('/staff/{id}/delete', 'StaffController@destroy', ['auth', 'role:admin,landlord']);
$router->post('/staff/{id}/restore', 'StaffController@restore', ['auth', 'role:admin']);
$router->post('/staff/{id}/hard-delete', 'StaffController@hardDelete', ['auth', 'role:admin']);

// Properties
$router->get('/properties', 'PropertyController@index', ['auth']);
$router->get('/properties/create', 'PropertyController@create', ['auth', 'role:landlord,property_manager']);
$router->post('/properties', 'PropertyController@store', ['auth', 'role:landlord,property_manager']);
$router->get('/properties/{id}', 'PropertyController@show', ['auth']);
$router->get('/properties/{id}/edit', 'PropertyController@edit', ['auth', 'role:landlord,property_manager']);
$router->post('/properties/{id}/update', 'PropertyController@update', ['auth', 'role:landlord,property_manager']);
$router->post('/properties/{id}/delete', 'PropertyController@destroy', ['auth', 'role:admin,landlord']);
$router->post('/properties/{id}/restore', 'PropertyController@restore', ['auth', 'role:admin']);
$router->post('/properties/{id}/photos', 'PropertyController@uploadPhoto', ['auth', 'role:admin,landlord,property_manager']);
$router->post('/properties/{id}/photos/{photoId}/main', 'PropertyController@setMainPhoto', ['auth', 'role:admin,landlord,property_manager']);
$router->post('/properties/{id}/photos/{photoId}/delete', 'PropertyController@deletePhoto', ['auth', 'role:admin,landlord,property_manager']);
$router->get('/properties/{id}/photos/{photoId}', 'PropertyController@servePhoto', ['auth']);
$router->get('/properties/{id}/photos/{photoId}/download', 'PropertyController@downloadPhoto', ['auth']);

// Tenants
$router->get('/tenants', 'TenantController@index', ['auth', 'role:landlord,property_manager']);
$router->get('/tenants/create', 'TenantController@create', ['auth', 'role:landlord,property_manager']);
$router->post('/tenants', 'TenantController@store', ['auth', 'role:landlord,property_manager']);
$router->get('/tenants/{id}', 'TenantController@show', ['auth', 'role:landlord,property_manager']);
$router->get('/tenants/{id}/edit', 'TenantController@edit', ['auth', 'role:landlord,property_manager']);
$router->post('/tenants/{id}/update', 'TenantController@update', ['auth', 'role:landlord,property_manager']);
$router->post('/tenants/{id}/move-out', 'TenantController@moveOut', ['auth', 'role:landlord,property_manager']);
$router->post('/tenants/{id}/restore', 'TenantController@restore', ['auth', 'role:admin']);
$router->post('/tenants/{id}/delete', 'TenantController@destroy', ['auth', 'role:admin']);

// Leases
$router->get('/leases', 'LeaseController@index', ['auth']);
$router->get('/leases/create', 'LeaseController@create', ['auth', 'role:landlord,property_manager']);
$router->post('/leases', 'LeaseController@store', ['auth', 'role:landlord,property_manager']);
$router->get('/leases/{id}', 'LeaseController@show', ['auth']);
$router->post('/leases/{id}/delete', 'LeaseController@destroy', ['auth', 'role:admin,landlord,property_manager']);
$router->post('/leases/{id}/restore', 'LeaseController@restore', ['auth', 'role:admin']);
$router->post('/leases/{id}/hard-delete', 'LeaseController@hardDelete', ['auth', 'role:admin']);

// Tickets
$router->get('/tickets', 'TicketController@index', ['auth']);
$router->get('/tickets/create', 'TicketController@create', ['auth']);
$router->post('/tickets', 'TicketController@store', ['auth']);
$router->get('/tickets/{id}', 'TicketController@show', ['auth']);
$router->post('/tickets/{id}/assign', 'TicketController@assign', ['auth', 'role:admin,landlord,property_manager,maintenance']);
$router->post('/tickets/{id}/status', 'TicketController@status', ['auth', 'role:admin,landlord,property_manager,maintenance']);
$router->post('/tickets/{id}/restore', 'TicketController@restore', ['auth', 'role:admin']);
$router->post('/tickets/{id}/comment', 'TicketController@comment', ['auth']);

// Documents
$router->get('/documents/{id}/download', 'DocumentController@download', ['auth']);
$router->post('/documents/{id}/delete', 'DocumentController@destroy', ['auth', 'role:admin,landlord,property_manager']);

// Notifications
$router->get('/notifications', 'NotificationController@index', ['auth']);
$router->post('/notifications/read-all', 'NotificationController@readAll', ['auth']);
$router->post('/notifications/{id}/read', 'NotificationController@read', ['auth']);

// Profile
$router->get('/profile', 'ProfileController@edit', ['auth']);
$router->post('/profile', 'ProfileController@update', ['auth']);

// Settings (admin only)
$router->get('/settings', 'SettingsController@index', ['auth', 'role:admin']);
$router->post('/settings/reset', 'SettingsController@reset', ['auth', 'role:admin']);
$router->post('/settings/general', 'SettingsController@saveGeneral', ['auth', 'role:admin']);
$router->post('/settings/mail', 'SettingsController@saveMail', ['auth', 'role:admin']);
$router->post('/settings/test-mail', 'SettingsController@testMail', ['auth', 'role:admin']);
$router->post('/settings/update-channel', 'SettingsController@setUpdateChannel', ['auth', 'role:admin']);

// Updates API (admin only)
$router->post('/updates/check', 'UpdateController@check', ['auth', 'role:admin']);
$router->post('/updates/apply', 'UpdateController@apply', ['auth', 'role:admin']);
$router->get('/updates/progress', 'UpdateController@progress', ['auth', 'role:admin']);

try {
    $method = $_POST['_method'] ?? $_SERVER['REQUEST_METHOD'];
    $uri = $_SERVER['REQUEST_URI'];
    $router->dispatch(strtoupper($method), $uri);
} catch (\Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    require base_path('www/Views/errors/500.php');
}
