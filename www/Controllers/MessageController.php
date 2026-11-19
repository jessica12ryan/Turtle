<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;

class MessageController
{
    public function users(): void
    {
        header('Content-Type: application/json');
        if (!messaging_enabled()) {
            http_response_code(403);
            echo json_encode(['error' => 'Messaging is disabled.']);
            return;
        }
        $me = Auth::instance()->id();
        $cutoff = time() - PRESENCE_TIMEOUT_SECONDS;
        $presenceSeconds = PRESENCE_TIMEOUT_SECONDS;
        $senderRole = Auth::instance()->user()['role'] ?? '';
        $tenantFilter = $senderRole === 'tenant' ? " AND u.role != 'tenant'" : '';

        try {
            $rows = Database::fetchAll(
                "SELECT u.id, u.name, u.role, u.avatar_path, p.last_seen,
                        (SELECT COUNT(*) FROM messages m
                         WHERE m.sender_id = u.id AND m.recipient_id = ? AND m.read_at IS NULL) AS unread
                 FROM users u
                 LEFT JOIN user_presence p ON p.user_id = u.id
                 WHERE u.archived_at IS NULL AND u.id != ?{$tenantFilter}
                 ORDER BY (p.last_seen >= DATE_SUB(NOW(), INTERVAL {$presenceSeconds} SECOND)) DESC, u.name",
                [$me, $me]
            );
        } catch (\Throwable $e) {
            echo json_encode([]);
            return;
        }

        $users = [];
        foreach ($rows as $row) {
            $users[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'role' => ucwords(str_replace('_', ' ', $row['role'])),
                'avatar_url' => avatar_url(['id' => $row['id'], 'avatar_path' => $row['avatar_path']]),
                'initials' => initials_for($row['name']),
                'online' => !empty($row['last_seen']) && strtotime($row['last_seen']) >= $cutoff,
                'last_seen' => !empty($row['last_seen']) ? display_time($row['last_seen']) : null,
                'unread' => (int)$row['unread'],
            ];
        }

        echo json_encode($users);
    }

    public function thread(): void
    {
        header('Content-Type: application/json');
        if (!messaging_enabled()) {
            http_response_code(403);
            echo json_encode(['error' => 'Messaging is disabled.']);
            return;
        }
        $me = (int)Auth::instance()->id();
        $other = (int)($_GET['user_id'] ?? 0);

        if ($other === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid user.']);
            return;
        }

        $recipient = Database::fetch("SELECT id, name, role FROM users WHERE id = ? AND archived_at IS NULL", [$other]);
        if (!$recipient) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found.']);
            return;
        }

        if ((Auth::instance()->user()['role'] ?? '') === 'tenant' && $recipient['role'] === 'tenant') {
            http_response_code(403);
            echo json_encode(['error' => 'Tenants cannot message other tenants.']);
            return;
        }

        Database::execute(
            "UPDATE messages SET read_at = NOW() WHERE sender_id = ? AND recipient_id = ? AND read_at IS NULL",
            [$other, $me]
        );

        $rows = Database::fetchAll(
            "SELECT m.*, u.name as sender_name
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE (m.sender_id = ? AND m.recipient_id = ?) OR (m.sender_id = ? AND m.recipient_id = ?)
             ORDER BY m.created_at ASC
             LIMIT 200",
            [$me, $other, $other, $me]
        );

        $messages = [];
        foreach ($rows as $row) {
            $messages[] = [
                'id' => (int)$row['id'],
                'mine' => (int)$row['sender_id'] === $me,
                'sender_name' => $row['sender_name'],
                'body' => $row['body'],
                'time' => display_time($row['created_at']),
            ];
        }

