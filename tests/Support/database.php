<?php

/**
 * Create a clean test database, load the schema, and inject its PDO into the
 * app's Database singleton. Injection bypasses Database::loadEnv(), so a
 * developer's .env can never point integration tests at real data.
 */
function turtle_test_database(string $database = 'turtle_test'): \PDO
{
    static $connections = [];

    if (isset($connections[$database])) {
        return $connections[$database];
    }

    $host = getenv('DB_HOST') ?: 'mysql';
    $port = getenv('DB_PORT') ?: '3306';
    $rootPass = getenv('MYSQL_ROOT_PASSWORD') ?: 'root';

    $options = [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $root = new \PDO("mysql:host={$host};port={$port};charset=utf8mb4", 'root', $rootPass, $options);
    $root->exec("DROP DATABASE IF EXISTS `{$database}`");
    $root->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdo = new \PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        'root',
        $rootPass,
        $options
    );

    $schema = (string) file_get_contents(base_path('database/schema.sql'));
    foreach (\App\Core\Database::splitSql($schema) as $statement) {
        $pdo->exec($statement);
    }

    $reflection = new \ReflectionClass(\App\Core\Database::class);
    $instance = $reflection->newInstanceWithoutConstructor();

    $pdoProperty = $reflection->getProperty('pdo');
    $pdoProperty->setValue($instance, $pdo);

    $instanceProperty = $reflection->getProperty('instance');
    $instanceProperty->setValue(null, $instance);

    return $connections[$database] = $pdo;
}
