<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\View;
use App\Core\Tenancy;

class AiAssistantController
{
    /** Keep conversations bounded: 20 messages = 10 exchanges. */
    public const MAX_MESSAGES = 20;

    /** Feature gate: admin-enabled and never available to tenant-only users. */
    private function guard(bool $json): ?array
    {
        $user = Auth::instance()->user();
        $enabled = getCachedSetting('ai_assistant_enabled') === '1';

        if (!$user || !$enabled || is_tenant_only_user($user)) {
            if ($json) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['error' => __('AI Assistant is not available.')]);
            } else {
                http_response_code(403);
                require base_path('www/Views/errors/403.php');
            }
            return null;
        }
        return $user;
    }

    public function index(): void
    {
        $user = $this->guard(false);
        if ($user === null) {
            return;
        }

        $conversations = Database::fetchAll(
            "SELECT id, title, updated_at FROM ai_conversations WHERE user_id = ? ORDER BY updated_at DESC",
            [$user['id']]
        );

        $view = new View();
        $view->layout('layouts/main', ['title' => 'AI Assistant']);
        $view->render('ai_assistant/index', compact('conversations'));
    }

    /** Create an empty chat. Kept as JSON so the UI can stay on one page. */
    public function createConversation(): void
    {
        header('Content-Type: application/json');
        $user = $this->guard(true);
        if ($user === null) {
            return;
        }
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(400);
            echo json_encode(['error' => __('Invalid security token.')]);
            return;
        }

        $title = self::conversationTitle((string)($_POST['title'] ?? ''));
        $id = Database::insert(
            "INSERT INTO ai_conversations (user_id, title, messages, created_at, updated_at) VALUES (?, ?, '[]', NOW(), NOW())",
            [$user['id'], $title]
        );

        echo json_encode(['id' => (int)$id, 'title' => $title]);
    }

    /** Load one chat's messages for the current user. */
    public function getConversation(int $id): void
    {
        header('Content-Type: application/json');
        $user = $this->guard(true);
        if ($user === null) {
            return;
        }

        $conversation = Database::fetch(
            "SELECT id, title, messages FROM ai_conversations WHERE id = ? AND user_id = ?",
            [$id, $user['id']]
        );
        if (!$conversation) {
            http_response_code(404);
            echo json_encode(['error' => __('Chat not found.')]);
            return;
        }

        $decoded = json_decode((string)$conversation['messages'], true);
        echo json_encode([
            'id' => (int)$conversation['id'],
            'title' => (string)$conversation['title'],
            'messages' => is_array($decoded) ? array_values($decoded) : [],
        ]);
    }

    /** Send a message to an existing chat owned by the current user. */
    public function conversationChat(int $id): void
    {
        header('Content-Type: application/json');
        $user = $this->guard(true);
        if ($user === null) {
            return;
        }
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(400);
            echo json_encode(['error' => __('Invalid security token.')]);
            return;
        }

        $message = trim((string)($_POST['message'] ?? ''));
        if ($message === '') {
            http_response_code(400);
            echo json_encode(['error' => __('Message is required.')]);
            return;
        }

        $conversation = Database::fetch(
            "SELECT id, title, messages FROM ai_conversations WHERE id = ? AND user_id = ?",
            [$id, $user['id']]
        );
        if (!$conversation) {
            http_response_code(404);
            echo json_encode(['error' => __('Chat not found.')]);
            return;
        }

        $decoded = json_decode((string)$conversation['messages'], true);
        $prior = is_array($decoded) ? $decoded : [];

        try {
            [$prior, $response] = $this->exchange($user, $prior, $message);
        } catch (\Throwable $e) {
            Logger::error('AI Assistant error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => __('AI request failed. Please try again.')]);
            return;
        }

        Database::execute(
            "UPDATE ai_conversations SET messages = ?, updated_at = NOW() WHERE id = ? AND user_id = ?",
            [json_encode($prior, JSON_UNESCAPED_UNICODE), $id, $user['id']]
        );

        echo json_encode(['response' => $response]);
    }

    public function deleteConversation(int $id): void
    {
        header('Content-Type: application/json');
        $user = $this->guard(true);
        if ($user === null) {
            return;
        }
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(400);
            echo json_encode(['error' => __('Invalid security token.')]);
            return;
        }

        Database::execute("DELETE FROM ai_conversations WHERE id = ? AND user_id = ?", [$id, $user['id']]);
        echo json_encode(['success' => true]);
    }

    /**
     * Backward-compatible single-chat endpoint. Older bookmarks/clients keep
     * working; the UI now uses per-conversation endpoints above.
     */
    public function chat(): void
    {
        header('Content-Type: application/json');
        $user = $this->guard(true);
        if ($user === null) {
            return;
        }
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(400);
            echo json_encode(['error' => __('Invalid security token.')]);
            return;
        }

        $message = trim((string)($_POST['message'] ?? ''));
        if ($message === '') {
            http_response_code(400);
            echo json_encode(['error' => __('Message is required.')]);
            return;
        }

        $conversation = $_SESSION['ai_conversation'] ?? [];
        if (!is_array($conversation)) {
            $conversation = [];
        }

        try {
            [$conversation, $response] = $this->exchange($user, $conversation, $message);
        } catch (\Throwable $e) {
            Logger::error('AI Assistant error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => __('AI request failed. Please try again.')]);
            return;
        }

        $_SESSION['ai_conversation'] = $conversation;
        echo json_encode(['response' => $response]);
    }

    /** Append a user message, call OpenAI, append the reply, and cap history. */
    private function exchange(array $user, array $prior, string $message): array
    {
        $apiKeyRow = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'openai_api_key'");
        $apiKey = trim((string)($apiKeyRow['value'] ?? ''));
        if ($apiKey === '') {
            throw new \RuntimeException((string)__('AI Assistant is not configured. Ask an administrator to add an OpenAI API key.'));
        }

        $systemPrompt = $this->buildSystemPrompt($user, self::redactContext($this->buildContext($user)));

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach (array_slice($prior, -self::MAX_MESSAGES) as $msg) {
            if (is_array($msg) && isset($msg['role'], $msg['content'])) {
                $messages[] = ['role' => (string)$msg['role'], 'content' => (string)$msg['content']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        $response = $this->callOpenAI($apiKey, $messages);

        $prior[] = ['role' => 'user', 'content' => $message];
        $prior[] = ['role' => 'assistant', 'content' => $response];
        return [self::capMessages($prior), $response];
    }

    /** Bound a conversation to the last 20 messages. */
    public static function capMessages(array $messages): array
    {
        return array_values(array_slice($messages, -self::MAX_MESSAGES));
    }

    /** Short, safe title derived from the first message or a supplied name. */
    public static function conversationTitle(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($text === '') {
            return (string)__('New chat');
        }
        return mb_substr($text, 0, 80);
    }

    /**
     * Strip direct identifiers before any data leaves the installation.
     * Only aggregate counts and opaque IDs are sent to OpenAI.
     */
    public static function redactContext(array $context): array
    {
        $redacted = ['stats' => $context['stats'] ?? []];

        foreach ($context['properties'] ?? [] as $property) {
            $redacted['properties'][] = [
                'id' => 'Property #' . (int)($property['id'] ?? 0),
                'tenants' => (int)($property['tenants_count'] ?? 0),
                'open_tickets' => (int)($property['tickets_count'] ?? 0),
            ];
        }
        foreach ($context['tenants'] ?? [] as $tenant) {
            $redacted['tenants'][] = ['id' => 'Tenant #' . (int)($tenant['id'] ?? 0)];
        }
        foreach ($context['open_tickets'] ?? [] as $ticket) {
            $redacted['open_tickets'][] = [
                'status' => (string)($ticket['status'] ?? ''),
                'property' => 'Property #' . (int)($ticket['property_id'] ?? 0),
                'tenant' => 'Tenant #' . (int)($ticket['tenant_id'] ?? 0),
            ];
        }
        foreach ($context['recent_leases'] ?? [] as $lease) {
            $redacted['recent_leases'][] = [
                'property' => 'Property #' . (int)($lease['property_id'] ?? 0),
                'lease_start' => (string)($lease['lease_start'] ?? ''),
                'lease_end' => (string)($lease['lease_end'] ?? ''),
            ];
        }

        return $redacted;
    }

    private function buildSystemPrompt(array $user, array $context): string
    {
        $roleLabel = ucwords(str_replace('_', ' ', (string)$user['role']));

        $prompt = "You are an AI assistant for a property management application called Turtle. ";
        $prompt .= "The current user is {$roleLabel}. ";
        $prompt .= "Answer questions based ONLY on the data provided below. If the information is not available, say so.\n\n";
        $prompt .= "=== CURRENT DATA (identifiers are anonymized) ===\n";

        if (!empty($context['stats'])) {
            $prompt .= "Stats:\n";
            foreach ($context['stats'] as $key => $value) {
                $prompt .= "- {$key}: {$value}\n";
            }
        }
        foreach (['properties', 'tenants', 'open_tickets', 'recent_leases'] as $section) {
            if (empty($context[$section])) {
                continue;
            }
            $prompt .= "\n" . ucfirst(str_replace('_', ' ', $section)) . ":\n";
            foreach ($context[$section] as $item) {
                $prompt .= '- ' . implode(', ', array_map(
                    static fn($key, $value) => "{$key}: {$value}",
                    array_keys($item),
                    array_values($item)
                )) . "\n";
            }
        }

        $prompt .= "\nKeep responses concise and helpful. Format with markdown where appropriate.";
        return $prompt;
    }

    private function buildContext(array $user): array
    {
        $propertyIdList = implode(',', accessible_property_ids($user)) ?: '0';

        $context = [];
        $context['stats'] = [
            'Total Properties' => count(explode(',', $propertyIdList)),
            'Active Tenants' => Database::fetch(
                "SELECT COUNT(*) as count FROM property_tenant pt
                 JOIN users u ON u.id = pt.tenant_id
                 WHERE pt.property_id IN ({$propertyIdList})
                   AND pt.moved_out_at IS NULL AND u.archived_at IS NULL"
            )['count'] ?? 0,
            'Open Tickets' => Database::fetch(
                "SELECT COUNT(*) as count FROM tickets WHERE property_id IN ({$propertyIdList}) AND status IN ('open','in_progress','awaiting_parts','awaiting_contractor') AND archived_at IS NULL"
            )['count'] ?? 0,
            'Active Leases' => Database::fetch(
                "SELECT COUNT(*) as count FROM leases WHERE property_id IN ({$propertyIdList}) AND archived_at IS NULL"
            )['count'] ?? 0,
        ];

        $context['properties'] = Database::fetchAll(
            "SELECT p.id, p.name, u.name as landlord_name,
             " . Tenancy::tenantCountSql('p') . " as tenants_count,
             (SELECT COUNT(*) FROM tickets WHERE property_id = p.id AND archived_at IS NULL) as tickets_count
             FROM properties p JOIN users u ON u.id = p.landlord_id
             WHERE p.id IN ({$propertyIdList}) AND p.archived_at IS NULL
             ORDER BY p.name"
        );
        $context['tenants'] = Database::fetchAll(
            "SELECT u.id, u.name, u.email, p.name as property_name FROM users u
             JOIN property_tenant pt ON pt.tenant_id = u.id
             JOIN properties p ON p.id = pt.property_id
             WHERE pt.property_id IN ({$propertyIdList}) AND pt.moved_out_at IS NULL AND u.archived_at IS NULL
             ORDER BY u.name"
        );
        $context['open_tickets'] = Database::fetchAll(
            "SELECT t.id, t.property_id, t.tenant_id, t.subject, t.status, p.name as property_name, u.name as tenant_name
             FROM tickets t JOIN properties p ON p.id = t.property_id JOIN users u ON u.id = t.tenant_id
             WHERE t.property_id IN ({$propertyIdList}) AND t.archived_at IS NULL
             AND t.status IN ('open','in_progress','awaiting_parts','awaiting_contractor')
             ORDER BY t.created_at DESC LIMIT 20"
        );
        $context['recent_leases'] = Database::fetchAll(
            "SELECT p.id as property_id, p.name as property_name, pt.lease_start, pt.lease_end
             FROM property_tenant pt JOIN properties p ON p.id = pt.property_id
             WHERE pt.property_id IN ({$propertyIdList}) AND pt.moved_out_at IS NULL
             ORDER BY pt.created_at DESC LIMIT 10"
        );

        return $context;
    }

    private function callOpenAI(string $apiKey, array $messages): string
    {
        $url = 'https://api.openai.com/v1/chat/completions';
        $payload = [
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'max_tokens' => 1500,
            'temperature' => 0.7,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('OpenAI API request failed: ' . $error);
        }
        $data = json_decode((string)$response, true);
        if ($httpCode !== 200) {
            $errMsg = is_array($data) ? ($data['error']['message'] ?? 'Unknown error') : 'Unknown error';
            throw new \RuntimeException('OpenAI API error (' . $httpCode . '): ' . $errMsg);
        }
        return is_array($data) ? (string)($data['choices'][0]['message']['content'] ?? '') : '';
    }
}
