<?php

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function old(string $key, string $default = ''): string
{
    return isset($_SESSION['_old'][$key]) ? h($_SESSION['_old'][$key]) : $default;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function redirectBack(): void
{
    $url = $_SERVER['HTTP_REFERER'] ?? '/';
    redirect($url);
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

function asset(string $path): string
{
    return $path;
}

function base_path(string $path = ''): string
{
    return __DIR__ . '/../' . ltrim($path, '/');
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

    // Return cached result if checked within the last hour
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

    // Empty server = NTP check disabled
    if ($server === '') {
        return ['ntp_time' => time(), 'system_time' => time(), 'drift' => 0];
    }

    $now = date('Y-m-d H:i:s');
    $ntpMs = null;

    // Try multiple methods to get NTP time
    if ($server === 'time.gov') {
        // Method 1: HTTPS with default context
        $ctx = stream_context_create(['http' => ['timeout' => 5]]);
        $response = @file_get_contents('https://time.gov/actualtime.cgi', false, $ctx);
        if ($response === false) {
            // Method 2: HTTPS with SSL verification disabled
            $ctx = stream_context_create(['http' => ['timeout' => 5], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
            $response = @file_get_contents('https://time.gov/actualtime.cgi', false, $ctx);
        }
        if ($response === false) {
            // Method 3: HTTP (no encryption)
            $ctx = stream_context_create(['http' => ['timeout' => 5]]);
            $response = @file_get_contents('http://time.gov/actualtime.cgi', false, $ctx);
        }
        if ($response !== false) {
            $xml = @simplexml_load_string($response);
            if ($xml && isset($xml['time'])) {
                $ntpMs = (int)$xml['time'];
            }
        }
    } else {
        // Custom server - try as-is
        $ctx = stream_context_create(['http' => ['timeout' => 5], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $response = @file_get_contents($server, false, $ctx);
        // Try to parse as XML (time.gov format) or just use response time
        if ($response !== false) {
            $xml = @simplexml_load_string($response);
            if ($xml && isset($xml['time'])) {
                $ntpMs = (int)$xml['time'];
            }
        }
    }

    // Save result to cache
    if ($ntpMs !== null) {
        $drift = abs(time() - (int)($ntpMs / 1000));
        try {
            \App\Core\Database::execute("INSERT INTO settings (`key`, `value`) VALUES ('last_ntp_check', ?) ON DUPLICATE KEY UPDATE `value` = ?", [$now, $now]);
            \App\Core\Database::execute("INSERT INTO settings (`key`, `value`) VALUES ('last_ntp_status', ?) ON DUPLICATE KEY UPDATE `value` = ?", [(string)$drift, (string)$drift]);
        } catch (\Throwable $e) {}
        return [
            'ntp_time' => (int)($ntpMs / 1000),
            'system_time' => time(),
            'drift' => $drift,
        ];
    }

    // Cache the failure
    try {
        \App\Core\Database::execute("INSERT INTO settings (`key`, `value`) VALUES ('last_ntp_check', ?) ON DUPLICATE KEY UPDATE `value` = ?", [$now, $now]);
        \App\Core\Database::execute("INSERT INTO settings (`key`, `value`) VALUES ('last_ntp_status', ?) ON DUPLICATE KEY UPDATE `value` = ?", ['unreachable', 'unreachable']);
    } catch (\Throwable $e) {}

    return null;
}

function provinces(): array
{
    return [
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
}
