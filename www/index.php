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

$router = new \App\Core\Router();

// Auth routes
$router->get('/login', 'AuthController@login', ['guest']);
$router->post('/login', 'AuthController@loginPost', ['guest']);
$router->post('/logout', 'AuthController@logout', ['auth']);
$router->get('/forgot-password', 'AuthController@forgotPassword', ['guest']);
$router->post('/forgot-password', 'AuthController@forgotPasswordPost', ['guest']);
$router->get('/reset-password/{token}', 'AuthController@resetPassword', ['guest']);
$router->post('/reset-password', 'AuthController@resetPasswordPost', ['guest']);
$router->get('/password/change', 'AuthController@changePassword', ['auth']);
$router->post('/password/change', 'AuthController@changePasswordPost', ['auth']);

// Dashboard
$router->get('/dashboard', 'DashboardController@index', ['auth']);

// Companies
$router->get('/companies', 'CompanyController@index', ['auth', 'role:landlord,property_manager']);
$router->get('/companies/create', 'CompanyController@create', ['auth', 'role:landlord']);
$router->post('/companies', 'CompanyController@store', ['auth', 'role:landlord']);
$router->get('/companies/{id}', 'CompanyController@show', ['auth', 'role:staff']);
$router->get('/companies/{id}/edit', 'CompanyController@edit', ['auth', 'role:landlord']);
$router->post('/companies/{id}/update', 'CompanyController@update', ['auth', 'role:landlord']);
$router->post('/companies/{id}/delete', 'CompanyController@destroy', ['auth', 'role:landlord']);

// Properties
$router->get('/properties', 'PropertyController@index', ['auth']);
$router->get('/properties/create', 'PropertyController@create', ['auth', 'role:landlord,property_manager']);
$router->post('/properties', 'PropertyController@store', ['auth', 'role:landlord,property_manager']);
$router->get('/properties/{id}', 'PropertyController@show', ['auth']);
$router->get('/properties/{id}/edit', 'PropertyController@edit', ['auth', 'role:landlord,property_manager']);
$router->post('/properties/{id}/update', 'PropertyController@update', ['auth', 'role:landlord,property_manager']);
$router->post('/properties/{id}/delete', 'PropertyController@destroy', ['auth', 'role:landlord,property_manager']);

// Tenants
$router->get('/tenants', 'TenantController@index', ['auth', 'role:landlord,property_manager']);
$router->get('/tenants/create', 'TenantController@create', ['auth', 'role:landlord,property_manager']);
$router->post('/tenants', 'TenantController@store', ['auth', 'role:landlord,property_manager']);
$router->get('/tenants/{id}', 'TenantController@show', ['auth', 'role:landlord,property_manager']);
$router->get('/tenants/{id}/edit', 'TenantController@edit', ['auth', 'role:landlord,property_manager']);
$router->post('/tenants/{id}/update', 'TenantController@update', ['auth', 'role:landlord,property_manager']);
$router->post('/tenants/{id}/move-out', 'TenantController@moveOut', ['auth', 'role:landlord,property_manager']);

// Leases
$router->get('/leases', 'LeaseController@index', ['auth']);
$router->get('/leases/create', 'LeaseController@create', ['auth', 'role:landlord,property_manager']);
$router->post('/leases', 'LeaseController@store', ['auth', 'role:landlord,property_manager']);
$router->get('/leases/{id}', 'LeaseController@show', ['auth']);
$router->post('/leases/{id}/delete', 'LeaseController@destroy', ['auth', 'role:landlord,property_manager']);

// Tickets
$router->get('/tickets', 'TicketController@index', ['auth']);
$router->get('/tickets/create', 'TicketController@create', ['auth', 'role:tenant']);
$router->post('/tickets', 'TicketController@store', ['auth', 'role:tenant']);
$router->get('/tickets/{id}', 'TicketController@show', ['auth']);
$router->post('/tickets/{id}/assign', 'TicketController@assign', ['auth', 'role:landlord,property_manager,maintenance']);
$router->post('/tickets/{id}/status', 'TicketController@status', ['auth', 'role:landlord,property_manager,maintenance']);
$router->post('/tickets/{id}/comment', 'TicketController@comment', ['auth']);

// Documents
$router->get('/documents/{id}/download', 'DocumentController@download', ['auth']);
$router->post('/documents/{id}/delete', 'DocumentController@destroy', ['auth', 'role:landlord,property_manager']);

// Notifications
$router->get('/notifications', 'NotificationController@index', ['auth']);
$router->post('/notifications/read-all', 'NotificationController@readAll', ['auth']);
$router->post('/notifications/{id}/read', 'NotificationController@read', ['auth']);

// Profile
$router->get('/profile', 'ProfileController@edit', ['auth']);
$router->post('/profile', 'ProfileController@update', ['auth']);

try {
    $method = $_POST['_method'] ?? $_SERVER['REQUEST_METHOD'];
    $uri = $_SERVER['REQUEST_URI'];
    $router->dispatch(strtoupper($method), $uri);
} catch (\Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    require base_path('www/Views/errors/500.php');
}
