<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Core\GuestChat;

final class GuestChatIntegrationTest extends IntegrationTestCase
{
    public function testVisitorChatIsTokenPrivateAndStoresOnlyAHash(): void
    {
        $landlord = $this->insertUser('landlord@example.com', 'landlord');
        $admin = $this->insertUser('admin@example.com', 'admin');
        $stranger = $this->insertUser('stranger@example.com', 'tenant');
        $company = $this->insertCompany();
        $property = $this->insertProperty($company, $landlord, 'Listed Property');
        Database::execute("UPDATE properties SET listed = 1 WHERE id = ?", [$property]);

        $token = GuestChat::generateToken();
        $chatId = Database::insert(
            "INSERT INTO guest_chats (property_id, guest_name, guest_email, token_hash, created_at, updated_at, last_activity)
             VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())",
            [$property, 'Jane Visitor', 'jane@example.com', GuestChat::hashToken($token)]
        );
        Database::insert(
            "INSERT INTO guest_chat_messages (chat_id, sender_type, body, created_at) VALUES (?, 'guest', ?, NOW())",
            [$chatId, 'Is this still available?']
        );
        GuestChat::registerToken((int) $chatId, $token);

        $chat = GuestChat::findByToken($token);
        self::assertNotNull($chat);
        self::assertSame((string) $chatId, (string) $chat['id']);
        self::assertSame('Listed Property', $chat['property_name']);

        self::assertNull(GuestChat::findByToken(str_repeat('a', 64)), 'a different token must not open the chat');
        self::assertNull(GuestChat::findByToken('not-a-valid-token'));

        $stored = Database::fetch("SELECT token_hash FROM guest_chats WHERE id = ?", [$chatId]);
        self::assertSame(hash('sha256', $token), $stored['token_hash']);
        self::assertNotSame($token, $stored['token_hash'], 'the raw token must never be stored');

        $recipients = GuestChat::recipientIds($property);
        self::assertContains($landlord, $recipients);
        self::assertContains($admin, $recipients, 'admins are a fallback so a chat is never orphaned');
        self::assertNotContains($stranger, $recipients);
    }

    public function testAdditionalConversationLinksDoNotDisableEarlierOnes(): void
    {
        $landlord = $this->insertUser('recover-owner@example.com', 'landlord');
        $company = $this->insertCompany();
        $property = $this->insertProperty($company, $landlord, 'Recoverable Property');
        $originalToken = GuestChat::generateToken();
        $chatId = Database::insert(
            "INSERT INTO guest_chats (property_id, guest_name, guest_email, token_hash, created_at, updated_at, last_activity)
             VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())",
            [$property, 'Returning Visitor', 'Case@Example.com', GuestChat::hashToken($originalToken)]
        );
        GuestChat::registerToken((int) $chatId, $originalToken);

        $found = GuestChat::findByEmail(' case@example.com ');
        self::assertCount(1, $found);
        self::assertSame((string) $chatId, (string) $found[0]['id']);
        self::assertSame('Recoverable Property', $found[0]['property_name']);
        self::assertSame([], GuestChat::findByEmail('nobody@example.com'));

        $newToken = GuestChat::issueToken((int) $chatId);
        self::assertTrue(GuestChat::isValidToken($newToken));
        self::assertNotSame($originalToken, $newToken);
        self::assertNotNull(GuestChat::findByToken($originalToken), 'the original emailed link must keep working');
        self::assertNotNull(GuestChat::findByToken($newToken), 'the new link must work too');

        $stored = Database::fetch("SELECT token_hash FROM guest_chats WHERE id = ?", [$chatId]);
        self::assertSame(GuestChat::hashToken($originalToken), $stored['token_hash'], 'the original hash must not be replaced');
        self::assertNotSame($originalToken, $stored['token_hash'], 'the raw token must never be stored');
        $hashes = Database::fetchAll(
            "SELECT token_hash FROM guest_chat_tokens WHERE chat_id = ? ORDER BY id ASC",
            [$chatId]
        );
        self::assertSame([
            ['token_hash' => GuestChat::hashToken($originalToken)],
            ['token_hash' => GuestChat::hashToken($newToken)],
        ], $hashes);
        self::assertSame(hash('sha256', 'case@example.com'), GuestChat::emailKey(' Case@Example.com '));
    }

    public function testGuestAndStaffReadStateAreTrackedSeparately(): void
    {
        $landlord = $this->insertUser('owner@example.com', 'landlord');
        $company = $this->insertCompany();
        $property = $this->insertProperty($company, $landlord);
        $chatId = Database::insert(
            "INSERT INTO guest_chats (property_id, guest_name, guest_email, token_hash, created_at, updated_at, last_activity)
             VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())",
            [$property, 'Visitor', 'visitor@example.com', GuestChat::hashToken(GuestChat::generateToken())]
        );
        Database::insert(
            "INSERT INTO guest_chat_messages (chat_id, sender_type, body, created_at) VALUES (?, 'guest', ?, NOW())",
            [$chatId, 'Hello']
        );
        Database::insert(
            "INSERT INTO guest_chat_messages (chat_id, sender_type, sender_id, body, created_at) VALUES (?, 'staff', ?, ?, NOW())",
            [$chatId, $landlord, 'Hi there']
        );

        Database::execute(
            "UPDATE guest_chat_messages SET read_at = NOW() WHERE chat_id = ? AND sender_type = 'guest'",
            [$chatId]
        );
        Database::execute(
            "UPDATE guest_chat_messages SET guest_read_at = NOW() WHERE chat_id = ? AND sender_type = 'staff'",
            [$chatId]
        );

        $rows = Database::fetchAll("SELECT sender_type, read_at, guest_read_at FROM guest_chat_messages WHERE chat_id = ?", [$chatId]);
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            if ($row['sender_type'] === 'guest') {
                self::assertNotNull($row['read_at']);
                self::assertNull($row['guest_read_at']);
            } else {
                self::assertNull($row['read_at']);
                self::assertNotNull($row['guest_read_at']);
            }
        }
    }
}