        echo json_encode(['user' => ['id' => (int)$recipient['id'], 'name' => $recipient['name']], 'messages' => $messages]);
    }

    public function unread(): void
    {
        header('Content-Type: application/json');
        if (!messaging_enabled()) {
            http_response_code(403);
            echo json_encode(['error' => 'Messaging is disabled.']);
            return;
        }
        $me = Auth::instance()->id();

        $messages = Database::fetch(
            "SELECT COUNT(*) as count FROM messages WHERE recipient_id = ? AND read_at IS NULL",
            [$me]
        )['count'] ?? 0;
        $notifications = Database::fetch(
            "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND read_at IS NULL AND (type IS NULL OR type != 'message')",
            [$me]
        )['count'] ?? 0;

        echo json_encode([
            'messages' => (int)$messages,
            'notifications' => (int)$notifications,
        ]);
    }

    public function send(): void
    {
        header('Content-Type: application/json');
        if (!messaging_enabled()) {
            http_response_code(403);
            echo json_encode(['error' => 'Messaging is disabled.']);
            return;
        }

        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid security token.']);
            return;
        }

        $me = (int)Auth::instance()->id();
        $recipientId = (int)($_POST['recipient_id'] ?? 0);
        $body = trim((string)($_POST['body'] ?? ''));

        if ($body === '' || mb_strlen($body) > 2000) {
            http_response_code(400);
            echo json_encode(['error' => 'Message cannot be empty or too long.']);
            return;
        }

        if ($recipientId === 0 || $recipientId === $me) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid recipient.']);
            return;
        }

        $recipient = Database::fetch("SELECT id, name, role FROM users WHERE id = ? AND archived_at IS NULL", [$recipientId]);
        if (!$recipient) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found.']);
            return;
        }

        $sender = Auth::instance()->user();
        if ($sender['role'] === 'tenant' && $recipient['role'] === 'tenant') {
            http_response_code(403);
            echo json_encode(['error' => 'Tenants cannot message other tenants.']);
            return;
        }

        // Rate limit: at most 10 messages per 30 seconds per sender.
        $recent = Database::fetch(
            "SELECT COUNT(*) as cnt FROM messages WHERE sender_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)",
            [$me]
        );
        if ($recent && (int)$recent['cnt'] >= 10) {
            http_response_code(429);
            echo json_encode(['error' => 'Message limit reached. Please wait a moment.']);
            return;
        }

        $id = Database::insert(
            "INSERT INTO messages (sender_id, recipient_id, body, created_at) VALUES (?, ?, ?, NOW())",
            [$me, $recipientId, $body]
        );
        $saved = Database::fetch("SELECT created_at FROM messages WHERE id = ?", [$id]);

        // Online recipients are notified by the messenger overlay (badge + sound).
        // Only offline recipients get a persisted notification record.
        if (!$this->isOnline($recipientId) && shouldNotifyInApp('new_message', $recipient['role'])) {
            $snippet = mb_strlen($body) > 120 ? mb_substr($body, 0, 117) . '...' : $body;
            Database::execute(
                "INSERT INTO notifications (user_id, type, data, created_at) VALUES (?, 'message', ?, NOW())",
                [$recipientId, $sender['name'] . ': ' . $snippet]
            );
        }

        echo json_encode([
            'success' => true,
            'message' => [
                'id' => $id,
                'mine' => true,
                'sender_name' => $sender['name'],
                'body' => $body,
                'time' => display_time($saved['created_at'] ?? date('Y-m-d H:i:s')),
            ],
        ]);
    }

    public function heartbeat(): void
    {
        header('Content-Type: application/json');
        if (!messaging_enabled()) {
            http_response_code(403);
            echo json_encode(['error' => 'Messaging is disabled.']);
            return;
        }

        Database::execute(
            "INSERT INTO user_presence (user_id, last_seen) VALUES (?, NOW())
             ON DUPLICATE KEY UPDATE last_seen = NOW()",
            [Auth::instance()->id()]
        );

        echo json_encode(['success' => true]);
    }

    private function isOnline(int $userId): bool
    {
        $presenceSeconds = PRESENCE_TIMEOUT_SECONDS;
        $row = Database::fetch(
            "SELECT user_id FROM user_presence
             WHERE user_id = ? AND last_seen >= DATE_SUB(NOW(), INTERVAL {$presenceSeconds} SECOND)",
            [$userId]
        );
        return $row !== null;
    }
}
