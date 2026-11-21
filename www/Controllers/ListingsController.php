<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\GuestChat;
use App\Core\Validator;
use App\Core\View;

/**
 * Public-facing property listings and the visitor chat started from them.
 * None of these actions require an application account.
 */
class ListingsController
{
    private const ALLOWED_PHOTO_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function index(): void
    {
        header('Cache-Control: private, no-store');

        $properties = Database::fetchAll(
            "SELECT p.id, p.name, p.address, p.city, p.province, p.rent_amount, p.ad_description,
                    (SELECT pp.id FROM property_photos pp
                     WHERE pp.property_id = p.id AND pp.is_main = 1
                     ORDER BY pp.id ASC LIMIT 1) AS main_photo_id
             FROM properties p
             WHERE p.listed = 1 AND p.archived_at IS NULL
             ORDER BY p.name ASC"
        );

        $conversations = GuestChat::cookieChats();
        $continueChats = [];
        foreach ($conversations as $chat) {
            $continueChats[(int) $chat['property_id']] = $chat['token'];
        }

        $view = new View();
        $view->layout('layouts/public', ['title' => __('Properties Available')]);
        $view->render('listings/index', [
            'properties' => $properties,
            'continueChats' => $continueChats,
            'conversations' => $conversations,
        ]);
    }

    public function startChat(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirect('/listings');
        }

