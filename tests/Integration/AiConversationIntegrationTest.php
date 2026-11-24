<?php

namespace Tests\Integration;

use App\Controllers\AiAssistantController;
use App\Core\Database;

final class AiConversationIntegrationTest extends IntegrationTestCase
{
    private function capture(callable $call): array
    {
        ob_start();
        $call();
        $raw = (string)ob_get_clean();
        return is_array(json_decode($raw, true)) ? json_decode($raw, true) : [];
    }

    private function enableFeature(string $value): void
    {
        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES ('ai_assistant_enabled', ?)
             ON DUPLICATE KEY UPDATE `value` = ?",
            [$value, $value]
        );
        clearSettingCache();
    }

    public function testConversationsAreScopedToTheirOwner(): void
    {
        $owner = $this->insertUser('ai-owner@example.test', 'landlord');
        $other = $this->insertUser('ai-other@example.test', 'landlord');
        $this->enableFeature('1');

        $_SESSION['user_id'] = $owner;
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(16));
        $csrf = (string)$_SESSION['_csrf_token'];
        $controller = new AiAssistantController();

        try {
            $_POST = ['_csrf' => $csrf];
            $created = $this->capture(static fn() => $controller->createConversation());
            $id = (int)($created['id'] ?? 0);
            self::assertGreaterThan(0, $id, 'an enabled staff user can create a chat');

            $_POST = [];
            $loaded = $this->capture(static fn() => $controller->getConversation($id));
            self::assertSame([], $loaded['messages'] ?? null);

            // Another user must not be able to read or delete this chat.
            $_SESSION['user_id'] = $other;
            $_POST = [];
            $foreign = $this->capture(static fn() => $controller->getConversation($id));
            self::assertSame('Chat not found.', $foreign['error'] ?? null);

            $_POST = ['_csrf' => $csrf];
            $this->capture(static fn() => $controller->deleteConversation($id));
            $row = Database::fetch("SELECT id FROM ai_conversations WHERE id = ?", [$id]);
            self::assertNotNull($row, 'another user cannot delete the chat');

            $_SESSION['user_id'] = $owner;
            $_POST = ['_csrf' => $csrf];
            $this->capture(static fn() => $controller->deleteConversation($id));
            self::assertNull(Database::fetch("SELECT id FROM ai_conversations WHERE id = ?", [$id]));
        } finally {
            $_POST = [];
            unset($_SESSION['user_id']);
        }
    }

    public function testFeatureGateBlocksTenantsAndDisabledFeature(): void
    {
        $tenant = $this->insertUser('ai-tenant@example.test', 'tenant');
        $this->enableFeature('1');
        $_SESSION['user_id'] = $tenant;
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(16));
        $_POST = ['_csrf' => (string)$_SESSION['_csrf_token']];
        $controller = new AiAssistantController();

        try {
            $blocked = $this->capture(static fn() => $controller->createConversation());
            self::assertSame('AI Assistant is not available.', $blocked['error'] ?? null);

            $this->enableFeature('0');
            $_SESSION['user_id'] = $this->insertUser('ai-staff@example.test', 'landlord');
            $disabled = $this->capture(static fn() => $controller->createConversation());
            self::assertSame('AI Assistant is not available.', $disabled['error'] ?? null);
        } finally {
            $_POST = [];
            unset($_SESSION['user_id']);
        }
    }
}
