<?php

namespace App\Core;

/**
 * Support for visitor conversations started from the public listings page.
 *
 * Visitors are not application users: they are identified by a high-entropy
 * token kept in a cookie and (optionally) in their saved conversation URL.
 * Only a SHA-256 hash of that token is stored in the database.
 */
class GuestChat
{
    public const COOKIE_PREFIX = 'turtle_chat_';
    public const MAX_COOKIES = 50;

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function isValidToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?array
    {
        if (!self::isValidToken($token)) {
            return null;
        }
        $chat = Database::fetch(
            "SELECT gc.*, p.name AS property_name, gct.token_hash AS matched_hash
             FROM guest_chat_tokens gct
             JOIN guest_chats gc ON gc.id = gct.chat_id
             JOIN properties p ON p.id = gc.property_id
             WHERE gct.token_hash = ?",
            [self::hashToken($token)]
        );
        if ($chat !== null && !hash_equals($chat['matched_hash'], self::hashToken($token))) {
            return null;
        }
        return $chat;
    }

    public static function findById(int $id): ?array
    {
        return Database::fetch(
            "SELECT gc.*, p.name AS property_name
             FROM guest_chats gc
             JOIN properties p ON p.id = gc.property_id
             WHERE gc.id = ?",
            [$id]
        );
    }

