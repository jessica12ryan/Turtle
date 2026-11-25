<?php

namespace Tests\Unit;

use App\Controllers\AiAssistantController;
use Tests\TestCase;

final class AiAssistantTest extends TestCase
{
    public function testContextRedactionRemovesDirectIdentifiers(): void
    {
        $context = [
            'stats' => ['Total Properties' => 2],
            'properties' => [[
                'id' => 7, 'name' => 'Secret House', 'landlord_name' => 'Jane Doe',
                'tenants_count' => 3, 'tickets_count' => 2,
            ]],
            'tenants' => [[
                'id' => 9, 'name' => 'John Smith', 'email' => 'john@secret.example',
                'property_name' => 'Secret House',
            ]],
            'open_tickets' => [[
                'id' => 3, 'property_id' => 7, 'tenant_id' => 9,
                'subject' => 'John Smith reported a leak', 'status' => 'open',
                'property_name' => 'Secret House', 'tenant_name' => 'John Smith',
            ]],
            'recent_leases' => [[
                'property_id' => 7, 'property_name' => 'Secret House',
                'lease_start' => '2026-01-01', 'lease_end' => '2026-12-31',
            ]],
        ];

        $json = (string)json_encode(AiAssistantController::redactContext($context));

        self::assertStringNotContainsString('Secret House', $json);
        self::assertStringNotContainsString('Jane Doe', $json);
        self::assertStringNotContainsString('John Smith', $json);
        self::assertStringNotContainsString('john@secret.example', $json);
        self::assertStringNotContainsString('reported a leak', $json);
        self::assertStringContainsString('Property #7', $json);
        self::assertStringContainsString('Tenant #9', $json);
    }

    public function testConversationHistoryIsCapped(): void
    {
        $messages = [];
        for ($i = 1; $i <= 30; $i++) {
            $messages[] = ['role' => 'user', 'content' => (string)$i];
        }

        $capped = AiAssistantController::capMessages($messages);
        self::assertCount(AiAssistantController::MAX_MESSAGES, $capped);
        self::assertSame('11', $capped[0]['content']);
        self::assertSame('30', $capped[count($capped) - 1]['content']);
    }

    public function testMessagesAreStampedForDisplay(): void
    {
        $message = \App\Controllers\AiAssistantController::stampMessage('user', 'Hello');
        self::assertSame('user', $message['role']);
        self::assertSame('Hello', $message['content']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', (string)$message['at']);
        self::assertMatchesRegularExpression('/UTC$/', (string)$message['at_label']);
    }

    public function testConversationTitleIsSafeAndBounded(): void
    {
        self::assertSame('New chat', AiAssistantController::conversationTitle('   '));
        self::assertSame('Hello world', AiAssistantController::conversationTitle("  Hello   world \n"));
        self::assertSame(80, mb_strlen(AiAssistantController::conversationTitle(str_repeat('a', 200))));
    }
}
