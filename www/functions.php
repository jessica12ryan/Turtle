<?php

function log_activity(string $action, string $description = ''): void
{
    try {
        $user = \App\Core\Auth::instance()->user();
        // Events triggered without a session (public forms, mail sent by cron, etc.)
        // are attributed to "System" with a null user_id.
        $userId = $user['id'] ?? null;
        $userName = $user['name'] ?? 'System';
        \App\Core\Database::execute(
            "INSERT INTO activity_logs (user_id, user_name, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
            [$userId, $userName, $action, $description, $_SERVER['REMOTE_ADDR'] ?? '']
        );
    } catch (\Throwable $e) {
        \App\Core\Logger::error('Failed to log activity: ' . $e->getMessage());
    }
}

function __(string $text, array $replace = []): string
{
    static $translations = [];
    $lang = current_language();
    if (!isset($translations[$lang])) {
        $path = base_path("www/lang/{$lang}.php");
        $translations[$lang] = file_exists($path) ? require $path : [];
    }
    $translated = $translations[$lang][$text] ?? $text;
    return $replace ? strtr($translated, $replace) : $translated;
}

/**
 * Read a setting with a request-scoped cache only.
 *
 * Values are never stored in $_SESSION: that duplicated identical settings
 * into every anonymous visitor's session and kept them stale for up to five
 * minutes after an admin changed them. The $ttl argument is kept for callers
 * that pass it, but the cache now lives for a single request only.
 */
function getCachedSetting(string $key, int $ttl = 300): ?string
{
    if (!isset($GLOBALS['turtle_setting_cache']) || !is_array($GLOBALS['turtle_setting_cache'])) {
        $GLOBALS['turtle_setting_cache'] = [];
    }
    if (array_key_exists($key, $GLOBALS['turtle_setting_cache'])) {
        return $GLOBALS['turtle_setting_cache'][$key];
    }

    try {
        $row = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = ?", [$key]);
        $value = ($row && array_key_exists('value', $row) && $row['value'] !== null)
            ? (string)$row['value']
            : null;
    } catch (\Throwable $e) {
        $value = null;
    }

    $GLOBALS['turtle_setting_cache'][$key] = $value;
    return $value;
}

/** Whether an active admin or landlord exists — shared by boot and setup guards. */
function setup_is_complete(): bool
{
    try {
        return (bool) \App\Core\Database::fetch(
            "SELECT id FROM users WHERE role IN ('admin','landlord') AND archived_at IS NULL LIMIT 1"
        );
    } catch (\Throwable $e) {
        return false;
    }
}

/** True only for a fresh install with no active accounts (safe for /setup/restore). */
function setup_is_fresh(): bool
{
    try {
        return \App\Core\Database::fetch("SELECT id FROM users WHERE archived_at IS NULL LIMIT 1") === null;
    } catch (\Throwable $e) {
        return false;
    }
}

/** Drop cached settings, optionally just one key (useful after a settings write). */
function clearSettingCache(?string $key = null): void
{
    if ($key === null) {
        unset($GLOBALS['turtle_setting_cache']);
        return;
    }
    unset($GLOBALS['turtle_setting_cache'][$key]);
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

/**
 * Render a property's saved advertisement template for the public listings
 * page. The template and all substituted values are escaped before they are
 * combined, so manager-entered copy can never inject markup.
 */
function render_ad_description(array $property): string
{
    $template = trim((string) ($property['ad_description'] ?? ''));
    if ($template === '') {
        return '';
    }
    $values = [
        '{{name}}' => h($property['name'] ?? ''),
        '{{address}}' => h($property['address'] ?? ''),
        '{{city}}' => h($property['city'] ?? ''),
        '{{province}}' => h($property['province'] ?? ''),
        '{{rent}}' => !empty($property['rent_amount']) ? h(number_format((float) $property['rent_amount'], 2)) : '',
        '{{heating}}' => h($property['heating_type'] ?? ''),
        '{{deposit}}' => !empty($property['security_deposit']) ? h(number_format((float) $property['security_deposit'], 2)) : '',
    ];
    return nl2br(strtr(h($template), $values), false);
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

/**
 * Whether the last update check reported an update. Used instead of comparing
 * app_version (a tag) with latest_version (a commit hash on the dev channel).
 */
function has_available_update(array $settings): bool
{
    return (string)($settings['update_available'] ?? '0') === '1';
}

/** Per-request CSP nonce, set by the front controller. */
function csp_nonce(): string
{
    return (string)($GLOBALS['turtle_csp_nonce'] ?? '');
}

/** Backups must never carry .env: it contains APP_KEY and DB/mail secrets. */
function backup_archive_contains_env(\ZipArchive $zip): bool
{
    return $zip->locateName('.env') !== false;
}

/** MIME types the app accepts for image uploads. HEIC/HEIF are converted to JPEG. */
function image_upload_mimes(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
    ];
}

function is_heic_mime(string $mime): bool
{
    return in_array(strtolower($mime), ['image/heic', 'image/heif'], true);
}

/**
 * Validate an uploaded image and convert HEIC/HEIF to JPEG.
 *
 * @return array{tmp_name:string,mime:string,ext:string,converted:bool}|null
 */
function prepare_image_upload(array $file): ?array
{
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) {
        return null;
    }
    $mime = strtolower((string)((new \finfo(FILEINFO_MIME_TYPE))->file($tmp)));
    $map = image_upload_mimes();
    if (!isset($map[$mime])) {
        return null;
    }
    if (!is_heic_mime($mime)) {
        return ['tmp_name' => $tmp, 'mime' => $mime, 'ext' => $map[$mime], 'converted' => false];
    }

    $converter = trim((string)(@shell_exec('command -v heif-convert 2>/dev/null') ?? ''));
    if ($converter === '') {
        \App\Core\Logger::error('HEIC upload rejected: heif-convert is not installed.');
        return null;
    }
    $destination = tempnam(sys_get_temp_dir(), 'turtle_heic_');
    if ($destination === false) {
        return null;
    }
    unlink($destination);
    $destination .= '.jpg';
    exec(escapeshellarg($converter) . ' ' . escapeshellarg($tmp) . ' ' . escapeshellarg($destination) . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0 || !is_file($destination) || filesize($destination) === 0) {
        @unlink($destination);
        \App\Core\Logger::error('HEIC to JPEG conversion failed: ' . implode(' ', $output));
        return null;
    }
    return ['tmp_name' => $destination, 'mime' => 'image/jpeg', 'ext' => 'jpg', 'converted' => true];
}

