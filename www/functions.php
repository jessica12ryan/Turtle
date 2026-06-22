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
        $row = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'ntp_server'");
        $server = $row['value'] ?? 'time.gov';
    } catch (\Throwable $e) {
        $server = 'time.gov';
    }

    $url = $server === 'time.gov'
        ? 'https://time.gov/actualtime.cgi'
        : $server;

    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) return null;

    if ($server === 'time.gov') {
        $xml = @simplexml_load_string($response);
        if (!$xml || !isset($xml['time'])) return null;
        $ntpMs = (int)$xml['time'];
        return [
            'ntp_time' => $ntpMs / 1000,
            'system_time' => time(),
            'drift' => abs(time() - ($ntpMs / 1000)),
        ];
    }

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
