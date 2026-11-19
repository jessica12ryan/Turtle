<?php

namespace App\Core;

/**
 * Severity-aware logging.
 *
 * The "Log Level" setting in Settings -> Logging used to be stored but never
 * applied. This class makes it authoritative: PHP diagnostics are filtered
 * via error_reporting(), the front-controller error handler filters before
 * writing, and application messages all go through Logger::error().
 */
class Logger
{
    /** Lower number = more verbose. */
    public const LEVELS = [
        'debug' => 10,
        'info' => 20,
        'notice' => 30,
        'warning' => 40,
        'error' => 50,
    ];

    private static ?string $level = null;

    /**
     * The configured minimum severity. Falls back to debug when the settings
     * table is not available yet (setup, migrations, CLI before first boot).
     */
    public static function level(): string
    {
        if (self::$level === null) {
            // Set the default first: a failed DB connection logs through this
            // class, so a re-entrant level() call must not recurse forever.
            self::$level = 'debug';
            try {
                $row = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'log_level'");
                $value = strtolower(trim((string)($row['value'] ?? '')));
                if (isset(self::LEVELS[$value])) {
                    self::$level = $value;
                }
            } catch (\Throwable $e) {
                // Database not ready — keep the verbose default.
            }
        }
        return self::$level;
    }

    public static function enabled(string $level): bool
    {
        $wanted = self::LEVELS[strtolower($level)] ?? self::LEVELS['error'];
        return $wanted >= (self::LEVELS[self::level()] ?? self::LEVELS['debug']);
    }

    public static function log(string $level, string $message): void
    {
        if (!self::enabled($level)) {
            return;
        }
        \error_log('[' . strtoupper($level) . '] ' . $message);
    }

    public static function debug(string $message): void
    {
        self::log('debug', $message);
    }

    public static function info(string $message): void
    {
        self::log('info', $message);
    }

    public static function notice(string $message): void
    {
        self::log('notice', $message);
    }

    public static function warning(string $message): void
    {
        self::log('warning', $message);
    }

    public static function error(string $message): void
    {
        self::log('error', $message);
    }

    /**
     * Turn a PHP error severity into one of our level names.
     */
    public static function levelForPhpSeverity(int $severity): string
    {
        return match ($severity) {
            E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR => 'error',
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'warning',
            E_NOTICE, E_USER_NOTICE => 'notice',
            E_DEPRECATED, E_USER_DEPRECATED, E_STRICT => 'debug',
            default => 'error',
        };
    }

    /**
     * PHP's own error_reporting() mask for the configured level.
     */
    public static function errorReporting(): int
    {
        return match (self::level()) {
            'info' => E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED,
            'notice' => E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_STRICT,
            'warning' => E_ERROR | E_WARNING | E_PARSE | E_CORE_ERROR | E_CORE_WARNING
                | E_COMPILE_ERROR | E_COMPILE_WARNING | E_USER_ERROR | E_USER_WARNING
                | E_RECOVERABLE_ERROR,
            'error' => E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR
                | E_USER_ERROR | E_RECOVERABLE_ERROR,
            default => E_ALL,
        };
    }

    /**
     * Apply the setting to PHP's error configuration. Called once per request
     * from index.php; safe to call again after the setting changes.
     */
    public static function apply(): void
    {
        error_reporting(self::errorReporting());
        ini_set('log_errors', '1');
        ini_set('display_errors', '0');
    }
}
