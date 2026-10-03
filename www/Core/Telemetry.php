<?php

namespace App\Core;

/**
 * Anonymous usage telemetry — opt-out (default ON).
 *
 * Sends system + application context ONLY to PostHog Cloud via the
 * public write-only capture API (phc_ key is safe to ship in code —
 * unlike the old GitHub PAT, it cannot be revoked by secret scanning).
 * Never collects users, tenants, properties, tickets, names, emails,
 * URLs, or IPs (sender IP is visible at transport level; project is
 * configured to discard IP data before storage).
 *
 * Design constraints (production safety):
 * - All methods are fail-silent: never throw, never break page loads.
 * - At most one ping per 24h per install (gated by settings.last_telemetry_sent).
 * - Fully self-contained: the public capture key below is the only
 *   credential and it lives here, once. No per-deploy setup —
 *   Docker and Home Assistant run identical code.
 * - Short timeouts (5s) so slow/unreachable capture host can't hang requests.
 */
class Telemetry
{
    /** PostHog Cloud capture host. Override with env POSTHOG_HOST for EU (https://eu.i.posthog.com). */
    public const POSTHOG_HOST = 'https://us.i.posthog.com';
    /**
     * Public write-only Project API key. Safe to embed — it can only
     * capture anonymous events, never read data or manage the project.
     */
    public const POSTHOG_KEY = 'phc_ySQFxrsfwFLVp2J32mAo5FzyoGHC3znmKp6hkPUobw5H';
    public const EVENT = 'turtle-ping';
    /** @deprecated Kept for backward compatibility; no longer used as ingest target. */
    public const DEFAULT_REPO = 'jessica12ryan/Turtle-Stats';
    /** @deprecated Kept for backward compatibility; GitHub dispatch removed (PAT revoked by secret scanning). */
    public const DISPATCH_EVENT = 'turtle-ping';
    public const INTERVAL_SECONDS = 86400;
    public const TIMEOUT_SECONDS = 5;

    /**
     * Opt-out check. Default ON unless explicitly disabled.
     * Env TELEMETRY_ENABLED=0/false/off/no disables globally (CI/dev).
     */
    public static function isEnabled(): bool
    {
        try {
            $env = getenv('TELEMETRY_ENABLED');
            if ($env !== false) {
                $v = strtolower(trim((string) $env));
                if (in_array($v, ['0', 'false', 'off', 'no', 'disabled'], true)) {
                    return false;
                }
            }

            $row = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'telemetry_enabled'");
            if ($row === null) {
                return true; // settings row missing (pre-migration) => default ON
            }
            return ($row['value'] ?? '1') !== '0';
        } catch (\Throwable $e) {
            // Fail open-safe: if DB is down (setup/boot), treat as disabled
            // so we never break setup or error pages. Daily hook retries later.
            return false;
        }
    }