/** Move a prepared upload to its final path. */
function store_image_upload(array $prepared, string $destination): bool
{
    if (!empty($prepared['converted'])) {
        if (@rename($prepared['tmp_name'], $destination)) {
            return true;
        }
        return @copy($prepared['tmp_name'], $destination) && @unlink($prepared['tmp_name']);
    }
    return @move_uploaded_file($prepared['tmp_name'], $destination);
}

function old(string $key, string $default = ''): string
{
    if (isset($_SESSION['_old'][$key])) {
        return h($_SESSION['_old'][$key]);
    }
    return h($default);
}

/** Original (unescaped) form input, for fields that are arrays. */
function old_raw(string $key, mixed $default = null): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

/** Unescaped form input as a scalar string, for JSON/JS contexts. */
function old_raw_text(string $key, string $default = ''): string
{
    $value = $_SESSION['_old'][$key] ?? $default;
    return is_scalar($value) ? (string) $value : $default;
}

/**
 * Sanitized reverse-proxy path prefix.
 *
 * These headers are client-controlled whenever the app is reachable directly,
 * so they must not be reflected into URLs or HTML verbatim.
 */
function ingress_prefix(): string
{
    static $prefix = null;
    if ($prefix === null) {
        $raw = $_SERVER['HTTP_X_FORWARDED_PREFIX'] ?? $_SERVER['HTTP_X_INGRESS_PATH'] ?? '';
        $raw = is_string($raw) ? trim($raw) : '';
        $prefix = ($raw !== ''
            && preg_match('#^/[A-Za-z0-9._~/-]{1,254}$#', $raw)
            && !str_contains($raw, '..')
            && !str_contains($raw, '//')) ? rtrim($raw, '/') : '';
    }
    return $prefix;
}

function base_url(): string
{
    return ingress_prefix();
}

/** Normalise a redirect target: no CRLF, no external or protocol-relative URL. */
function sanitize_redirect_url(string $url): string
{
    // Browsers normalise backslashes to forward slashes, so /\evil.com is an
    // open redirect just like //evil.com.
    $url = str_replace(["\r", "\n", "\0"], '', $url);
    $url = str_replace('\\', '/', $url);
    $url = trim($url);
    if (str_starts_with($url, '//') || preg_match('#^[a-z][a-z0-9+\-.]*:#i', $url)) {
        $url = '/';
    }
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
        $url = base_url() . $url;
    }
    return $url;
}

function redirect(string $url): never
{
    header('Location: ' . sanitize_redirect_url($url));
    exit;
}

/**
 * Resolve a Referer to a safe, unprefixed path. Browsers send absolute URLs,
 * and redirect() re-adds the ingress prefix, so any existing prefix is removed.
 */
