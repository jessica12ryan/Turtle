<?php

function log_activity(string $action, string $description = ''): void
{
    try {
        $user = \App\Core\Auth::instance()->user();
        if (!$user) return;
        \App\Core\Database::execute(
            "INSERT INTO activity_logs (user_id, user_name, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
            [$user['id'], $user['name'], $action, $description, $_SERVER['REMOTE_ADDR'] ?? '']
        );
    } catch (\Throwable $e) {
        error_log('Failed to log activity: ' . $e->getMessage());
    }
}

function __(string $text): string
{
    static $translations = null;
    if ($translations === null) {
        $lang = current_language();
        $path = base_path("www/lang/{$lang}.php");
        $translations = file_exists($path) ? require $path : [];
    }
    return $translations[$text] ?? $text;
}

function getCachedSetting(string $key, int $ttl = 300): ?string
{
    $cacheKey = '_setting_cache_' . $key;
    $expiryKey = $cacheKey . '_expiry';
    if (isset($_SESSION[$cacheKey]) && isset($_SESSION[$expiryKey]) && $_SESSION[$expiryKey] > time()) {
        return $_SESSION[$cacheKey];
    }
    try {
        $row = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = ?", [$key]);
        $val = $row['value'] ?? null;
        $_SESSION[$cacheKey] = $val;
        $_SESSION[$expiryKey] = time() + $ttl;
        return $val;
    } catch (\Throwable $e) {
        return null;
    }
}

function current_language(): string
{
    $sessionLang = $_SESSION['_language'] ?? null;
    if ($sessionLang && in_array($sessionLang, ['en', 'fr', 'es'])) {
        return $sessionLang;
    }
    try {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            $userLang = \App\Core\Database::fetch("SELECT language FROM users WHERE id = ?", [$userId]);
            if ($userLang && !empty($userLang['language']) && in_array($userLang['language'], ['en', 'fr', 'es'])) {
                $_SESSION['_language'] = $userLang['language'];
                return $userLang['language'];
            }
        }
        $langVal = getCachedSetting('default_language', 300);
        if ($langVal && in_array($langVal, ['en', 'fr', 'es'])) {
            return $langVal;
        }
    } catch (\Throwable $e) {
    }
    return 'en';
}

function languages(): array
{
    return [
        'en' => 'English',
        'fr' => 'Français',
        'es' => 'Español',
    ];
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function sanitize_filename(string $name): string
{
    // Strip path traversal, null bytes, and control chars
    $name = str_replace(["\r", "\n", "\0"], '', $name);
    $name = str_replace(['/', '\\'], '', $name);
    // Remove .. sequences and control characters
    $name = str_replace('..', '', $name);
    $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);
    // Limit length and trim
    $name = trim($name);
    if (strlen($name) > 255) {
        $name = substr($name, 0, 255);
    }
    return $name;
}

function old(string $key, string $default = ''): string
{
    if (isset($_SESSION['_old'][$key])) {
        return h($_SESSION['_old'][$key]);
    }
    return h($default);
}

function base_url(): string
{
    static $basePath = null;
    if ($basePath === null) {
        $basePath = $_SERVER['HTTP_X_FORWARDED_PREFIX'] 
            ?? $_SERVER['HTTP_X_INGRESS_PATH'] 
            ?? '';
    }
    return $basePath;
}

function redirect(string $url): void
{
    // Prevent CRLF injection and open-redirect via // or \/
    $url = str_replace(["\r", "\n", "\0"], '', $url);
    $url = trim($url);
    // Block protocol-relative and absolute external URLs
    if (str_starts_with($url, '//') || str_starts_with($url, '\\') || preg_match('#^[a-z][a-z0-9+\-.]*:#i', $url)) {
        $url = '/';
    }
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
        $url = base_url() . $url;
    }
    header('Location: ' . $url);
    exit;
}

function redirectBack(): void
{
    $referer = $_SERVER['HTTP_REFERER'] ?? '/';
    // Block CRLF and null bytes
    $referer = str_replace(["\r", "\n", "\0"], '', $referer);
    // Block protocol-relative URLs like //evil.com
    if (str_starts_with($referer, '//') || str_starts_with($referer, '\\')) {
        $referer = '/';
    }
    $parsed = parse_url($referer);
    if (!empty($parsed['host']) && $parsed['host'] !== ($_SERVER['HTTP_HOST'] ?? '')) {
        $referer = '/';
    }
    // Only allow relative or same-host URLs
    if (!str_starts_with($referer, '/') && !str_starts_with($referer, base_url() . '/')) {
        $referer = '/';
    }
    redirect($referer);
}

