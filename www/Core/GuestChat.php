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
    public const HUB_COOKIE_PREFIX = 'turtle_guest_';
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
             WHERE gct.token_hash = ? AND gc.archived_at IS NULL",
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

    /**
     * Property access filter shared by the staff inbox and the sidebar badge.
     *
     * @param array<string, mixed> $user
     * @return array{0:string, 1:array<int, mixed>}
     */
    private static function scopeFor(array $user): array
    {
        $where = 'p.archived_at IS NULL';
        $params = [];
        if (($user['role'] ?? '') !== 'admin') {
            if (($user['role'] ?? '') === 'property_manager') {
                $where .= ' AND p.property_manager_id = ?';
                $params[] = $user['id'];
            } else {
                $companyIds = Database::fetchAll(
                    "SELECT company_id FROM company_user WHERE user_id = ?",
                    [$user['id']]
                );
                $companyIdList = implode(',', array_column($companyIds, 'company_id')) ?: '0';
                $where .= " AND p.company_id IN ({$companyIdList})";
            }
        }
        return [$where, $params];
    }

    /**
     * Conversations a staff member is allowed to see, newest activity first.
     *
     * @param array<string, mixed> $user
     * @return array<int, array<string, mixed>>
     */
    public static function scopedChats(array $user, bool $showArchived = false): array
    {
        [$where, $params] = self::scopeFor($user);
        if (!$showArchived) {
            $where .= ' AND gc.archived_at IS NULL';
        }
        return Database::fetchAll(
            "SELECT gc.id, gc.guest_name, gc.guest_email, gc.last_activity, gc.archived_at, gc.property_id, p.name AS property_name,
                    (SELECT COUNT(*) FROM guest_chat_messages m
                     WHERE m.chat_id = gc.id AND m.sender_type = 'guest' AND m.read_at IS NULL) AS unread,
                    (SELECT m.body FROM guest_chat_messages m
                     WHERE m.chat_id = gc.id ORDER BY m.id DESC LIMIT 1) AS last_body
             FROM guest_chats gc
             JOIN properties p ON p.id = gc.property_id
             WHERE {$where}
             ORDER BY (gc.archived_at IS NULL) DESC, gc.last_activity DESC, gc.id DESC",
            $params
        );
    }

    /** Number of conversations with unread guest messages for this user. */
    public static function unreadCount(array $user): int
    {
        [$where, $params] = self::scopeFor($user);
        $row = Database::fetch(
            "SELECT COUNT(DISTINCT gc.id) AS total
             FROM guest_chats gc
             JOIN properties p ON p.id = gc.property_id
             JOIN guest_chat_messages m
               ON m.chat_id = gc.id AND m.sender_type = 'guest' AND m.read_at IS NULL
             WHERE {$where} AND gc.archived_at IS NULL",
            $params
        );
        return $row === null ? 0 : (int) $row['total'];
    }

    public static function setCookie(int $chatId, string $token): void
    {
        self::writeCookie(self::COOKIE_PREFIX . $chatId, $token);
    }

    public static function setHubCookie(int $guestId, string $token): void
    {
        self::writeCookie(self::HUB_COOKIE_PREFIX . $guestId, $token);
    }

    private static function writeCookie(string $name, string $token): void
    {
        if (headers_sent() || !self::isValidToken($token)) {
            return;
        }
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
        setcookie($name, $token, [
            'expires' => time() + 60 * 60 * 24 * 365,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /** A still-valid chat token already held by this browser, if any. */
    public static function chatTokenFromCookie(int $chatId): ?string
    {
        $token = $_COOKIE[self::COOKIE_PREFIX . $chatId] ?? '';
        if (!is_string($token) || !self::isValidToken($token)) {
            return null;
        }
        return self::findByToken($token) !== null ? $token : null;
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
             WHERE gct.token_hash IN ({$placeholders}) AND gc.archived_at IS NULL",
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

    /**
     * Guest identity whose hub token this browser holds.
     *
     * @return array{id:int, email:string, name:string, token:string}|null
     */
    public static function hubVisitor(): ?array
    {
        $candidates = [];
        $prefix = '/^' . preg_quote(self::HUB_COOKIE_PREFIX, '/') . '([1-9][0-9]*)$/';
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
            return null;
        }
        $placeholders = implode(',', array_fill(0, count($candidates), '?'));
        $rows = Database::fetchAll(
            "SELECT gv.id, gv.email, gv.name, gvt.token_hash
             FROM guest_visitor_tokens gvt
             JOIN guest_visitors gv ON gv.id = gvt.guest_id
             WHERE gvt.token_hash IN ({$placeholders})",
            array_keys($candidates)
        );
        foreach ($rows as $row) {
            $candidate = $candidates[$row['token_hash']] ?? null;
            if ($candidate !== null && (int) $row['id'] === $candidate['id']) {
                return [
                    'id' => (int) $row['id'],
                    'email' => (string) $row['email'],
                    'name' => (string) $row['name'],
                    'token' => $candidate['token'],
                ];
            }
        }
        return null;
    }

    /** Reuse the current browser's hub token for a known guest, if available. */
    public static function hubTokenFor(int $guestId): ?string
    {
        $hub = self::hubVisitor();
        return $hub !== null && (int) $hub['id'] === $guestId ? $hub['token'] : null;
    }

    /** Normalised, non-reversible key for email-based rate limits. */
    public static function emailKey(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    /** Find or create the guest identity for an email address. */
    public static function findOrCreateVisitor(string $email, string $name): int
    {
        $email = strtolower(trim($email));
        $name = trim($name);
        if ($email === '' || $name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 0;
        }
        Database::insert(
            "INSERT IGNORE INTO guest_visitors (email, name, created_at) VALUES (?, ?, NOW())",
            [$email, $name]
        );
        $visitor = Database::fetch("SELECT id FROM guest_visitors WHERE email = ?", [$email]);
        if ($visitor === null) {
            return 0;
        }
        $id = (int) $visitor['id'];
        Database::execute("UPDATE guest_visitors SET name = ? WHERE id = ?", [$name, $id]);
        return $id;
    }

    /** @return array{id:int, email:string, name:string}|null */
    public static function findVisitorByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }
        $row = Database::fetch("SELECT id, email, name FROM guest_visitors WHERE email = ?", [$email]);
        return self::visitorRow($row);
    }

    /** @return array{id:int, email:string, name:string}|null */
    public static function findVisitorByHubToken(string $token): ?array
    {
        if (!self::isValidToken($token)) {
            return null;
        }
        $hash = self::hashToken($token);
        $row = Database::fetch(
            "SELECT gv.id, gv.email, gv.name, gvt.token_hash AS matched_hash
             FROM guest_visitor_tokens gvt
             JOIN guest_visitors gv ON gv.id = gvt.guest_id
             WHERE gvt.token_hash = ?",
            [$hash]
        );
        if ($row === null || !hash_equals($row['matched_hash'], $hash)) {
            return null;
        }
        return self::visitorRow($row);
    }

    /** @param array<string, mixed>|null $row */
    private static function visitorRow(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'name' => (string) $row['name'],
        ];
    }

    /** Issue an additional hub link; earlier hub links keep working. */
    public static function issueHubToken(int $guestId): string
    {
        $token = self::generateToken();
        Database::insert(
            "INSERT INTO guest_visitor_tokens (guest_id, token_hash, created_at) VALUES (?, ?, NOW())",
            [$guestId, self::hashToken($token)]
        );
        return $token;
    }

    /**
     * All conversations belonging to a guest, newest activity first.
     * There is one conversation per property.
     *
     * @return array<int, array{id:int, property_id:int, property_name:string, last_activity:string}>
     */
    public static function chatsForVisitor(int $guestId): array
    {
        $visitor = Database::fetch("SELECT email FROM guest_visitors WHERE id = ?", [$guestId]);
        if ($visitor === null) {
            return [];
        }
        $rows = Database::fetchAll(
            "SELECT gc.id, gc.property_id, gc.last_activity, p.name AS property_name
             FROM guest_chats gc
             JOIN properties p ON p.id = gc.property_id
             WHERE LOWER(gc.guest_email) = ? AND gc.archived_at IS NULL
             ORDER BY gc.last_activity DESC, gc.id DESC",
            [$visitor['email']]
        );
        $chats = [];
        foreach ($rows as $row) {
            $chats[] = [
                'id' => (int) $row['id'],
                'property_id' => (int) $row['property_id'],
                'property_name' => (string) $row['property_name'],
                'last_activity' => (string) $row['last_activity'],
            ];
        }
        return $chats;
    }

    /** @return array{id:int, property_id:int}|null */
    public static function findChatForVisitorProperty(int $guestId, int $propertyId): ?array
    {
        $visitor = Database::fetch("SELECT email FROM guest_visitors WHERE id = ?", [$guestId]);
        if ($visitor === null) {
            return null;
        }
        $row = Database::fetch(
            "SELECT id, property_id FROM guest_chats
             WHERE LOWER(guest_email) = ? AND property_id = ? AND archived_at IS NULL
             ORDER BY last_activity DESC, id DESC LIMIT 1",
            [$visitor['email'], $propertyId]
        );
        return $row === null ? null : ['id' => (int) $row['id'], 'property_id' => (int) $row['property_id']];
    }

    /** Append a visitor message and bump the conversation's activity time. */
    public static function appendMessage(int $chatId, string $body): void
    {
        Database::insert(
            "INSERT INTO guest_chat_messages (chat_id, sender_type, body, created_at) VALUES (?, 'guest', ?, NOW())",
            [$chatId, $body]
        );
        Database::execute("UPDATE guest_chats SET last_activity = NOW(), updated_at = NOW() WHERE id = ?", [$chatId]);
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
    public static function emailLink(string $email, string $token, string $propertyName, ?string $hubToken = null): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || !self::isValidToken($token)) {
            return false;
        }
        $body = 'You can return to your private conversation about <strong>' . h($propertyName)
            . '</strong> at any time using the link below.'
            . '<br><br>Keep this email or bookmark the link — only people with the link can read the conversation.'
            . ' If we have sent you more than one link, you can use any of them.';
        if ($hubToken !== null && self::isValidToken($hubToken)) {
            $hubUrl = \site_url() . '/listings/conversations/' . $hubToken;
            $body .= '<br><br>To see all of your property conversations, visit <a href="' . $hubUrl . '">your conversations</a>.';
        }
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

    /** Email a single link that opens the guest's list of conversations. */
    public static function emailHubLink(string $email, string $hubToken, int $conversationCount): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || !self::isValidToken($hubToken)) {
            return false;
        }
        $body = 'You can return to all of your property conversations using the link below.'
            . '<br><br>This link is private — only people with it can read your conversations.'
            . ' Earlier links you received still work too.';
        if ($conversationCount === 1) {
            $body .= '<br><br>You currently have one conversation.';
        } else {
            $body .= '<br><br>You currently have ' . $conversationCount . ' conversations, one per property.';
        }
        try {
            return Mailer::sendTemplate(
                $email,
                __('Your conversations'),
                'Hello,',
                $body,
                \site_url() . '/listings/conversations/' . $hubToken,
                __('View your conversations')
            );
        } catch (\Throwable $e) {
            Logger::error('Guest conversation list email failed: ' . $e->getMessage());
            return false;
        }
    }

    /** Email the guest when a staff member posts a new message. */
    public static function emailStaffReply(array $chat, string $body, string $token): bool
    {
        $email = (string) ($chat['guest_email'] ?? '');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || !self::isValidToken($token)) {
            return false;
        }
        $snippet = mb_strlen($body) > 120 ? mb_substr($body, 0, 117) . '...' : $body;
        $message = 'The property team replied to your conversation about <strong>'
            . h((string) ($chat['property_name'] ?? '')) . '</strong>:<br><br>' . nl2br(h($snippet))
            . '<br><br>Use the link below to read the reply and continue the conversation.'
            . ' Your earlier links still work too.';
        try {
            return Mailer::sendTemplate(
                $email,
                __('New reply to your conversation'),
                'Hello ' . h((string) ($chat['guest_name'] ?? '')) . ',',
                $message,
                \site_url() . '/listings/chat/' . $token,
                __('View conversation')
            );
        } catch (\Throwable $e) {
            Logger::error('Guest reply notification email failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Recipient for listing messages: the property's manager if one is set,
     * otherwise its landlord. Admins are deliberately not notified.
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
        $managerId = (int) ($property['property_manager_id'] ?? 0);
        $recipientId = $managerId > 0 ? $managerId : (int) $property['landlord_id'];
        if ($recipientId <= 0) {
            return [];
        }
        $user = Database::fetch(
            "SELECT id FROM users WHERE id = ? AND archived_at IS NULL",
            [$recipientId]
        );
        return $user === null ? [] : [(int) $user['id']];
    }

    /** Notify the property team (in-app + email) about a visitor message. */
    public static function notifyStaff(array $chat, string $body, string $event = 'new_guest_chat'): void
    {
        $snippet = mb_strlen($body) > 120 ? mb_substr($body, 0, 117) . '...' : $body;
        $data = $chat['guest_name'] . ' (' . $chat['property_name'] . '): ' . $snippet;
        $propertyId = (int) $chat['property_id'];

        foreach (propertyNotificationRecipients($propertyId, $event, 'inapp') as $user) {
            \notifyInApp((int) $user['id'], $event, $data, 'guest_chat', 'property_manager');
        }

        $subject = $event === 'guest_chat_reply'
            ? 'New guest reply about '
            : 'New visitor message about ';
        foreach (propertyNotificationRecipients($propertyId, $event, 'email') as $user) {
            if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            try {
                Mailer::sendTemplate(
                    $user['email'],
                    $subject . $chat['property_name'],
                    'Hello ' . h($user['name']) . ',',
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