function safe_referer_path(string $referer, string $host, string $basePath = ''): string
{
    $referer = str_replace(["\r", "\n", "\0"], '', $referer);
    $referer = str_replace('\\', '/', $referer);
    $referer = trim($referer);
    if ($referer === '' || str_starts_with($referer, '//')) {
        return '/';
    }

    $parsed = parse_url($referer);
    if ($parsed === false) {
        return '/';
    }
    if (isset($parsed['scheme']) && !in_array(strtolower((string) $parsed['scheme']), ['http', 'https'], true)) {
        return '/';
    }
    if (!empty($parsed['host'])) {
        $currentHost = preg_replace('/:\d+$/', '', $host) ?: $host;
        $refererHost = preg_replace('/:\d+$/', '', (string) $parsed['host']) ?: (string) $parsed['host'];
        if (strcasecmp($refererHost, $currentHost) !== 0) {
            return '/';
        }
    }

    $path = (string) ($parsed['path'] ?? '');
    if (!str_starts_with($path, '/')) {
        return '/';
    }
    if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
        $path = substr($path, strlen($basePath)) ?: '/';
    }
    if (!empty($parsed['query'])) {
        $path .= '?' . $parsed['query'];
    }
    return $path;
}

function redirectBack(): void
{
    redirect(safe_referer_path(
        (string) ($_SERVER['HTTP_REFERER'] ?? '/'),
        (string) ($_SERVER['HTTP_HOST'] ?? ''),
        base_url()
    ));
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

/**
 * Simple fixed-window throttle for authentication endpoints.
 * Returns false when the caller has exceeded the limit.
 */
function auth_rate_limit(string $key, int $max, int $windowSeconds): bool
{
    // The table is created by database/schema.sql and database/migrate.sh.
    // Creating it here required elevated DB privileges at request time and
    // failed silently on restricted installations.
    try {
        $now = time();
        $row = \App\Core\Database::fetch(
            "SELECT attempts, window_start FROM auth_rate_limits WHERE cache_key = ?",
            [$key]
        );
        if ($row && (int)$row['window_start'] > $now - $windowSeconds) {
            if ((int)$row['attempts'] >= $max) {
                return false;
            }
            \App\Core\Database::execute(
                "UPDATE auth_rate_limits SET attempts = attempts + 1, last_at = NOW() WHERE cache_key = ?",
                [$key]
            );
        } else {
            \App\Core\Database::execute(
                "INSERT INTO auth_rate_limits (cache_key, attempts, window_start, last_at)
                 VALUES (?, 1, ?, NOW())
                 ON DUPLICATE KEY UPDATE attempts = 1, window_start = VALUES(window_start), last_at = NOW()",
                [$key, $now]
            );
        }
        return true;
    } catch (\Throwable $e) {
        // Throttling is best-effort: a database failure is already surfaced by
        // the login flow. Log it so a missing table or insufficient privileges
        // does not silently disable rate limiting.
        \App\Core\Logger::error('auth_rate_limit unavailable: ' . $e->getMessage());
        return true;
    }
}

function auth_rate_limit_clear(string $key): void
{
    try {
        \App\Core\Database::execute("DELETE FROM auth_rate_limits WHERE cache_key = ?", [$key]);
    } catch (\Throwable $e) {
    }
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
    $expected = $_SESSION['_csrf_token'] ?? '';
    // hash_equals('', '') succeeds — an empty token must never authenticate
    // a state-changing request on a session that has no CSRF token yet.
    return $expected !== '' && $token !== '' && hash_equals($expected, $token);
}

function loadEnvFile(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
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

/** Bring a restored (possibly older) database up to the current schema. */
function run_database_migrations(): bool
{
    $root = dirname(__DIR__);
    if (!is_file($root . '/database/migrate.sh')) {
        return false;
    }
    $output = [];
    $command = 'cd ' . escapeshellarg($root) . ' && bash database/migrate.sh 2>&1';
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        \App\Core\Logger::error('Post-restore migration failed: ' . implode("\n", $output));
        return false;
    }
    return true;
}

/** Reject private/reserved IPv4 ranges (SSRF protection). */
function isPrivateIpv4(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        return true;
    }
    $value = ip2long($ip);
    $ranges = [
        ['0.0.0.0', '0.255.255.255'],
        ['10.0.0.0', '10.255.255.255'],
        ['100.64.0.0', '100.127.255.255'],
        ['127.0.0.0', '127.255.255.255'],
        ['169.254.0.0', '169.254.255.255'],
        ['172.16.0.0', '172.31.255.255'],
        ['192.0.0.0', '192.0.0.255'],
        ['192.168.0.0', '192.168.255.255'],
        ['198.18.0.0', '198.19.255.255'],
        ['224.0.0.0', '255.255.255.255'],
    ];
    foreach ($ranges as [$from, $to]) {
        if ($value >= ip2long($from) && $value <= ip2long($to)) {
            return true;
        }
    }
    return false;
}

