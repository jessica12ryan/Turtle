<?php

namespace App\Controllers;

use App\Core\Access;
use App\Core\Auth;
use App\Core\Database;
use App\Core\GuestChat;
use App\Core\View;

/**
 * Staff-side inbox for conversations started by visitors on the public
 * listings page. Access is scoped to the properties a staff member manages.
 */
class GuestChatController
{
    /** @return array<int, array<string, mixed>> */
    private function scopedChats(): array
    {
        $user = Auth::instance()->user();
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

        return Database::fetchAll(
            "SELECT gc.id, gc.guest_name, gc.last_activity, gc.property_id, p.name AS property_name,
                    (SELECT COUNT(*) FROM guest_chat_messages m
                     WHERE m.chat_id = gc.id AND m.sender_type = 'guest' AND m.read_at IS NULL) AS unread,
                    (SELECT m.body FROM guest_chat_messages m
                     WHERE m.chat_id = gc.id ORDER BY m.id DESC LIMIT 1) AS last_body
             FROM guest_chats gc
             JOIN properties p ON p.id = gc.property_id
             WHERE {$where}
             ORDER BY gc.last_activity DESC",
            $params
        );
    }

    /** @return array<string, mixed>|null */
    private function findScoped(int $id): ?array
    {
        $chat = GuestChat::findById($id);
        if ($chat === null) {
            return null;
        }
        Access::require(Access::property((int) $chat['property_id']));
        return $chat;
    }

    public function index(): void
    {
        $view = new View();
        $view->layout('layouts/main', ['title' => __('Guest Chats')]);
        $view->render('guest_chats/index', ['chats' => $this->scopedChats()]);
    }

    public function show(int $id): void
    {
        $chat = $this->findScoped($id);
        if ($chat === null) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        $this->markGuestMessagesRead($id);

        $view = new View();
        $view->layout('layouts/main', ['title' => __('Guest Chats')]);
        $view->render('guest_chats/show', [
            'chat' => $chat,
            'messages' => $this->messagesFor($id),
        ]);
    }

    public function messages(int $id): void
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');

        $chat = $this->findScoped($id);
        if ($chat === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Conversation not found.']);
            return;
        }
        $this->markGuestMessagesRead($id);
        echo json_encode(['messages' => $this->messagesFor($id)]);
    }

    public function reply(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirect('/guest-chats/' . $id);
        }
        $chat = $this->findScoped($id);
        if ($chat === null) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }

        $body = trim((string) ($_POST['body'] ?? ''));
        if ($body === '' || mb_strlen($body) > 2000) {
            flash('error', __('Message cannot be empty or too long.'));
            redirect('/guest-chats/' . $id);
        }

        Database::insert(
            "INSERT INTO guest_chat_messages (chat_id, sender_type, sender_id, body, created_at)
             VALUES (?, 'staff', ?, ?, NOW())",
            [$id, Auth::instance()->id(), $body]
        );
        Database::execute("UPDATE guest_chats SET last_activity = NOW(), updated_at = NOW() WHERE id = ?", [$id]);

        // The guest never stores the raw token, so issue a fresh direct link
        // for this email. Earlier links stay valid.
        $token = GuestChat::issueToken($id);
        GuestChat::emailStaffReply($chat, $body, $token);

        log_activity('guest_chat.replied', "Staff replied to visitor chat #{$id}");
        flash('success', __('Reply sent.'));
        redirect('/guest-chats/' . $id);
    }

    private function markGuestMessagesRead(int $chatId): void
    {
        Database::execute(
            "UPDATE guest_chat_messages SET read_at = NOW()
             WHERE chat_id = ? AND sender_type = 'guest' AND read_at IS NULL",
            [$chatId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function messagesFor(int $chatId): array
    {
        $rows = Database::fetchAll(
            "SELECT id, sender_type, body, created_at FROM guest_chat_messages
             WHERE chat_id = ? ORDER BY id ASC LIMIT 200",
            [$chatId]
        );
        $messages = [];
        foreach ($rows as $row) {
            $messages[] = [
                'id' => (int) $row['id'],
                'mine' => $row['sender_type'] === 'staff',
                'body' => $row['body'],
                'time' => display_time($row['created_at']),
            ];
        }
        return $messages;
    }
}
