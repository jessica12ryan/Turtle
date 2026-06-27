<?php
$file = '/var/www/turtle/www/Core/Database.php';
$src = file_get_contents($file);

$old = '$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";';
$new = '$socket = $_ENV[\'DB_SOCKET\'] ?? \'\';' . "\n" .
       '        $dsn = $socket' . "\n" .
       '            ? "mysql:unix_socket={$socket};dbname={$dbname};charset=utf8mb4"' . "\n" .
       '            : "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";';

if (strpos($src, $old) === false) {
    echo "Already patched or string not found, skipping.\n";
    exit(0);
}

file_put_contents($file, str_replace($old, $new, $src));
echo "Database.php patched successfully.\n";