/** Only public IPv6 literals are accepted. */
function isPublicIpv6(string $ip): bool
{
    $packed = @inet_pton($ip);
    if ($packed === false || strlen($packed) !== 16) {
        return false;
    }
    if ($packed === str_repeat("\0", 15) . "\1") {
        return false; // ::1
    }
    if ((ord($packed[0]) & 0xfe) === 0xfc) {
        return false; // fc00::/7 unique local
    }
    if (ord($packed[0]) === 0xfe && (ord($packed[1]) & 0xc0) === 0x80) {
        return false; // fe80::/10 link local
    }
    if (substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
        $mapped = @inet_ntop(substr($packed, 12, 4));
        return $mapped !== false && !isPrivateIpv4($mapped);
    }
    return true;
}

/** SSRF guard: HTTPS only, no credentials, no private/loopback/link-local hosts. */
function isPublicHttpsUrl(string $url): bool
{
    $parts = parse_url($url);
    if ($parts === false || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
        return false;
    }
    if (isset($parts['user']) || isset($parts['pass'])) {
        return false;
    }
    $host = trim((string)$parts['host'], '[]');
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return !isPrivateIpv4($host);
    }
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return isPublicIpv6($host);
    }
    // Fail closed if DNS cannot be verified: every address must be public.
    $addresses = gethostbynamel($host) ?: [];
    if ($addresses === []) {
        return false;
    }
    foreach ($addresses as $address) {
        if (isPrivateIpv4($address)) {
            return false;
        }
    }
    return true;
}

/** Resolve a relative Location header against the URL that produced it. */
function resolveHttpUrl(string $base, string $location): string
{
    if (preg_match('#^[a-z][a-z0-9+\-.]*:#i', $location) === 1) {
        return $location;
    }
    $parts = parse_url($base);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }
    $scheme = (string)$parts['scheme'];
    $authority = $scheme . '://' . (string)$parts['host'];
    if (isset($parts['port'])) {
        $authority .= ':' . $parts['port'];
    }
    if (str_starts_with($location, '//')) {
        return $scheme . ':' . $location;
    }
    if (str_starts_with($location, '/')) {
        return $authority . $location;
    }
    $path = (string)($parts['path'] ?? '');
    $slash = strrpos($path, '/');
    $dir = $slash === false ? '/' : substr($path, 0, $slash + 1);
    return $authority . $dir . $location;
}

/** Follow redirects manually so every hop is validated before it is fetched. */
function httpRedirectTarget(string $url, int $httpCode, string $location, int $redirects): ?string
{
    if ($redirects <= 0 || $httpCode < 300 || $httpCode >= 400 || $location === '') {
        return null;
    }
    $next = resolveHttpUrl($url, $location);
    return isPublicHttpsUrl($next) ? $next : null;
}

function httpGet(string $url, int $timeout = 5, int $redirects = 3): ?string
{
    if (!isPublicHttpsUrl($url)) {
        \App\Core\Logger::warning('httpGet refused a non-public URL.');
        return null;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Turtle/1.0',
            CURLOPT_HEADER => false,
        ]);
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $location = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $curlError = curl_error($ch);
        curl_close($ch);

        $next = httpRedirectTarget($url, $httpCode, $location, $redirects);
        if ($next !== null) {
            return httpGet($next, $timeout, $redirects - 1);
        }
        if ($httpCode >= 200 && $httpCode < 400 && $body !== false) {
            return (string) $body;
        }
        \App\Core\Logger::error("httpGet failed for {$url}: HTTP {$httpCode} " . ($curlError ?: ''));
        return null;
    }

    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => ['timeout' => $timeout, 'user_agent' => 'Turtle/1.0', 'follow_location' => 0],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        $responseHeaders = http_get_last_response_headers() ?: [];
        [$code, $location] = httpParseResponseHeaders($responseHeaders);
        $next = httpRedirectTarget($url, $code, $location, $redirects);
        if ($next !== null) {
            return httpGet($next, $timeout, $redirects - 1);
        }
        if ($body !== false) {
            return $body;
        }
    }

    return null;
}

/** Extract the status code and Location from a stream-wrapper header list. */
function httpParseResponseHeaders(array $headers): array
{
    $code = 0;
    $location = '';
    foreach ($headers as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $m)) {
            $code = (int) $m[1];
        }
        if (preg_match('#^Location:\s*(.+)$#i', $header, $m)) {
            $location = trim($m[1]);
        }
    }
    return [$code, $location];
}

