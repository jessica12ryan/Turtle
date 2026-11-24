<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $this->loadEnv();

        $host = getenv('DB_HOST') ?: 'mysql';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_DATABASE') ?: 'turtle';
        $user = getenv('DB_USERNAME') ?: 'turtle';
        $pass = getenv('DB_PASSWORD') ?: 'turtle';
        $socket = getenv('DB_SOCKET') ?: '';

        $dsn = $socket
            ? "mysql:unix_socket={$socket};dbname={$dbname};charset=utf8mb4"
            : "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            \App\Core\Logger::error("Database connection failed: DSN={$dsn} user={$user} error=" . $e->getMessage());
            throw new PDOException("Database connection failed: " . $e->getMessage());
        }
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadEnv(): void
    {
        // Unified loader (single source of truth in functions.php)
        if (function_exists('loadEnvFile')) {
            loadEnvFile(__DIR__ . '/../../.env');
            return;
        }
        $envFile = __DIR__ . '/../../.env';
        if (!file_exists($envFile)) {
            return;
        }
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
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

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::instance()->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::query($sql, $params);
        return (int) self::instance()->pdo->lastInsertId();
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Split a SQL dump into statements, respecting quoted strings and comments.
     * Unlike explode(';') this does not break on semicolons inside data.
     *
     * @return list<string>
     */
    public static function splitSql(string $sql): array
    {
        $statements = [];
        $current = '';
        $length = strlen($sql);
        $quote = null;
        $lineComment = false;
        $blockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($lineComment) {
                $current .= $char;
                if ($char === "\n") {
                    $lineComment = false;
                }
                continue;
            }
            if ($blockComment) {
                $current .= $char;
                if ($char === '*' && $next === '/') {
                    $current .= $next;
                    $i++;
                    $blockComment = false;
                }
                continue;
            }
            if ($quote !== null) {
                $current .= $char;
                if ($char === '\\' && $next !== '') {
                    $current .= $next;
                    $i++;
                } elseif ($char === $quote) {
                    if ($next === $quote) {
                        $current .= $next;
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) {
                $lineComment = true;
                $current .= $char;
                continue;
            }
            if ($char === '#') {
                $lineComment = true;
                $current .= $char;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $blockComment = true;
                $current .= $char;
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $current .= $char;
                continue;
            }
            if ($char === ';') {
                $statement = trim($current);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $current = '';
                continue;
            }
            $current .= $char;
        }

        $statement = trim($current);
        if ($statement !== '') {
            $statements[] = $statement;
        }
        return $statements;
    }

    /** Execute every statement in a dump, aborting on the first error. */
    public static function executeSql(string $sql): void
    {
        foreach (self::splitSql($sql) as $statement) {
            $trimmed = ltrim($statement);
            if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                continue;
            }
            self::instance()->pdo->exec($statement);
        }
    }

    /** Logical backup of every table, reused by export and restore rollback. */
    public static function dumpSql(): string
    {
        $pdo = self::instance()->getConnection();
        $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);

        $sql = "-- Turtle Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $table) {
            $row = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_NUM);
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sql .= $row[1] . ";\n\n";

            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
            if ($rows === []) {
                continue;
            }
            $columns = array_keys($rows[0]);
            $colList = '`' . implode('`,`', $columns) . '`';
            foreach ($rows as $row) {
                $values = [];
                foreach ($columns as $column) {
                    $value = $row[$column];
                    $values[] = $value === null ? 'NULL' : $pdo->quote((string)$value);
                }
                $sql .= "INSERT INTO `{$table}` ({$colList}) VALUES (" . implode(', ', $values) . ");\n";
            }
            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        return $sql;
    }

    public static function beginTransaction(): void
    {
        self::instance()->pdo->beginTransaction();
    }

    public static function commit(): void
    {
        self::instance()->pdo->commit();
    }

    public static function rollback(): void
    {
        if (self::instance()->pdo->inTransaction()) {
            self::instance()->pdo->rollBack();
        }
    }
}
