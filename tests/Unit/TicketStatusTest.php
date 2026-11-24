<?php

namespace Tests\Unit;

use Tests\TestCase;

final class TicketStatusTest extends TestCase
{
    public function testKnownStatusesAreAccepted(): void
    {
        self::assertTrue(is_valid_ticket_status('open'));
        self::assertTrue(is_valid_ticket_status('in_progress'));
        self::assertTrue(is_valid_ticket_status('awaiting_parts'));
        self::assertTrue(is_valid_ticket_status('awaiting_contractor'));
        self::assertTrue(is_valid_ticket_status('resolved'));
        self::assertTrue(is_valid_ticket_status('closed'));
    }

    public function testArbitraryStatusesAreRejected(): void
    {
        self::assertFalse(is_valid_ticket_status(''));
        self::assertFalse(is_valid_ticket_status("closed' OR 1=1"));
        self::assertFalse(is_valid_ticket_status("closed\r\nBcc: evil@example.com"));
        self::assertFalse(is_valid_ticket_status('unknown'));
    }
}