function httpGetWithHeaders(string $url, int $timeout = 5, int $redirects = 3): ?array
{
    if (!isPublicHttpsUrl($url)) {
        \App\Core\Logger::warning('httpGetWithHeaders refused a non-public URL.');
        return null;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Turtle/1.0',
            CURLOPT_HEADER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $location = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);
        if ($response === false) {
            return null;
        }

        $next = httpRedirectTarget($url, $httpCode, $location, $redirects);
        if ($next !== null) {
            return httpGetWithHeaders($next, $timeout, $redirects - 1);
        }
        return [
            'http_code' => $httpCode,
            'headers' => substr($response, 0, $headerSize),
            'body' => substr($response, $headerSize),
        ];
    }

    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => ['timeout' => $timeout, 'user_agent' => 'Turtle/1.0', 'follow_location' => 0],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        $responseHeaders = http_get_last_response_headers() ?: [];
        [$code, $location] = httpParseResponseHeaders($responseHeaders);
        $next = httpRedirectTarget($url, $code, $location, $redirects);
        if ($next !== null) {
            return httpGetWithHeaders($next, $timeout, $redirects - 1);
        }
        if ($body !== false) {
            return ['http_code' => $code, 'headers' => implode("\r\n", $responseHeaders), 'body' => $body];
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
            if ($ts !== false) {
                return $ts;
            }
        }
    }
    return null;
}

/**
 * POST a raw JSON body. Returns ['http_code'=>int,'body'=>string] or null.
 * Never logs headers/body (may contain Authorization bearer token).
 */
function httpPostJson(string $url, string $jsonBody, array $headers = [], int $timeout = 5): ?array
{
    if (function_exists('curl_init')) {
        $ch = curl_init();
        $defaultHeaders = ['Content-Type: application/json', 'Content-Length: ' . strlen($jsonBody)];
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min($timeout, 5),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Turtle/1.0',
            CURLOPT_HEADER => false,
        ]);
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false) {
            return null;
        }
        return ['http_code' => $httpCode, 'body' => (string) $body];
    }

    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", array_merge(['Content-Type: application/json'], $headers)),
                'content' => $jsonBody,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            return null;
        }
        $code = 0;
        $respHeaders = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?: []) : [];
        if (isset($respHeaders[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $respHeaders[0], $m)) {
            $code = (int) $m[1];
        }
        return ['http_code' => $code, 'body' => (string) $body];
    }

    return null;
}

/**
 * Normalise a configured HTTP(S) endpoint to HTTPS. Returns null when the
 * value cannot be used safely (no scheme, or a non-HTTP scheme).
 */
function httpsOnlyUrl(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }
    $parts = parse_url($url);
    if ($parts === false || empty($parts['host'])) {
        return null;
    }
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return null;
    }
    // Upgrade plaintext URLs rather than falling back to them.
    $parts['scheme'] = 'https';
    $port = $parts['port'] ?? null;
    if ($scheme === 'http' && $port === 80) {
        unset($parts['port']);
    }

    $host = $parts['host'];
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $host = '[' . $host . ']';
    }
    $userInfo = isset($parts['user'])
        ? $parts['user'] . (isset($parts['pass']) ? ':' . $parts['pass'] : '') . '@'
        : '';
    $result = 'https://' . $userInfo . $host;
    if (isset($parts['port'])) {
        $result .= ':' . $parts['port'];
    }
    $result .= $parts['path'] ?? '';
    if (isset($parts['query'])) {
        $result .= '?' . $parts['query'];
    }
    if (isset($parts['fragment'])) {
        $result .= '#' . $parts['fragment'];
    }
    return $result;
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
        if ($cachedDrift === 'unreachable') {
            return null;
        }
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
        // HTTPS only: a plaintext fallback lets a network attacker spoof time.
        $body = httpGet('https://time.gov/actualtime.cgi');
        if ($body !== null) {
            $xml = @simplexml_load_string($body);
            if ($xml && isset($xml['time'])) {
                $ntpTs = (int)($xml['time']) / 1000;
            }
        }
    } else {
        $secureServer = httpsOnlyUrl($server);
        if ($secureServer === null) {
            \App\Core\Logger::warning('NTP server URL is invalid or uses an unsupported scheme; ignoring.');
        } else {
            $result = httpGetWithHeaders($secureServer);
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
    }

    // Try 2: Google Date header (HTTPS only)
    if ($ntpTs === null) {
        $result = httpGetWithHeaders('https://www.google.com/');
        if ($result !== null) {
            $ntpTs = parseHttpDateHeader($result['headers']);
        }
    }

    if ($ntpTs === null) {
        try {
            \App\Core\Database::execute("INSERT INTO settings (`key`, `value`) VALUES ('last_ntp_check', ?) ON DUPLICATE KEY UPDATE `value` = ?", [$now, $now]);
            \App\Core\Database::execute("INSERT INTO settings (`key`, `value`) VALUES ('last_ntp_status', ?) ON DUPLICATE KEY UPDATE `value` = ?", ['unreachable', 'unreachable']);
        } catch (\Throwable $e) {
        }
        return null;
    }

    $drift = abs(time() - (int)$ntpTs);
    try {
        \App\Core\Database::execute("INSERT INTO settings (`key`, `value`) VALUES ('last_ntp_check', ?) ON DUPLICATE KEY UPDATE `value` = ?", [$now, $now]);
        \App\Core\Database::execute("INSERT INTO settings (`key`, `value`) VALUES ('last_ntp_status', ?) ON DUPLICATE KEY UPDATE `value` = ?", [(string)$drift, (string)$drift]);
    } catch (\Throwable $e) {
    }
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
            'guest_chats.access', 'guest_chats.archive', 'guest_chats.restore',
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
            'guest_chats.access',
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
    return $user ? user_can($user, $permission) : false;
}