function error(string $key, string $default = ''): string
{
    return $_SESSION['_errors'][$key] ?? $default;
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $message = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $message;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function verify_csrf(string $token): bool
{
    return hash_equals($_SESSION['_csrf_token'] ?? '', $token);
}

function loadEnvFile(string $path): void
{
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}

function asset(string $path): string
{
    return base_url() . $path;
}

function base_path(string $path = ''): string
{
    return __DIR__ . '/../' . ltrim($path, '/');
}

function httpGet(string $url, int $timeout = 5): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Turtle/1.0',
            CURLOPT_HEADER => false,
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($httpCode >= 200 && $httpCode < 400 && $body !== false) {
            return $body;
        }
        if ($body === false || $httpCode < 200 || $httpCode >= 400) {
            error_log("httpGet failed for {$url}: HTTP {$httpCode} " . ($curlError ?: ''));
        }
        return null;
    }

    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => ['timeout' => $timeout, 'user_agent' => 'Turtle/1.0'],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body !== false) return $body;
    }

    return null;
}

function httpGetWithHeaders(string $url, int $timeout = 5): ?array
{
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Turtle/1.0',
            CURLOPT_HEADER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        if ($response === false) return null;
        $headerStr = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        return [
            'http_code' => $httpCode,
            'headers' => $headerStr,
            'body' => $body,
        ];
    }

    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => ['timeout' => $timeout, 'user_agent' => 'Turtle/1.0'],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body !== false) {
            return ['http_code' => 200, 'headers' => '', 'body' => $body];
        }
    }

    return null;
}

function parseHttpDateHeader(string $headerStr): ?int
{
    foreach (explode("\r\n", $headerStr) as $line) {
        if (stripos($line, 'Date:') === 0) {
            $dateStr = trim(substr($line, 5));
            $ts = strtotime($dateStr);
            if ($ts !== false) return $ts;
        }
    }
    return null;
}

function checkNtpTime(): ?array
{
    try {
        $lastCheck = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'last_ntp_check'");
        $lastStatus = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'last_ntp_status'");
    } catch (\Throwable $e) {
        $lastCheck = null;
        $lastStatus = null;
    }

    $lastTs = $lastCheck['value'] ?? '';
    $cachedDrift = $lastStatus['value'] ?? '';

    if ($lastTs && $cachedDrift !== '' && (strtotime('now') - strtotime($lastTs)) < 3600) {
        if ($cachedDrift === 'unreachable') return null;
        return [
            'ntp_time' => 0,
            'system_time' => time(),
            'drift' => (int)$cachedDrift,
        ];
    }

    try {
        $row = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'ntp_server'");
        $server = trim($row['value'] ?? '');
    } catch (\Throwable $e) {
        $server = 'time.gov';
    }

    if ($server === '') {
        return ['ntp_time' => time(), 'system_time' => time(), 'drift' => 0];
    }

    $now = date('Y-m-d H:i:s');
    $ntpTs = null;

    // Try 1: time.gov XML API
    if ($server === 'time.gov') {
        $body = httpGet('https://time.gov/actualtime.cgi');
        if ($body === null) {
            $body = httpGet('http://time.gov/actualtime.cgi');
        }
        if ($body !== null) {
            $xml = @simplexml_load_string($body);
            if ($xml && isset($xml['time'])) {
                $ntpTs = (int)($xml['time']) / 1000;
            }
        }
    } else {
        $result = httpGetWithHeaders($server);
        if ($result !== null) {
            $ntpTs = parseHttpDateHeader($result['headers']);
            if ($ntpTs === null) {
                $xml = @simplexml_load_string($result['body']);
                if ($xml && isset($xml['time'])) {
                    $ntpTs = (int)($xml['time']) / 1000;
                }
            }
        }
    }

    // Try 2: Google Date header (most reliable HTTP endpoint)
    if ($ntpTs === null) {
        $result = httpGetWithHeaders('https://www.google.com/');
        if ($result === null) {
            $result = httpGetWithHeaders('http://www.google.com/');
        }
        if ($result !== null) {
            $ntpTs = parseHttpDateHeader($result['headers']);
        }
    }

    if ($ntpTs === null) {
        try {
            \App\Core\Database::execute("INSERT INTO settings (`key`, `value`) VALUES ('last_ntp_check', ?) ON DUPLICATE KEY UPDATE `value` = ?", [$now, $now]);
            \App\Core\Database::execute("INSERT INTO settings (`key`, `value`) VALUES ('last_ntp_status', ?) ON DUPLICATE KEY UPDATE `value` = ?", ['unreachable', 'unreachable']);
        } catch (\Throwable $e) {}
        return null;
    }

    $drift = abs(time() - (int)$ntpTs);
    try {
        \App\Core\Database::execute("INSERT INTO settings (`key`, `value`) VALUES ('last_ntp_check', ?) ON DUPLICATE KEY UPDATE `value` = ?", [$now, $now]);
        \App\Core\Database::execute("INSERT INTO settings (`key`, `value`) VALUES ('last_ntp_status', ?) ON DUPLICATE KEY UPDATE `value` = ?", [(string)$drift, (string)$drift]);
    } catch (\Throwable $e) {}
    return [
        'ntp_time' => (int)$ntpTs,
        'system_time' => time(),
        'drift' => $drift,
    ];
}

