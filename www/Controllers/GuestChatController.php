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
    private function scopedChats(bool $showArchived = false): array
    {
        return GuestChat::scopedChats(Auth::instance()->user(), $showArchived);
    }

    /**
     * Group conversations by the guest's email address, most recently active
     * guest first. Each group keeps its own property conversations in order.
     *
     * @return array<int, array<string, mixed>>
     */
    private function groupedChats(bool $showArchived = false): array
    {
        $groups = [];
        foreach ($this->scopedChats($showArchived) as $chat) {
            $email = strtolower((string) ($chat['guest_email'] ?? ''));
            if (!isset($groups[$email])) {
                $groups[$email] = [
                    'guest_email' => (string) $chat['guest_email'],
                    'unread' => 0,
                    'last_activity' => (string) $chat['last_activity'],
                    'chats' => [],
                ];
            }
            $groups[$email]['unread'] += (int) $chat['unread'];
            if (strcmp((string) $chat['last_activity'], (string) $groups[$email]['last_activity']) > 0) {
                $groups[$email]['last_activity'] = (string) $chat['last_activity'];
            }
            $groups[$email]['chats'][] = $chat;
        }
        usort(
            $groups,
            static fn(array $a, array $b): int => strcmp((string) $b['last_activity'], (string) $a['last_activity'])
        );
        return $groups;
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
        $showArchived = !empty($_GET['show_archived']);
        $view = new View();
        $view->layout('layouts/main', ['title' => __('Guest Chats')]);
        $view->render('guest_chats/index', [
            'groups' => $this->groupedChats($showArchived),
            'showArchived' => $showArchived,
        ]);
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
        if (!empty($chat['archived_at'])) {
            flash('error', __('This conversation is archived. Restore it before replying.'));
            redirect('/guest-chats/' . $id);
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

    public function archive(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
        $chat = $this->findScoped($id);
        if ($chat === null) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        if (!empty($chat['archived_at'])) {
            redirect('/guest-chats');
        }
        Database::execute(
            "UPDATE guest_chats SET archived_at = NOW(), updated_at = NOW() WHERE id = ?",
            [$id]
        );
        log_activity('guest_chat.archived', "Visitor chat #{$id} archived");
        flash('success', __('Guest chat archived successfully.'));
        redirect('/guest-chats');
    }

    public function restore(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
        $chat = $this->findScoped($id);
        if ($chat === null || empty($chat['archived_at'])) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        Database::execute(
            "UPDATE guest_chats SET archived_at = NULL, updated_at = NOW() WHERE id = ?",
            [$id]
        );
        log_activity('guest_chat.restored', "Visitor chat #{$id} restored");
        flash('success', __('Guest chat restored successfully.'));
        redirect('/guest-chats/' . $id);
    }

    public function delete(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }
        $chat = $this->findScoped($id);
        if ($chat === null) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        // guest_chat_messages and guest_chat_tokens cascade from the chat.
        Database::execute("DELETE FROM guest_chats WHERE id = ?", [$id]);
        log_activity('guest_chat.deleted', "Visitor chat #{$id} deleted");
        flash('success', __('Guest chat deleted successfully.'));
        redirect('/guest-chats');
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