/**
 * Permission check for an explicit user (not necessarily the logged-in one).
 * Mirrors can() so callers can validate a target user such as a ticket assignee.
 */
function user_can(array $user, string $permission): bool
{
    foreach (effective_roles($user) as $role) {
        if ($role === 'admin') {
            return true;
        }

        if (permissionsMode() === 'custom') {
            try {
                $row = \App\Core\Database::fetch(
                    "SELECT 1 FROM role_permissions WHERE role = ? AND permission = ?",
                    [$role, $permission]
                );
                if ($row) {
                    return true;
                }
            } catch (\Throwable $e) {
            }
        }

        $defaults = defaultPermissions();
        if (in_array($permission, $defaults[$role] ?? [], true)) {
            return true;
        }
    }

    return false;
}

function has_role(array $user, string $role): bool
{
    return in_array($role, effective_roles($user), true);
}

function is_admin_user(array $user): bool
{
    return has_role($user, 'admin');
}

function is_tenant_only_user(array $user): bool
{
    $roles = effective_roles($user);
    if (!in_array('tenant', $roles, true)) {
        return false;
    }
    return !array_intersect($roles, ['admin', 'landlord', 'property_manager', 'maintenance']);
}

function is_property_manager_user(array $user): bool
{
    return has_role($user, 'property_manager');
}

/**
 * Property IDs visible to a user, honouring primary and secondary roles.
 * A property is included when any effective role grants access:
 * admin (all), landlord/maintenance (company), property_manager (managed),
 * or tenant (active tenancy).
 */
function accessible_property_ids(array $user): array
{
    if (is_admin_user($user)) {
        return array_map('intval', array_column(
            \App\Core\Database::fetchAll("SELECT id FROM properties WHERE archived_at IS NULL"),
            'id'
        ));
    }

    if (is_tenant_only_user($user)) {
        return array_map('intval', array_column(
            \App\Core\Database::fetchAll(
                "SELECT p.id FROM properties p
                 JOIN property_tenant pt ON pt.property_id = p.id
                 WHERE pt.tenant_id = ? AND pt.moved_out_at IS NULL AND p.archived_at IS NULL",
                [$user['id']]
            ),
            'id'
        ));
    }

    $companyIds = user_company_ids((int)($user['id'] ?? 0));
    $candidates = [];
    if ($companyIds !== []) {
        $placeholders = implode(',', array_fill(0, count($companyIds), '?'));
        $candidates = \App\Core\Database::fetchAll(
            "SELECT id, company_id, property_manager_id FROM properties
             WHERE company_id IN ({$placeholders}) AND archived_at IS NULL",
            $companyIds
        );
    }
    if (is_property_manager_user($user)) {
        $managed = \App\Core\Database::fetchAll(
            "SELECT id, company_id, property_manager_id FROM properties
             WHERE property_manager_id = ? AND archived_at IS NULL",
            [$user['id']]
        );
        $candidates = array_merge($candidates, $managed);
    }

    $seen = [];
    $ids = [];
    foreach ($candidates as $property) {
        $id = (int)$property['id'];
        if (isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        if (user_can_access_property($user, $property, $companyIds)) {
            $ids[] = $id;
        }
    }
    return $ids;
}

/** Canonical ticket statuses. Includes `resolved` for legacy seeded data. */
function ticket_statuses(): array
{
    return ['open', 'in_progress', 'awaiting_parts', 'awaiting_contractor', 'resolved', 'closed'];
}

function is_valid_ticket_status(string $status): bool
{
    return in_array($status, ticket_statuses(), true);
}

function ticket_assignment_comment_body(string $actorName, ?string $assigneeName): string
{
    return $actorName . ' assigned ticket to ' . ($assigneeName ?? 'Unassigned');
}

function effective_roles(array $user): array
{
    $roles = [];
    if (!empty($user['role'])) {
        $roles[] = $user['role'];
    }
    if (!empty($user['secondary_roles'])) {
        foreach (explode(',', (string)$user['secondary_roles']) as $role) {
            $role = trim($role);
            if ($role !== '') {
                $roles[] = $role;
            }
        }
    }
    return array_values(array_unique($roles));
}

function user_company_ids(int $userId): array
{
    try {
        $rows = \App\Core\Database::fetchAll(
            "SELECT company_id FROM company_user WHERE user_id = ?",
            [$userId]
        );
    } catch (\Throwable $e) {
        return [];
    }
    return array_map('intval', array_column($rows, 'company_id'));
}

/**
 * Whether a staff member can access a property.
 *
 * Access is the union of the rights granted by the user's primary and
 * secondary roles:
 *  - admin: every property
 *  - landlord / maintenance: properties belonging to one of the user's companies
 *  - property_manager: properties the user manages
 */
function user_can_access_property(array $user, array $property, ?array $companyIds = null): bool
{
    $roles = effective_roles($user);
    if (in_array('admin', $roles, true)) {
        return true;
    }

    $companyId = (int)($property['company_id'] ?? 0);
    if ($companyId === 0) {
        return false;
    }

    if ($companyIds === null) {
        $companyIds = user_company_ids((int)($user['id'] ?? 0));
    }

    if (
        in_array($companyId, $companyIds, true)
        && (in_array('landlord', $roles, true) || in_array('maintenance', $roles, true))
    ) {
        return true;
    }

    if (
        in_array('property_manager', $roles, true)
        && (int)($property['property_manager_id'] ?? 0) === (int)($user['id'] ?? 0)
    ) {
        return true;
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
            } catch (\Throwable $e) {
            }
            return '/' . ltrim($path, '/') . $ver;
        }
    } catch (\Throwable $e) {
    }
    return '/assets/logo.svg';
}