function defaultPermissions(): array
{
    return [
        'landlord' => [
            'home.access',
            'properties.access', 'properties.create', 'properties.edit', 'properties.archive', 'properties.restore',
            'photos.create', 'photos.edit', 'photos.download', 'photos.delete',
            'tenants.access', 'tenants.create', 'tenants.edit', 'tenants.archive', 'tenants.restore',
            'leases.access', 'leases.create', 'leases.archive', 'leases.restore',
            'tickets.access', 'tickets.create', 'tickets.assign', 'tickets.update_status', 'tickets.archive', 'tickets.restore', 'tickets.comment', 'tickets.internal_comment', 'tickets.upload_photos', 'tickets.download_photos',
            'staff.access', 'staff.create', 'staff.edit', 'staff.archive', 'staff.restore',
            'resources.access', 'resources.create', 'resources.edit', 'resources.delete',
            'calendar.access',
            'documents.download',
            'ai_assistant.access',
            'rents.access', 'rents.payments.create', 'rents.payments.edit', 'rents.payments.archive', 'rents.payments.restore',
            'applications.view', 'applications.edit', 'applications.archive', 'applications.restore',
        ],
        'property_manager' => [
            'home.access',
            'properties.access', 'properties.edit',
            'photos.create', 'photos.edit', 'photos.download', 'photos.delete',
            'tenants.access', 'tenants.create', 'tenants.edit',
            'leases.access', 'leases.create',
            'tickets.access', 'tickets.create', 'tickets.assign', 'tickets.update_status', 'tickets.comment', 'tickets.internal_comment', 'tickets.upload_photos', 'tickets.download_photos',
            'staff.access',
            'resources.access', 'resources.create', 'resources.edit', 'resources.delete',
            'calendar.access',
            'documents.download',
            'ai_assistant.access',
            'rents.access', 'rents.payments.create',
            'applications.view', 'applications.edit',
        ],
        'maintenance' => [
            'home.access',
            'properties.access',
            'tenants.access',
            'staff.access',
            'tickets.access', 'tickets.create', 'tickets.assign', 'tickets.update_status', 'tickets.comment', 'tickets.internal_comment', 'tickets.upload_photos', 'tickets.download_photos',
        ],
        'tenant' => [
            'home.access',
            'properties.access',
            'tenants.access',
            'tickets.access', 'tickets.create', 'tickets.comment', 'tickets.upload_photos', 'tickets.download_photos',
            'resources.access',
            'leases.access',
            'documents.download',
            'rents.access',
        ],
    ];
}

function permissionsMode(): string
{
    try {
        $row = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'permissions_mode'");
        return $row['value'] ?? 'default';
    } catch (\Throwable $e) {
        return 'default';
    }
}

