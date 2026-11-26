<?php

/**
 * Create the Turtle database and application users for the HA add-on.
 *
 * The password arrives through the environment (never argv) and is escaped
 * with PDO::quote() before being embedded in DDL: MySQL does not allow bound
 * parameters for passwords in CREATE/ALTER USER. Building this SQL in a shell
 * heredoc let quotes, backslashes or `;` in an operator option alter/inject
 * statements, and CREATE USER IF NOT EXISTS meant a rotated password never
 * took effect. This helper always reapplies the configured password.
 */

declare(strict_types=1);

$socket = trim((string)(getenv('DB_SOCKET') ?: '/tmp/mysql.sock'));
$database = 'turtle';
$password = (string)(getenv('DB_PASSWORD') ?: '');

try {
    $pdo = new PDO(
        "mysql:unix_socket={$socket};charset=utf8mb4",
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Escapes quotes/backslashes for this connection's charset so the value
    // remains a single SQL string literal.
    $quotedPassword = $pdo->quote($password);

    foreach (['localhost', '127.0.0.1'] as $host) {
        $account = "'turtle'@'{$host}'";
        $pdo->exec("CREATE USER IF NOT EXISTS {$account} IDENTIFIED BY {$quotedPassword}");
        // Always reapply so changing db_password in the add-on UI works.
        $pdo->exec("ALTER USER {$account} IDENTIFIED BY {$quotedPassword}");
        $pdo->exec("GRANT ALL PRIVILEGES ON `{$database}`.* TO {$account}");
    }

    $pdo->exec('FLUSH PRIVILEGES');
} catch (Throwable $e) {
    fwrite(STDERR, 'Database setup failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
