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

        // Every photo for the listed properties, so cards can offer a gallery.
        // Access is still checked per request by servePhoto().
        $photosByProperty = [];
        if ($properties !== []) {
            $propertyIds = array_map(static fn (array $property): int => (int) $property['id'], $properties);
            $placeholders = implode(',', array_fill(0, count($propertyIds), '?'));
            $photoRows = Database::fetchAll(
                "SELECT property_id, id, original_name
                 FROM property_photos
                 WHERE property_id IN ({$placeholders})
                 ORDER BY property_id ASC, is_main DESC, sort_order ASC, created_at ASC, id ASC",
                $propertyIds
            );
            foreach ($photoRows as $photo) {
                $propertyId = (int) $photo['property_id'];
                $photosByProperty[$propertyId][] = [
                    'src' => '/listings/' . $propertyId . '/photos/' . (int) $photo['id'],
                    'alt' => (string) $photo['original_name'],
                ];
            }
        }

        $conversations = GuestChat::cookieChats();
        $continueChats = [];
        foreach ($conversations as $chat) {
            $continueChats[(int) $chat['property_id']] = $chat['token'];
        }
        $hub = GuestChat::hubVisitor();

        $view = new View();
        $view->layout('layouts/public', $this->publicLayoutData(__('Properties Available'), !empty($properties)));
        $view->render('listings/index', [
            'properties' => $properties,
            'photos' => $photosByProperty,
            'continueChats' => $continueChats,
            'conversations' => $conversations,
            'hub' => $hub,
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

        $guestId = GuestChat::findOrCreateVisitor($email, $name);
        if ($guestId === 0) {
            flash('error', __('Please complete all fields.'));
            redirect('/listings');
        }

        // An email address alone is not proof of ownership of an existing
        // conversation. Direct the visitor to their private link instead.
        if (GuestChat::findChatForVisitorProperty($guestId, $id) !== null) {
            flash('error', __('You already have a conversation about this property. Use the private link we emailed you, or request a new link below.'));
            redirect('/listings');
        }

        $hadOtherChats = GuestChat::chatsForVisitor($guestId) !== [];
        $knownHubToken = GuestChat::hubTokenFor($guestId);

        $token = GuestChat::generateToken();
        $chatId = Database::insert(
            "INSERT INTO guest_chats (property_id, guest_name, guest_email, created_at, updated_at, last_activity)
             VALUES (?, ?, ?, NOW(), NOW(), NOW())",
            [$id, $name, $email]
        );
        GuestChat::registerToken((int) $chatId, $token);
        GuestChat::appendMessage((int) $chatId, $body);

        // Email a hub link, but only keep it in this browser when it was already
        // trusted or this is the visitor's first conversation.
        $hubToken = $knownHubToken ?? GuestChat::issueHubToken($guestId);
        GuestChat::setCookie($chatId, $token);
        if ($knownHubToken !== null || !$hadOtherChats) {
            GuestChat::setHubCookie($guestId, $hubToken);
        }
        log_activity('guest_chat.started', "Visitor chat #{$chatId} for property #{$id}");

        $emailSent = false;
        $chat = GuestChat::findById($chatId);
        if ($chat !== null) {
            GuestChat::notifyStaff($chat, $body);
            $emailSent = GuestChat::emailLink($email, $token, (string) $chat['property_name'], $hubToken);
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
            $view->layout('layouts/public', $this->publicLayoutData(__('Conversation Not Found')));
            $view->render('listings/chat-missing');
            return;
        }

        // A chat token authorises this conversation only. Hub access requires
        // the separate hub link from the visitor's email, not this token.
        GuestChat::setCookie((int) $chat['id'], $token);

        $view = new View();
        $view->layout('layouts/public', $this->publicLayoutData(__('Conversation')));
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

        $priorMessages = Database::fetch(
            "SELECT COUNT(*) AS cnt FROM guest_chat_messages WHERE chat_id = ?",
            [$chat['id']]
        );
        $event = ($priorMessages && (int) $priorMessages['cnt'] > 0) ? 'guest_chat_reply' : 'new_guest_chat';

        Database::insert(
            "INSERT INTO guest_chat_messages (chat_id, sender_type, body, created_at) VALUES (?, 'guest', ?, NOW())",
            [$chat['id'], $body]
        );
        Database::execute("UPDATE guest_chats SET last_activity = NOW(), updated_at = NOW() WHERE id = ?", [$chat['id']]);
        GuestChat::notifyStaff($chat, $body, $event);

        echo json_encode([
            'success' => true,
            'message' => ['mine' => true, 'body' => $body, 'time' => date('H:i')],
        ]);
    }

    /**
     * Email a link to the guest's conversation hub after they lose the URL.
     *
     * The guest is identified by email. A single hub link leads to one
     * conversation per property; earlier links stay valid. The response is
     * deliberately generic so this endpoint cannot enumerate email addresses.
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

        $visitor = GuestChat::findVisitorByEmail($email);
        if ($visitor !== null) {
            $chats = GuestChat::chatsForVisitor((int) $visitor['id']);
            $hubToken = GuestChat::issueHubToken((int) $visitor['id']);
            GuestChat::emailHubLink($email, $hubToken, count($chats));
            log_activity('guest_chat.link_requested', 'Conversation hub link request matched ' . count($chats) . ' conversation(s)');
        }

        flash('success', __('If a conversation exists for that address, a link to your conversations has been sent.'));
        redirect('/listings');
    }

    /** List all of a guest's property conversations from their hub link. */
    public function guestHub(string $token): void
    {
        header('Cache-Control: private, no-store');

        $visitor = GuestChat::findVisitorByHubToken($token);
        if ($visitor === null) {
            http_response_code(404);
            $view = new View();
            $view->layout('layouts/public', $this->publicLayoutData(__('Conversation Links Not Found')));
            $view->render('listings/hub-missing');
            return;
        }

        $view = new View();
        $view->layout('layouts/public', $this->publicLayoutData(__('Your conversations')));
        $view->render('listings/conversations', [
            'visitor' => $visitor,
            'chats' => GuestChat::chatsForVisitor((int) $visitor['id']),
            'hubToken' => $token,
        ]);
    }

    /** Open one property conversation from the guest's hub link. */
    public function openChatFromHub(string $token, int $chatId): void
    {
        $visitor = GuestChat::findVisitorByHubToken($token);
        $chat = null;
        if ($visitor !== null) {
            foreach (GuestChat::chatsForVisitor((int) $visitor['id']) as $candidate) {
                if ((int) $candidate['id'] === $chatId) {
                    $chat = $candidate;
                    break;
                }
            }
        }
        if ($visitor === null || $chat === null) {
            http_response_code(404);
            $view = new View();
            $view->layout('layouts/public', $this->publicLayoutData(__('Conversation Links Not Found')));
            $view->render('listings/hub-missing');
            return;
        }

        $chatToken = GuestChat::chatTokenFromCookie($chatId) ?? GuestChat::issueToken($chatId);
        GuestChat::setCookie($chatId, $chatToken);
        GuestChat::setHubCookie((int) $visitor['id'], $token);
        redirect('/listings/chat/' . $chatToken);
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

    /** Shared layout data for every page using the public header. */
    private function publicLayoutData(string $title, bool $hasListedProperties = true): array
    {
        return [
            'title' => $title,
            'hasListedProperties' => $hasListedProperties,
            'applicationsEnabled' => applications_enabled(),
        ];
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