function can(string $permission): bool
{
    try {
        $user = \App\Core\Auth::instance()->user();
    } catch (\Throwable $e) {
        return false;
    }
    if (!$user) return false;

    $rolesToCheck = [$user['role']];
    if (!empty($user['secondary_roles'])) {
        $secondary = explode(',', $user['secondary_roles']);
        $rolesToCheck = array_merge($rolesToCheck, $secondary);
    }

    foreach ($rolesToCheck as $role) {
        if ($role === 'admin') return true;

        if (permissionsMode() === 'custom') {
            try {
                $row = \App\Core\Database::fetch(
                    "SELECT 1 FROM role_permissions WHERE role = ? AND permission = ?",
                    [$role, $permission]
                );
                if ($row) return true;
            } catch (\Throwable $e) {}
        }

        $defaults = defaultPermissions();
        if (in_array($permission, $defaults[$role] ?? [])) {
            return true;
        }
    }

    return false;
}

function provinces(): array
{
    return regions('CA');
}

function regions(string $country = 'CA'): array
{
    $ca = [
        'AB' => 'Alberta',
        'BC' => 'British Columbia',
        'MB' => 'Manitoba',
        'NB' => 'New Brunswick',
        'NL' => 'Newfoundland and Labrador',
        'NS' => 'Nova Scotia',
        'ON' => 'Ontario',
        'PE' => 'Prince Edward Island',
        'QC' => 'Quebec',
        'SK' => 'Saskatchewan',
        'NT' => 'Northwest Territories',
        'NU' => 'Nunavut',
        'YT' => 'Yukon',
    ];
    $us = [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
        'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut',
        'DE' => 'Delaware', 'FL' => 'Florida', 'GA' => 'Georgia',
        'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana',
        'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana',
        'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts',
        'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
        'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
        'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
        'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota',
        'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania',
        'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota',
        'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont',
        'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
        'WI' => 'Wisconsin', 'WY' => 'Wyoming',
    ];
    return $country === 'US' ? $us : $ca;
}

function region_label(string $country = 'CA'): string
{
    return $country === 'US' ? 'State' : 'Province';
}

function postal_label(string $country = 'CA'): string
{
    return $country === 'US' ? 'Zip Code' : 'Postal Code';
}

function default_country(): string
{
    try {
        $row = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'default_country'");
        return $row['value'] ?? 'CA';
    } catch (\Throwable $e) {
        return 'CA';
    }
}

function site_name(): string
{
    try {
        $row = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'site_name'");
        return $row['value'] ?? 'Turtle';
    } catch (\Throwable $e) {
        return 'Turtle';
    }
}

function site_logo(): string
{
    try {
        $row = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'logo_path'");
        if ($row && $row['value'] !== '') {
            $path = $row['value'];
            // Use app_version for cache busting instead of filemtime (avoids mtime leak)
            $ver = '';
            try {
                $vRow = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'app_version'");
                if ($vRow && $vRow['value'] !== '') {
                    $ver = '?v=' . $vRow['value'];
                }
            } catch (\Throwable $e) {}
            return '/' . ltrim($path, '/') . $ver;
        }
    } catch (\Throwable $e) {}
    return '/assets/logo.svg';
}

function defaultNotifications(): array
{
    return [
        'ticket_assigned' => ['landlord', 'property_manager', 'maintenance'],
        'ticket_status_updated' => ['tenant'],
        'document_uploaded' => ['tenant'],
        'staff_welcome' => ['admin', 'landlord', 'property_manager', 'maintenance'],
        'tenant_welcome' => ['tenant'],
        'password_reset' => ['admin', 'landlord', 'property_manager', 'maintenance', 'tenant'],
        'onboarding' => ['admin', 'landlord', 'property_manager', 'maintenance', 'tenant'],
    ];
}

function site_url(): string
{
    try {
        $row = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'site_url'");
        if ($row && $row['value'] !== '') {
            return rtrim($row['value'], '/');
        }
    } catch (\Throwable $e) {}
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function display_time(?string $datetime, string $format = 'M j, Y g:i A'): string
{
    if (!$datetime) return '';
    try {
        $dt = new \DateTime($datetime, new \DateTimeZone('UTC'));
        $dt->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        return $dt->format($format);
    } catch (\Throwable $e) {
        return h($datetime);
    }
}