    public static function setCookie(int $chatId, string $token): void
    {
        if (headers_sent() || !self::isValidToken($token)) {
            return;
        }
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
        setcookie(self::COOKIE_PREFIX . $chatId, $token, [
            'expires' => time() + 60 * 60 * 24 * 365,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Visitor chats this browser has a valid token cookie for.
     *
     * @return array<int, array{id:int, property_id:int, token:string}>
     */
    public static function cookieChats(): array
    {
        $candidates = [];
        $prefix = '/^' . preg_quote(self::COOKIE_PREFIX, '/') . '([1-9][0-9]*)$/';
        foreach ($_COOKIE as $name => $value) {
            if (!is_string($name) || preg_match($prefix, $name, $matches) !== 1) {
                continue;
            }
            $token = (string) $value;
            if (!self::isValidToken($token)) {
                continue;
            }
            if (count($candidates) >= self::MAX_COOKIES) {
                break;
            }
            $candidates[self::hashToken($token)] = ['id' => (int) $matches[1], 'token' => $token];
        }
        if ($candidates === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($candidates), '?'));
        $rows = Database::fetchAll(
            "SELECT gc.id, gc.property_id, gct.token_hash, gc.last_activity, p.name AS property_name
             FROM guest_chat_tokens gct
             JOIN guest_chats gc ON gc.id = gct.chat_id
             JOIN properties p ON p.id = gc.property_id
             WHERE gct.token_hash IN ({$placeholders})",
            array_keys($candidates)
        );
        $chats = [];
        foreach ($rows as $row) {
            $candidate = $candidates[$row['token_hash']] ?? null;
            if ($candidate !== null && (int) $row['id'] === $candidate['id']) {
                $chats[] = [
                    'id' => (int) $row['id'],
                    'property_id' => (int) $row['property_id'],
                    'token' => $candidate['token'],
                    'property_name' => (string) $row['property_name'],
                    'last_activity' => (string) $row['last_activity'],
                ];
            }
        }
        return $chats;
    }

    /** Normalised, non-reversible key for email-based rate limits. */
    public static function emailKey(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    /**
     * Chats started with an email address, most recent activity first.
     *
     * @return array<int, array{id:int, property_name:string}>
     */
    public static function findByEmail(string $email, int $limit = 5): array
    {
        $email = strtolower(trim($email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [];
        }
        $limit = max(1, min(10, $limit));
        $rows = Database::fetchAll(
            "SELECT gc.id, p.name AS property_name
             FROM guest_chats gc
             JOIN properties p ON p.id = gc.property_id
             WHERE LOWER(gc.guest_email) = ?
             ORDER BY gc.last_activity DESC, gc.id DESC
             LIMIT {$limit}",
            [$email]
        );
        $chats = [];
        foreach ($rows as $row) {
            $chats[] = [
                'id' => (int) $row['id'],
                'property_name' => (string) $row['property_name'],
            ];
        }
        return $chats;
    }

    /** Register the hash of a link so it can be used to open the chat. */
    public static function registerToken(int $chatId, string $token): void
    {
        if (!self::isValidToken($token)) {
            return;
        }
        Database::insert(
            "INSERT INTO guest_chat_tokens (chat_id, token_hash, created_at) VALUES (?, ?, NOW())",
            [$chatId, self::hashToken($token)]
        );
    }

    /**
     * Issue an additional access link. Earlier links are left untouched, so
     * an emailed link keeps working even after the visitor requests another.
     */
    public static function issueToken(int $chatId): string
    {
        $token = self::generateToken();
        self::registerToken($chatId, $token);
        return $token;
    }

    /**
     * Email the private conversation link. Never sets a cookie: the link
     * itself is the bearer credential and must reach the visitor's inbox.
     */
    public static function emailLink(string $email, string $token, string $propertyName): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || !self::isValidToken($token)) {
            return false;
        }
        $body = 'You can return to your private conversation about <strong>' . h($propertyName)
            . '</strong> at any time using the link below.'
            . '<br><br>Keep this email or bookmark the link — only people with the link can read the conversation.'
            . ' If we have sent you more than one link, you can use any of them.';
        try {
            return Mailer::sendTemplate(
                $email,
                __('Your conversation link'),
                'Hello,',
                $body,
                \site_url() . '/listings/chat/' . $token,
                __('View conversation')
            );
        } catch (\Throwable $e) {
            Logger::error('Guest chat link email failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Staff who should see a property's visitor chat: its landlord, its
     * property manager, and every admin (so a chat is never orphaned).
     *
     * @return array<int, int>
     */
    public static function recipientIds(int $propertyId): array
    {
        $property = Database::fetch(
            "SELECT landlord_id, property_manager_id FROM properties WHERE id = ?",
            [$propertyId]
        );
        if ($property === null) {
            return [];
        }
        $rows = Database::fetchAll(
            "SELECT DISTINCT u.id FROM users u
             WHERE u.archived_at IS NULL
               AND (u.id = ? OR u.id = ? OR u.role = 'admin')
             ORDER BY u.id",
            [$property['landlord_id'], $property['property_manager_id'] ?? null]
        );
        return array_map(static fn(array $row): int => (int) $row['id'], $rows);
    }

    /** Notify the property team (in-app + email) about a visitor message. */
    public static function notifyStaff(array $chat, string $body): void
    {
        $recipientIds = self::recipientIds((int) $chat['property_id']);
        if ($recipientIds === []) {
            return;
        }
        $snippet = mb_strlen($body) > 120 ? mb_substr($body, 0, 117) . '...' : $body;
        $data = $chat['guest_name'] . ' (' . $chat['property_name'] . '): ' . $snippet;
        $placeholders = implode(',', array_fill(0, count($recipientIds), '?'));
        $users = Database::fetchAll(
            "SELECT id, email, name FROM users WHERE id IN ({$placeholders})",
            $recipientIds
        );
        foreach ($users as $user) {
            \notifyInApp((int) $user['id'], 'new_message', $data, 'guest_chat');
            if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            try {
                Mailer::sendTemplate(
                    $user['email'],
                    'New visitor message about ' . $chat['property_name'],
                    'Hello ' . $user['name'] . ',',
                    'A visitor (' . h($chat['guest_name']) . ', ' . h($chat['guest_email']) . ') sent a message about '
                        . '<strong>' . h($chat['property_name']) . '</strong>:<br><br>' . nl2br(h($snippet)),
                    \site_url() . '/guest-chats/' . $chat['id'],
                    'View Conversation'
                );
            } catch (\Throwable $e) {
                Logger::error('Guest chat notification email failed: ' . $e->getMessage());
            }
        }
    }
}
