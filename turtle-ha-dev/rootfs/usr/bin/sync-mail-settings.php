<?php

/**
 * Sync Home Assistant add-on mail options into the Turtle settings table.
 *
 * Runs during add-on boot, after the schema/migrations. Values arrive through
 * the environment (never argv) so secrets do not show up in `ps`. Prepared
 * statements keep passwords containing quotes, backslashes or newlines intact.
 *
 * Update policy:
 *  - Explicitly configured options update the database.
 *  - Empty username/password/OAuth options leave the database value untouched,
 *    so values set in Settings -> Email survive restarts.
 *  - Host/port/from defaults are only inserted when missing, so a UI change is
 *    not overwritten by the add-on defaults on every boot.
 */

declare(strict_types=1);

function rawEnv(string $key): string
{
    $value = getenv($key);
    return $value === false ? '' : $value;
}

function textEnv(string $key): string
{
    return trim(rawEnv($key));
}

function isExplicit(string $key): bool
{
    return textEnv($key) === '1';
}

function upsertSetting(PDO $pdo, string $key, string $value, bool $onlyIfMissing): void
{
    if ($onlyIfMissing) {
        $statement = $pdo->prepare('INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)');
    } else {
        $statement = $pdo->prepare(
            'INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
    }
    $statement->execute([$key, $value]);
}

$socket = textEnv('DB_SOCKET') ?: '/tmp/mysql.sock';
$database = textEnv('DB_DATABASE') ?: 'turtle';
$username = textEnv('DB_USERNAME') ?: 'turtle';
$password = rawEnv('DB_PASSWORD');

try {
    $pdo = new PDO(
        "mysql:unix_socket={$socket};dbname={$database};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // Host and port: an explicitly configured add-on option wins; the derived
    // local-Mailpit defaults only seed a fresh install.
    $host = textEnv('TURTLE_MAIL_HOST');
    if ($host !== '') {
        upsertSetting($pdo, 'mail_host', $host, !isExplicit('TURTLE_MAIL_HOST_EXPLICIT'));
    }

    $port = textEnv('TURTLE_MAIL_PORT');
    if ($port !== '' && ctype_digit($port) && (int) $port >= 1 && (int) $port <= 65535) {
        upsertSetting($pdo, 'mail_port', $port, !isExplicit('TURTLE_MAIL_PORT_EXPLICIT'));
    }

    // Credentials are only applied when supplied. Empty add-on options must not
    // wipe a mail account configured in the UI.
    $mailUsername = rawEnv('TURTLE_MAIL_USERNAME');
    if ($mailUsername !== '') {
        upsertSetting($pdo, 'mail_username', $mailUsername, false);
    }

    $mailPassword = rawEnv('TURTLE_MAIL_PASSWORD');
    if ($mailPassword !== '') {
        upsertSetting($pdo, 'mail_password', $mailPassword, false);
    }

    $fromAddress = textEnv('TURTLE_MAIL_FROM_ADDRESS');
    if ($fromAddress !== '') {
        upsertSetting($pdo, 'mail_from_address', $fromAddress, !isExplicit('TURTLE_MAIL_FROM_EXPLICIT'));
    }

    // OAuth settings are optional and only synced when explicitly provided.
    $oauthSettings = [
        'mail_auth_mode' => 'TURTLE_MAIL_AUTH_MODE',
        'mail_oauth_client_id' => 'TURTLE_MAIL_OAUTH_CLIENT_ID',
        'mail_oauth_client_secret' => 'TURTLE_MAIL_OAUTH_CLIENT_SECRET',
        'mail_oauth_refresh_token' => 'TURTLE_MAIL_OAUTH_REFRESH_TOKEN',
        'mail_oauth_email' => 'TURTLE_MAIL_OAUTH_EMAIL',
    ];
    foreach ($oauthSettings as $settingKey => $envKey) {
        $value = rawEnv($envKey);
        if ($value !== '') {
            upsertSetting($pdo, $settingKey, $value, false);
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, "sync-mail-settings: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Mail settings synced.\n";
