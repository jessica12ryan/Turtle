<?php

namespace App\Core;

/**
 * Discovers and tails the log files written by PHP, Apache and MySQL/MariaDB.
 *
 * Deployments keep logs in different places:
 *   - Docker: the app writes into storage/logs/, MySQL into its data volume
 *   - HA add-on: Apache/PHP use /data/logs/, MariaDB uses /data/mysql/
 *   - Bare metal: the usual /var/log/... locations
 *
 * The old viewer only probed a short list of absolute system paths, so none of
 * the containerised logs were ever found. All discovery now lives here so the
 * settings page and the download endpoint stay in sync.
 */
class LogReader
{
    public const TAIL_LINES = 100;

    /** Paths that are checked in order; the first readable one is the "primary" log. */
    public static function phpCandidates(): array
    {
        $configured = ini_get('error_log');
        $candidates = [];

        // "stderr", "syslog" and "" are destinations, not files.
        if (is_string($configured) && $configured !== '' && !in_array(strtolower($configured), ['stderr', 'stdout', 'syslog'], true)) {
            $candidates[] = $configured;
        }

        return array_merge($candidates, [
            base_path('storage/logs/php-error.log'),
            '/data/logs/php-error.log',
            '/data/logs/apache_error.log',
            '/var/log/php_errors.log',
            'glob:' . base_path('storage/logs/*php*.log'),
            'glob:/data/logs/*php*.log',
        ]);
    }

    public static function apacheCandidates(): array
    {
        return [
            base_path('storage/logs/apache_error.log'),
            base_path('storage/logs/apache_access.log'),
            '/data/logs/apache_error.log',
            '/data/logs/apache_access.log',
            '/var/log/apache2/error.log',
            '/var/log/apache2/access.log',
            '/var/log/httpd/error_log',
            '/var/log/httpd/access_log',
            'glob:' . base_path('storage/logs/apache*.log'),
            'glob:/data/logs/apache*.log',
        ];
    }

    public static function mysqlCandidates(): array
    {
        return [
            base_path('storage/logs/mysql-error.log'),
            base_path('storage/logs/mysql-general.log'),
            '/var/lib/mysql/mysql-error.log',
            '/var/lib/mysql/mysql-general.log',
            '/data/mysql/mariadb-error.log',
            '/var/log/mysql/error.log',
            '/var/log/mysql/mysql.log',
            '/var/log/mariadb/mariadb.log',
            'glob:/data/mysql/*.err',
            'glob:' . base_path('storage/logs/mysql*.log'),
        ];
    }

    /**
     * The path shown in the UI, even when the file does not exist yet.
     */
    public static function primaryPath(array $candidates): string
    {
        foreach (self::expand($candidates) as $path) {
            if (!str_starts_with($path, 'glob:') && self::isRealFile($path)) {
                return $path;
            }
        }
        foreach ($candidates as $path) {
            if (!str_starts_with($path, 'glob:')) {
                return $path;
            }
        }
        return '';
    }

    /**
     * Read the last N lines of every available candidate.
     *
     * @return list<array{path: string, name: string, lines: array<int, string>}>
     */
    public static function read(array $candidates, int $limit = self::TAIL_LINES): array
    {
        $entries = [];
        $seen = [];

        foreach (self::expand($candidates) as $path) {
            if (!self::isReadableFile($path)) {
                continue;
            }
            $real = realpath($path);
            if ($real === false || isset($seen[$real])) {
                continue;
            }
            $seen[$real] = true;
            $entries[] = [
                'path' => $path,
                'name' => basename($path),
                'lines' => self::tail($path, $limit),
            ];
        }

        return $entries;
    }

    /**
     * Full contents of every available log, used by the download endpoints.
     */
    public static function contents(array $candidates): string
    {
        $out = '';
        $seen = [];

        foreach (self::expand($candidates) as $path) {
            if (!self::isReadableFile($path)) {
                continue;
            }
            $real = realpath($path);
            if ($real === false || isset($seen[$real])) {
                continue;
            }
            $seen[$real] = true;
            $content = @file_get_contents($path);
            if ($content === false) {
                continue;
            }
            if ($out !== '') {
                $out .= "\n";
            }
            $out .= "=== {$path} ===\n\n" . rtrim($content, "\r\n") . "\n";
        }

        return $out;
    }

    /**
     * Memory-safe tail: only the trailing 8 KiB chunks are read, which matters
     * for Apache access logs that can grow very large.
     *
     * @return array<int, string>
     */
    public static function tail(string $path, int $limit = self::TAIL_LINES): array
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $size = (int)(fstat($handle)['size'] ?? 0);
        if ($size <= 0) {
            fclose($handle);
            return [];
        }

        $chunk = 8192;
        $position = $size;
        $data = '';

        // Stop once we have more than $limit newlines (the extra covers a
        // partially read first line and a trailing newline).
        while ($position > 0 && substr_count($data, "\n") <= $limit) {
            $read = min($chunk, $position);
            $position -= $read;
            if (fseek($handle, $position) !== 0) {
                break;
            }
            $piece = fread($handle, $read);
            if ($piece === false) {
                break;
            }
            $data = $piece . $data;
        }
        fclose($handle);

        $lines = preg_split('/\r\n|\r|\n/', $data) ?: [];
        if (end($lines) === '') {
            array_pop($lines);
        }

        return array_slice($lines, -$limit);
    }

    private static function expand(array $candidates): array
    {
        $paths = [];
        foreach ($candidates as $candidate) {
            if (str_starts_with($candidate, 'glob:')) {
                $matches = glob(substr($candidate, 5));
                if ($matches) {
                    sort($matches);
                    foreach ($matches as $match) {
                        $paths[] = $match;
                    }
                }
                continue;
            }
            $paths[] = $candidate;
        }
        return $paths;
    }

    /**
     * Real files only. This deliberately rejects the symlinks that php:apache
     * ships for /var/log/apache2/* — they point at /dev/stdout and /dev/stderr,
     * which are live pipes with no readable history.
     */
    private static function isRealFile(string $path): bool
    {
        // Log files are written while the page renders; avoid a stale stat.
        clearstatcache(true, $path);
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }
        $real = realpath($path);
        if ($real === false) {
            return false;
        }
        foreach (['/dev/', '/proc/', '/sys/'] as $prefix) {
            if (str_starts_with($real, $prefix)) {
                return false;
            }
        }
        return true;
    }

    private static function isReadableFile(string $path): bool
    {
        return self::isRealFile($path) && (filesize($path) > 0);
    }
}
