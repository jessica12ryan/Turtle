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
            "INSERT INTO guest_chats (property_id, guest_name, guest_email, created_at, updated_at, last_activity)
             VALUES (?, ?, ?, NOW(), NOW(), NOW())",
            [$property, 'Jane Visitor', 'jane@example.com']
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

        $stored = Database::fetchAll("SELECT token_hash FROM guest_chat_tokens WHERE chat_id = ?", [$chatId]);
        self::assertSame([['token_hash' => hash('sha256', $token)]], $stored);
        self::assertNotSame($token, $stored[0]['token_hash'], 'the raw token must never be stored');

        $recipients = GuestChat::recipientIds($property);
        self::assertSame([$landlord], $recipients, 'a property without a manager notifies only its landlord');
        self::assertNotContains($admin, $recipients, 'admins are not notified about listing messages');
        self::assertNotContains($stranger, $recipients);
    }

    public function testListingMessagesNotifyOnlyTheAssignedPropertyManager(): void
    {
        $landlord = $this->insertUser('manager-case-landlord@example.com', 'landlord');
        $manager = $this->insertUser('manager-case-manager@example.com', 'property_manager');
        $admin = $this->insertUser('manager-case-admin@example.com', 'admin');
        $company = $this->insertCompany();
        $property = $this->insertProperty($company, $landlord, 'Managed Property');
        Database::execute("UPDATE properties SET property_manager_id = ? WHERE id = ?", [$manager, $property]);

        $recipients = GuestChat::recipientIds($property);
        self::assertSame([$manager], $recipients);
        self::assertNotContains($landlord, $recipients);
        self::assertNotContains($admin, $recipients);

        Database::execute("UPDATE users SET archived_at = NOW() WHERE id = ?", [$manager]);
        self::assertSame([], GuestChat::recipientIds($property), 'an archived manager must not receive mail');
    }

    public function testAdditionalConversationLinksDoNotDisableEarlierOnes(): void
    {
        $landlord = $this->insertUser('recover-owner@example.com', 'landlord');
        $company = $this->insertCompany();
        $property = $this->insertProperty($company, $landlord, 'Recoverable Property');
        $originalToken = GuestChat::generateToken();
        $chatId = Database::insert(
            "INSERT INTO guest_chats (property_id, guest_name, guest_email, created_at, updated_at, last_activity)
             VALUES (?, ?, ?, NOW(), NOW(), NOW())",
            [$property, 'Returning Visitor', 'Case@Example.com']
        );
        GuestChat::registerToken((int) $chatId, $originalToken);

        $newToken = GuestChat::issueToken((int) $chatId);
        self::assertTrue(GuestChat::isValidToken($newToken));
        self::assertNotSame($originalToken, $newToken);
        self::assertNotNull(GuestChat::findByToken($originalToken), 'the original emailed link must keep working');
        self::assertNotNull(GuestChat::findByToken($newToken), 'the new link must work too');

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

    public function testGuestHubGroupsConversationsByEmailAndReusesThreads(): void
    {
        $landlord = $this->insertUser('hub-owner@example.com', 'landlord');
        $company = $this->insertCompany();
        $propertyA = $this->insertProperty($company, $landlord, 'Hub Property A');
        $propertyB = $this->insertProperty($company, $landlord, 'Hub Property B');

        $guestId = GuestChat::findOrCreateVisitor(' Hub@Example.com ', 'Guest Name');
        self::assertGreaterThan(0, $guestId);
        self::assertSame($guestId, GuestChat::findOrCreateVisitor('hub@example.com', 'Updated Name'));
        $visitor = GuestChat::findVisitorByEmail('HUB@example.com');
        self::assertNotNull($visitor);
        self::assertSame('Updated Name', $visitor['name']);

        $tokenA = GuestChat::generateToken();
        $chatA = Database::insert(
            "INSERT INTO guest_chats (property_id, guest_name, guest_email, created_at, updated_at, last_activity)
             VALUES (?, ?, ?, NOW(), NOW(), NOW())",
            [$propertyA, 'Guest Name', 'hub@example.com']
        );
        GuestChat::registerToken((int) $chatA, $tokenA);
        $tokenB = GuestChat::generateToken();
        $chatB = Database::insert(
            "INSERT INTO guest_chats (property_id, guest_name, guest_email, created_at, updated_at, last_activity)
             VALUES (?, ?, ?, NOW(), NOW(), NOW())",
            [$propertyB, 'Guest Name', 'hub@example.com']
        );
        GuestChat::registerToken((int) $chatB, $tokenB);

        $hubToken = GuestChat::issueHubToken($guestId);
        $fromToken = GuestChat::findVisitorByHubToken($hubToken);
        self::assertNotNull($fromToken);
        self::assertSame($guestId, $fromToken['id']);
        self::assertSame('hub@example.com', $fromToken['email']);
        self::assertNull(GuestChat::findVisitorByHubToken(str_repeat('0', 64)));

        $chats = GuestChat::chatsForVisitor($guestId);
        self::assertCount(2, $chats);
        self::assertContains('Hub Property A', array_column($chats, 'property_name'));
        self::assertContains('Hub Property B', array_column($chats, 'property_name'));
        $existing = GuestChat::findChatForVisitorProperty($guestId, $propertyA);
        self::assertNotNull($existing);
        self::assertSame((string) $chatA, (string) $existing['id']);
        self::assertNull(GuestChat::findChatForVisitorProperty($guestId, 999999));

        GuestChat::appendMessage((int) $chatA, 'A follow-up question');
        $messages = Database::fetchAll(
            "SELECT body FROM guest_chat_messages WHERE chat_id = ? ORDER BY id ASC",
            [$chatA]
        );
        self::assertCount(1, $messages);
        self::assertSame('A follow-up question', $messages[0]['body']);
        self::assertNotNull(GuestChat::findByToken($tokenB), 'other property link must still work');
    }

    public function testInboxRowsAndUnreadBadgeAreScopedForStaff(): void
    {
        $landlord = $this->insertUser('inbox-landlord@example.com', 'landlord');
        $admin = $this->insertUser('inbox-admin@example.com', 'admin');
        $outsider = $this->insertUser('inbox-outsider@example.com', 'landlord');
        $company = $this->insertCompany();
        $property = $this->insertProperty($company, $landlord, 'Inbox Property');
        Database::insert("INSERT INTO company_user (company_id, user_id) VALUES (?, ?)", [$company, $landlord]);

        $token = GuestChat::generateToken();
        $chatId = Database::insert(
            "INSERT INTO guest_chats (property_id, guest_name, guest_email, created_at, updated_at, last_activity)
             VALUES (?, ?, ?, NOW(), NOW(), NOW())",
            [$property, 'Inbox Guest', 'inbox@example.com']
        );
        GuestChat::registerToken((int) $chatId, $token);
        Database::insert(
            "INSERT INTO guest_chat_messages (chat_id, sender_type, body, created_at) VALUES (?, 'guest', ?, NOW())",
            [$chatId, 'Any updates?']
        );

        $adminRow = Database::fetch("SELECT * FROM users WHERE id = ?", [$admin]);
        $landlordRow = Database::fetch("SELECT * FROM users WHERE id = ?", [$landlord]);
        $outsiderRow = Database::fetch("SELECT * FROM users WHERE id = ?", [$outsider]);
        self::assertNotNull($adminRow);
        self::assertNotNull($landlordRow);
        self::assertNotNull($outsiderRow);

        $rows = GuestChat::scopedChats($adminRow);
        self::assertCount(1, $rows);
        self::assertSame('inbox@example.com', $rows[0]['guest_email']);
        self::assertSame(1, (int) $rows[0]['unread']);
        self::assertSame(1, GuestChat::unreadCount($adminRow));
        self::assertSame(1, GuestChat::unreadCount($landlordRow));
        self::assertSame(0, GuestChat::unreadCount($outsiderRow));

        Database::execute("UPDATE guest_chat_messages SET read_at = NOW() WHERE chat_id = ?", [$chatId]);
        self::assertSame(0, GuestChat::unreadCount($adminRow));
    }

    public function testArchivedConversationsAreHiddenAndCanBeRestoredOrDeleted(): void
    {
        $landlord = $this->insertUser('archive-owner@example.com', 'landlord');
        $admin = $this->insertUser('archive-admin@example.com', 'admin');
        $company = $this->insertCompany();
        $property = $this->insertProperty($company, $landlord, 'Archived Property');
        $guestId = GuestChat::findOrCreateVisitor('archive@example.com', 'Archive Guest');
        $token = GuestChat::generateToken();
        $chatId = Database::insert(
            "INSERT INTO guest_chats (property_id, guest_name, guest_email, created_at, updated_at, last_activity)
             VALUES (?, ?, ?, NOW(), NOW(), NOW())",
            [$property, 'Archive Guest', 'archive@example.com']
        );
        GuestChat::registerToken((int) $chatId, $token);
        Database::insert(
            "INSERT INTO guest_chat_messages (chat_id, sender_type, body, created_at) VALUES (?, 'guest', ?, NOW())",
            [$chatId, 'Before archive']
        );
        $adminRow = Database::fetch("SELECT * FROM users WHERE id = ?", [$admin]);
        self::assertNotNull($adminRow);
        self::assertNotNull(GuestChat::findByToken($token));
        self::assertCount(1, GuestChat::chatsForVisitor($guestId));

        Database::execute("UPDATE guest_chats SET archived_at = NOW() WHERE id = ?", [$chatId]);
        self::assertNull(GuestChat::findByToken($token), 'guests lose access to archived chats');
        self::assertSame([], GuestChat::chatsForVisitor($guestId));
        self::assertNull(GuestChat::findChatForVisitorProperty($guestId, $property));
        self::assertCount(0, GuestChat::scopedChats($adminRow));
        self::assertCount(1, GuestChat::scopedChats($adminRow, true));
        self::assertSame(0, GuestChat::unreadCount($adminRow));

        Database::execute("UPDATE guest_chats SET archived_at = NULL WHERE id = ?", [$chatId]);
        self::assertNotNull(GuestChat::findByToken($token), 'restoring re-enables the guest link');
        self::assertCount(1, GuestChat::scopedChats($adminRow));

        Database::execute("DELETE FROM guest_chats WHERE id = ?", [$chatId]);
        self::assertNull(GuestChat::findByToken($token));
        self::assertSame([], Database::fetchAll("SELECT id FROM guest_chat_messages WHERE chat_id = ?", [$chatId]));
        self::assertSame([], Database::fetchAll("SELECT id FROM guest_chat_tokens WHERE chat_id = ?", [$chatId]));
    }

    public function testTokensStopWorkingWhenPropertyIsArchived(): void
    {
        $landlord = $this->insertUser('archived-property-owner@example.com', 'landlord');
        $company = $this->insertCompany();
        $property = $this->insertProperty($company, $landlord, 'Soon Archived Property');
        $guestId = GuestChat::findOrCreateVisitor('archived-property@example.com', 'Archived Guest');
        $token = GuestChat::generateToken();
        $chatId = Database::insert(
            "INSERT INTO guest_chats (property_id, guest_name, guest_email, created_at, updated_at, last_activity)
             VALUES (?, ?, ?, NOW(), NOW(), NOW())",
            [$property, 'Archived Guest', 'archived-property@example.com']
        );
        GuestChat::registerToken((int)$chatId, $token);

        self::assertNotNull(GuestChat::findByToken($token));
        self::assertCount(1, GuestChat::chatsForVisitor($guestId));

        Database::execute("UPDATE properties SET archived_at = NOW() WHERE id = ?", [$property]);

        self::assertNull(GuestChat::findByToken($token), 'a chat link must not survive property archival');
        self::assertSame([], GuestChat::chatsForVisitor($guestId), 'the hub must not list archived properties');
        self::assertNull(GuestChat::findChatForVisitorProperty($guestId, $property));
    }

    public function testGuestAndStaffReadStateAreTrackedSeparately(): void
    {
        $landlord = $this->insertUser('owner@example.com', 'landlord');
        $company = $this->insertCompany();
        $property = $this->insertProperty($company, $landlord);
        $chatId = Database::insert(
            "INSERT INTO guest_chats (property_id, guest_name, guest_email, created_at, updated_at, last_activity)
             VALUES (?, ?, ?, NOW(), NOW(), NOW())",
            [$property, 'Visitor', 'visitor@example.com']
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