function defaultNotifications(): array
{
    $defaults = [
        'ticket_assigned' => ['landlord', 'property_manager', 'maintenance'],
        'ticket_created' => ['landlord', 'property_manager', 'maintenance'],
        'ticket_comment' => ['landlord', 'property_manager', 'maintenance', 'tenant'],
        'new_message' => ['landlord', 'property_manager', 'maintenance', 'tenant'],
        'ticket_status_updated' => ['tenant'],
        'document_uploaded' => ['tenant'],
        'staff_welcome' => ['admin', 'landlord', 'property_manager', 'maintenance'],
        'tenant_welcome' => ['tenant'],
        'password_reset' => ['admin', 'landlord', 'property_manager', 'maintenance', 'tenant'],
        'onboarding' => ['admin', 'landlord', 'property_manager', 'maintenance', 'tenant'],
        'new_application' => ['property_manager'],
        'new_guest_chat' => ['property_manager'],
        'guest_chat_reply' => ['property_manager'],
    ];

    // Admin defaults mirror the Landlord defaults for every event.
    foreach ($defaults as $event => $roles) {
        $landlord = in_array('landlord', $roles, true);
        $defaults[$event] = array_values(array_filter(
            $roles,
            fn($role) => $role !== 'admin' || $landlord
        ));
        if ($landlord && !in_array('admin', $defaults[$event], true)) {
            $defaults[$event][] = 'admin';
        }
    }

    return $defaults;
}

function avatar_url(?array $user): ?string
{
    if (!$user || empty($user['avatar_path'])) {
        return null;
    }
    return '/users/' . (int)($user['id'] ?? 0) . '/avatar';
}

function initials_for(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $initials !== '' ? $initials : '?';
}

if (!defined('PRESENCE_TIMEOUT_SECONDS')) {
    define('PRESENCE_TIMEOUT_SECONDS', 300);
}

function messaging_enabled(): bool
{
    try {
        $row = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'messaging_enabled'");
        return ($row['value'] ?? '1') !== '0';
    } catch (\Throwable $e) {
        return true;
    }
}

function applications_enabled(): bool
{
    try {
        $row = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'applications_enabled'");
        return ($row['value'] ?? '0') === '1';
    } catch (\Throwable $e) {
        return false;
    }
}

/** Property-scoped events whose recipients are the manager (or landlord fallback). */
function propertyNotificationEvents(): array
{
    return ['new_application', 'new_guest_chat', 'guest_chat_reply'];
}

function notificationEventLabels(): array
{
    return [
        'ticket_assigned' => __('Ticket Assigned'),
        'ticket_created' => __('Ticket Created'),
        'ticket_comment' => __('Ticket Comment'),
        'new_message' => __('New Message'),
        'ticket_status_updated' => __('Ticket Status Updated'),
        'document_uploaded' => __('Document Uploaded'),
        'staff_welcome' => __('Staff Welcome'),
        'tenant_welcome' => __('Tenant Welcome'),
        'password_reset' => __('Password Reset'),
        'onboarding' => __('Onboarding'),
        'new_application' => __('New Application'),
        'new_guest_chat' => __('New Guest Chat'),
        'guest_chat_reply' => __('Guest Chat Reply'),
    ];
}

function inappNotificationMode(): string
{
    try {
        $row = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'inapp_notification_mode'");
        return $row['value'] ?? 'default';
    } catch (\Throwable $e) {
        return 'default';
    }
}