    public static function setEnabled(bool $enabled): void
    {
        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES ('telemetry_enabled', ?) ON DUPLICATE KEY UPDATE `value` = ?",
            [$enabled ? '1' : '0', $enabled ? '1' : '0']
        );
    }

    /**
     * Stable anonymous install identifier. Generated once, stored in settings.
     * Random UUID v4 — not derived from any user/system data.
     * Self-healing: a non-empty value that fails validation (e.g. written by
     * an older build or edited by hand) is replaced with a fresh UUID once;
     * valid IDs are never rotated.
     */
    public static function installId(): string
    {
        $corrupt = false;
        try {
            $row = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'telemetry_install_id'");
            $existing = trim($row['value'] ?? '');
            if ($existing !== '' && preg_match('/^[A-Za-z0-9\-_]{8,64}$/', $existing)) {
                return $existing;
            }
            $corrupt = ($existing !== '');
        } catch (\Throwable $e) {
            return '';
        }

        try {
            $uuid = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0x0fff) | 0x4000,
                random_int(0, 0x3fff) | 0x8000,
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff)
            );
            if ($corrupt) {
                // Repair path: overwrite the invalid value exactly once so a
                // corrupt ID can't permanently wedge telemetry into silent-off.
                Database::execute(
                    "INSERT INTO settings (`key`, `value`) VALUES ('telemetry_install_id', ?) ON DUPLICATE KEY UPDATE `value` = ?",
                    [$uuid, $uuid]
                );
                error_log('Telemetry install_id regenerated (previous value had invalid format).');
            } else {
                // First-run path: keep first-writer on concurrent boots.
                Database::execute(
                    "INSERT INTO settings (`key`, `value`) VALUES ('telemetry_install_id', ?) ON DUPLICATE KEY UPDATE `value` = IF(`value` = '', ?, `value`)",
                    [$uuid, $uuid]
                );
            }
            $row = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'telemetry_install_id'");
            return trim($row['value'] ?? $uuid);
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function captureHost(): string
    {
        try {
            $env = getenv('POSTHOG_HOST');
            if ($env !== false && preg_match('#^https://[A-Za-z0-9.\-]+$#', trim((string) $env))) {
                return rtrim(trim((string) $env), '/');
            }
        } catch (\Throwable $e) {
        }
        return self::POSTHOG_HOST;
    }

    public static function captureKey(): string
    {
        try {
            $env = getenv('POSTHOG_KEY');
            if ($env !== false && preg_match('/^phc_[A-Za-z0-9]{10,128}$/', trim((string) $env))) {
                return trim((string) $env);
            }
        } catch (\Throwable $e) {
        }
        return self::POSTHOG_KEY;
    }

    /** @deprecated GitHub ingest removed; returns legacy repo name for BC only. */
    public static function repo(): string
    {
        return self::DEFAULT_REPO;
    }

    /** @deprecated GitHub PAT removed (revoked by secret scanning); always returns ''. */
    public static function token(): string
    {
        return '';
    }

    /**
     * Detect runtime without leaking host details.
     * Returns one of: ha-addon, docker, unknown.
     */
    public static function runtime(): string
    {
        try {
            if (getenv('SUPERVISOR_TOKEN') !== false || getenv('HASSIO') !== false) {
                return 'ha-addon';
            }
            // HA add-on persists /data; Docker compose does not mount /data
            if (is_dir('/data/logs') || is_dir('/data')) {
                return 'ha-addon';
            }
        } catch (\Throwable $e) {
        }
        // Default: docker compose / bare metal apache
        if (is_file('/.dockerenv')) {
            return 'docker';
        }
        return 'unknown';
    }

    /**
     * Build the anonymous payload. No PII, no business data.
     * Shape is additive-only: existing keys are never renamed/removed.
     */
    public static function payload(): array
    {
        $appVersion = '0.0.0';
        $channel = 'stable';
        $country = '';
        $language = '';
        $timezone = '';
        try {
            $row = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'app_version'");
            if ($row && trim($row['value'] ?? '') !== '') {
                $appVersion = substr(trim($row['value']), 0, 32);
            }
            $row = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'update_channel'");
            if ($row && in_array($row['value'] ?? '', ['stable', 'development'], true)) {
                $channel = $row['value'];
            }
            $row = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'default_country'");
            if ($row && in_array($row['value'] ?? '', ['CA', 'US'], true)) {
                $country = $row['value'];
            }
            $row = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'default_language'");
            if ($row && in_array($row['value'] ?? '', ['en', 'fr', 'es'], true)) {
                $language = $row['value'];
            }
            $row = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'timezone'");
            $tz = trim($row['value'] ?? '');
            if ($tz !== '' && preg_match('#^[A-Za-z0-9_+\-/]{1,48}$#', $tz)) {
                $timezone = $tz;
            }
        } catch (\Throwable $e) {
        }

        // Git branch/commit — best effort, never fatal
        $branch = '';
        $commit = '';
        try {
            $repoRoot = dirname(__DIR__, 2);
            $safe = escapeshellarg($repoRoot);
            $b = @shell_exec("git -c safe.directory={$safe} -C {$safe} rev-parse --abbrev-ref HEAD 2>/dev/null");
            if (is_string($b) && trim($b) !== '') {
                $branch = substr(trim($b), 0, 64);
            }
            $c = @shell_exec("git -c safe.directory={$safe} -C {$safe} rev-parse --short HEAD 2>/dev/null");
            if (is_string($c) && trim($c) !== '') {
                $commit = substr(trim($c), 0, 40);
            }
        } catch (\Throwable $e) {
        }

        $os = '';
        $arch = '';
        try {
            $os = substr((string) php_uname('s'), 0, 32);
            $arch = substr((string) php_uname('m'), 0, 32);
        } catch (\Throwable $e) {
        }

        return [
            'install_id' => self::installId(),
            'app_version' => $appVersion,
            'channel' => $channel,
            'branch' => $branch,
            'commit' => $commit,
            'php' => substr(PHP_VERSION, 0, 16),
            'sapi' => substr(PHP_SAPI, 0, 16),
            'os' => $os,
            'arch' => $arch,
            'runtime' => self::runtime(),
            'country' => $country,
            'language' => $language,
            'timezone' => $timezone,
            '$lib' => 'turtle-php',
            '$lib_version' => substr($appVersion, 0, 32),
            'sent_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }

    private static function dueForSend(): bool
    {
        try {
            $row = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'last_telemetry_sent'");
            $last = trim($row['value'] ?? '');
            if ($last === '') {
                return true;
            }
            $lastTs = strtotime($last);
            if ($lastTs === false) {
                return true;
            }
            return (time() - $lastTs) >= self::INTERVAL_SECONDS;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function markSent(): void
    {
        try {
            $now = date('Y-m-d H:i:s');
            Database::execute(
                "INSERT INTO settings (`key`, `value`) VALUES ('last_telemetry_sent', ?) ON DUPLICATE KEY UPDATE `value` = ?",
                [$now, $now]
            );
        } catch (\Throwable $e) {
        }
    }

    /**
     * Opportunistic daily send. Never throws. Returns true on capture accept.
     *
     * @param bool $force Bypass the 24h gate (used only for manual "Send now" testing; still respects opt-out + key).
     */
    public static function maybeSend(bool $force = false): bool
    {
        try {
            if (!self::isEnabled()) {
                return false;
            }
            $apiKey = self::captureKey();
            if ($apiKey === '') {
                return false; // telemetry fully unconfigured — silent, no error spam
            }
            if (!$force && !self::dueForSend()) {
                return false;
            }

            $payload = self::payload();
            $installId = $payload['install_id'] ?? '';
            if (!is_string($installId) || !preg_match('/^[A-Za-z0-9\-_]{8,64}$/', $installId)) {
                return false; // rate-limit/validation: malformed identity never leaves the box
            }

            $url = self::captureHost() . '/capture/';
            $body = json_encode([
                'api_key' => $apiKey,
                'event' => self::EVENT,
                'distinct_id' => $installId,
                'properties' => $payload,
                'timestamp' => $payload['sent_at'] ?? gmdate('Y-m-d\TH:i:s\Z'),
            ]);
            if ($body === false) {
                return false;
            }

            $result = function_exists('httpPostJson')
                ? @httpPostJson($url, $body, [
                    'Accept: application/json',
                    'User-Agent: Turtle-Telemetry/1.0',
                ], self::TIMEOUT_SECONDS)
                : null;

            // PostHog /capture/ returns 200 with {"status":1} on success
            if (is_array($result) && ($result['http_code'] ?? 0) >= 200 && ($result['http_code'] ?? 0) < 300) {
                self::markSent();
                return true;
            }

            // Log at most a one-liner; never include key, install_id, or payload
            if (is_array($result)) {
                error_log('Telemetry capture failed: HTTP ' . ($result['http_code'] ?? '?'));
            }
            return false;
        } catch (\Throwable $e) {
            error_log('Telemetry maybeSend failed: ' . $e->getMessage());
            return false;
        }
    }
}