        $property = Database::fetch(
            "SELECT id, name FROM properties WHERE id = ? AND listed = 1 AND archived_at IS NULL",
            [$id]
        );
        if ($property === null) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }

        $validator = new Validator();
        if (
            !$validator->validate($_POST, [
            'guest_name' => 'required|max:120',
            'guest_email' => 'required|email|max:190',
            'body' => 'required|max:2000',
            ])
        ) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_errors'] = $validator->errors();
            flash('error', __('Please complete all fields.'));
            redirect('/listings');
        }

        $name = trim((string) $_POST['guest_name']);
        $email = trim((string) $_POST['guest_email']);
        $body = trim((string) $_POST['body']);
        if ($name === '' || $email === '' || $body === '') {
            flash('error', __('Please complete all fields.'));
            redirect('/listings');
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (
            !auth_rate_limit('guest_chat:ip:' . $ip, 20, 300)
            || !auth_rate_limit('guest_chat:property:' . $id . ':' . $ip, 5, 900)
        ) {
            flash('error', __('Too many messages. Please wait a few minutes.'));
            redirect('/listings');
        }

        $token = GuestChat::generateToken();
        $chatId = Database::insert(
            "INSERT INTO guest_chats (property_id, guest_name, guest_email, token_hash, created_at, updated_at, last_activity)
             VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())",
            [$id, $name, $email, GuestChat::hashToken($token)]
        );
        Database::insert(
            "INSERT INTO guest_chat_messages (chat_id, sender_type, body, created_at) VALUES (?, 'guest', ?, NOW())",
            [$chatId, $body]
        );

        GuestChat::registerToken((int) $chatId, $token);
        GuestChat::setCookie($chatId, $token);
        log_activity('guest_chat.started', "Visitor chat #{$chatId} started for property #{$id}");

        $emailSent = false;
        $chat = GuestChat::findById($chatId);
        if ($chat !== null) {
            GuestChat::notifyStaff($chat, $body);
            $emailSent = GuestChat::emailLink($email, $token, (string) $chat['property_name']);
        }

        if ($emailSent) {
            flash('success', __('Your message has been sent. We emailed your private link to the address you provided. Save that email or bookmark this page to return to this conversation.'));
        } else {
            flash('success', __('Your message has been sent. Save this page link to return to the conversation.'));
        }
        redirect('/listings/chat/' . $token);
    }

    public function chat(string $token): void
    {
        header('Cache-Control: private, no-store');

        $chat = GuestChat::findByToken($token);
        if ($chat === null) {
            http_response_code(404);
            $view = new View();
            $view->layout('layouts/public', ['title' => __('Conversation Not Found')]);
            $view->render('listings/chat-missing');
            return;
        }

        // Opening a valid link proves possession of the bearer token, so the
        // browser can keep a convenience shortcut back to this conversation.
        GuestChat::setCookie((int) $chat['id'], $token);

        $view = new View();
        $view->layout('layouts/public', ['title' => __('Conversation')]);
        $view->render('listings/chat', [
            'chat' => $chat,
            'messages' => $this->messagesFor((int) $chat['id']),
            'token' => $token,
        ]);
    }

    public function chatMessages(string $token): void
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');

        $chat = GuestChat::findByToken($token);
        if ($chat === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Conversation not found.']);
            return;
        }
        echo json_encode(['messages' => $this->messagesFor((int) $chat['id'])]);
    }

    public function chatSend(string $token): void
    {
        header('Content-Type: application/json');

        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid form token.']);
            return;
        }
        $chat = GuestChat::findByToken($token);
        if ($chat === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Conversation not found.']);
            return;
        }

        $body = trim((string) ($_POST['body'] ?? ''));
        if ($body === '' || mb_strlen($body) > 2000) {
            http_response_code(400);
            echo json_encode(['error' => 'Message cannot be empty or too long.']);
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (
            !auth_rate_limit('guest_chat_send:ip:' . $ip, 20, 300)
            || !auth_rate_limit('guest_chat_send:chat:' . $chat['id'], 60, 300)
        ) {
            http_response_code(429);
            echo json_encode(['error' => 'Message limit reached. Please wait a moment.']);
            return;
        }

        Database::insert(
            "INSERT INTO guest_chat_messages (chat_id, sender_type, body, created_at) VALUES (?, 'guest', ?, NOW())",
            [$chat['id'], $body]
        );
        Database::execute("UPDATE guest_chats SET last_activity = NOW(), updated_at = NOW() WHERE id = ?", [$chat['id']]);
        GuestChat::notifyStaff($chat, $body);

        echo json_encode([
            'success' => true,
            'message' => ['mine' => true, 'body' => $body, 'time' => date('H:i')],
        ]);
    }

    /**
     * Email an additional private link after a visitor loses the original URL.
     *
     * Earlier links stay valid; only hashes of issued tokens are stored. The
     * response is deliberately generic so the endpoint cannot be used to
     * discover which addresses have conversations.
     */
    public function emailConversationLink(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirect('/listings');
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            flash('error', __('Please enter a valid email address.'));
            redirect('/listings');
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (
            !auth_rate_limit('guest_chat_link:ip:' . $ip, 5, 900)
            || !auth_rate_limit('guest_chat_link:email:' . GuestChat::emailKey($email), 3, 3600)
        ) {
            flash('error', __('Too many link requests. Please wait a few minutes.'));
            redirect('/listings');
        }

        $chats = GuestChat::findByEmail($email);
        foreach ($chats as $chat) {
            $token = GuestChat::issueToken((int) $chat['id']);
            GuestChat::emailLink($email, $token, (string) $chat['property_name']);
        }
        log_activity('guest_chat.link_requested', 'Conversation link request matched ' . count($chats) . ' conversation(s)');

        flash('success', __('If a conversation exists for that address, a new link has been sent. You can keep using any earlier links as well.'));
        redirect('/listings');
    }

    /** Serve a listing photo without an account, but only for listed properties. */
    public function servePhoto(int $id, int $photoId): void
    {
        $property = Database::fetch(
            "SELECT id FROM properties WHERE id = ? AND listed = 1 AND archived_at IS NULL",
            [$id]
        );
        if ($property === null) {
            http_response_code(404);
            return;
        }
        $photo = Database::fetch(
            "SELECT id, file_path, mime_type FROM property_photos WHERE id = ? AND property_id = ?",
            [$photoId, $id]
        );
        if ($photo === null || !in_array($photo['mime_type'], self::ALLOWED_PHOTO_MIMES, true)) {
            http_response_code(404);
            return;
        }

        $path = (string) $photo['file_path'];
        $fullPath = str_starts_with($path, '/') ? $path : base_path($path);
        $allowedBase = realpath(base_path('storage/uploads/property_photos'));
        $realPath = realpath($fullPath);
        if (
            $allowedBase === false || $realPath === false || !is_file($realPath)
            || !str_starts_with($realPath, $allowedBase . DIRECTORY_SEPARATOR)
        ) {
            http_response_code(404);
            return;
        }

        header('Content-Type: ' . $photo['mime_type']);
        header('Content-Length: ' . filesize($realPath));
        header('Cache-Control: public, max-age=86400');
        readfile($realPath);
        exit;
    }

    /** @return array<int, array<string, mixed>> */
    private function messagesFor(int $chatId): array
    {
        Database::execute(
            "UPDATE guest_chat_messages SET guest_read_at = NOW()
             WHERE chat_id = ? AND sender_type = 'staff' AND guest_read_at IS NULL",
            [$chatId]
        );
        $rows = Database::fetchAll(
            "SELECT id, sender_type, body, created_at FROM guest_chat_messages
             WHERE chat_id = ? ORDER BY id ASC LIMIT 200",
            [$chatId]
        );
        $messages = [];
        foreach ($rows as $row) {
            $messages[] = [
                'id' => (int) $row['id'],
                'mine' => $row['sender_type'] === 'guest',
                'body' => $row['body'],
                'time' => display_time($row['created_at']),
            ];
        }
        return $messages;
    }
}