function emailNotificationMode(): string
{
    try {
        $row = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'notification_mode'");
        return $row['value'] ?? 'default';
    } catch (\Throwable $e) {
        return 'default';
    }
}

function shouldNotifyEmail(string $event, string $role): bool
{
    try {
        if (emailNotificationMode() === 'custom') {
            $row = \App\Core\Database::fetch(
                "SELECT 1 FROM notification_events WHERE event = ? AND role = ?",
                [$event, $role]
            );
            return $row !== null;
        }
    } catch (\Throwable $e) {
        // Fall back to defaults if the table is not available yet.
    }
    return in_array($role, defaultNotifications()[$event] ?? [], true);
}

function shouldNotifyInApp(string $event, string $role): bool
{
    try {
        if (inappNotificationMode() === 'custom') {
            $row = \App\Core\Database::fetch(
                "SELECT 1 FROM inapp_notification_events WHERE event = ? AND role = ?",
                [$event, $role]
            );
            return $row !== null;
        }
    } catch (\Throwable $e) {
        // Fall back to defaults if the table is not available yet.
    }
    return in_array($role, defaultNotifications()[$event] ?? [], true);
}

function notifyInApp(int $userId, string $event, string $data, ?string $type = null, ?string $roleOverride = null): void
{
    try {
        $user = \App\Core\Database::fetch("SELECT role FROM users WHERE id = ? AND archived_at IS NULL", [$userId]);
        if (!$user || !shouldNotifyInApp($event, $roleOverride ?? (string) $user['role'])) {
            return;
        }

        \App\Core\Database::execute(
            "INSERT INTO notifications (user_id, type, data, created_at) VALUES (?, ?, ?, NOW())",
            [$userId, $type ?? $event, $data]
        );
        log_activity('notifications.sent', "In-app notification '{$event}' sent to user #{$userId}");
    } catch (\Throwable $e) {
        \App\Core\Logger::error('In-app notification failed: ' . $e->getMessage());
    }
}

function notifyInAppRoles(string $event, string $data, ?int $excludeUserId = null): void
{
    try {
        $users = \App\Core\Database::fetchAll("SELECT id FROM users WHERE archived_at IS NULL", []);
        foreach ($users as $user) {
            if ($excludeUserId !== null && (int)$user['id'] === $excludeUserId) {
                continue;
            }
            notifyInApp((int)$user['id'], $event, $data);
        }
    } catch (\Throwable $e) {
        \App\Core\Logger::error('In-app role notification failed: ' . $e->getMessage());
    }
}

/**
 * Recipients for a property-scoped notification: the property's manager, or
 * its landlord when no manager is set. The property-manager setting governs
 * the notification in either case, so the fallback is always honoured.
 *
 * @return array<int, array<string, mixed>>
 */
function propertyNotificationRecipients(int $propertyId, string $event, string $channel): array
{
    $gateRole = 'property_manager';
    $allowed = $channel === 'email'
        ? shouldNotifyEmail($event, $gateRole)
        : shouldNotifyInApp($event, $gateRole);
    if (!$allowed) {
        return [];
    }

    try {
        $property = \App\Core\Database::fetch(
            "SELECT landlord_id, property_manager_id FROM properties WHERE id = ? AND archived_at IS NULL",
            [$propertyId]
        );
        if ($property === null) {
            return [];
        }
        $managerId = (int) ($property['property_manager_id'] ?? 0);
        $landlordId = (int) ($property['landlord_id'] ?? 0);
        $recipientId = $managerId > 0 ? $managerId : $landlordId;
        if ($recipientId <= 0) {
            return [];
        }
        $user = \App\Core\Database::fetch(
            "SELECT id, name, email, role FROM users WHERE id = ? AND archived_at IS NULL",
            [$recipientId]
        );
        if ($user === null || ($user['role'] ?? '') === 'tenant') {
            return [];
        }
        return [$user];
    } catch (\Throwable $e) {
        \App\Core\Logger::error('Property notification recipient lookup failed: ' . $e->getMessage());
        return [];
    }
}

function site_url(): string
{
    try {
        $row = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'site_url'");
        if ($row && $row['value'] !== '') {
            return rtrim($row['value'], '/');
        }
    } catch (\Throwable $e) {
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function db_timestamp_epoch(?string $datetime): ?int
{
    if (!$datetime) {
        return null;
    }
    try {
        return (new \DateTime($datetime, new \DateTimeZone('UTC')))->getTimestamp();
    } catch (\Throwable $e) {
        return null;
    }
}

function display_time(?string $datetime, string $format = 'M j, Y g:i A'): string
{
    if (!$datetime) {
        return '';
    }
    try {
        $dt = new \DateTime($datetime, new \DateTimeZone('UTC'));
        $dt->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        return $dt->format($format);
    } catch (\Throwable $e) {
        return h($datetime);
    }
}
