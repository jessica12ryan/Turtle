<?php

namespace Tests\Unit;

use App\Core\Mailer;
use Tests\TestCase;

final class MailerTest extends TestCase
{
    public function testHeaderSanitisationRemovesCrlfAndControlCharacters(): void
    {
        self::assertSame('Hello Bcc: evil@example.com', Mailer::sanitizeHeader("Hello\r\nBcc: evil@example.com"));
        self::assertSame('a b', Mailer::sanitizeHeader("a\x00\x1Fb"));
        self::assertSame('', Mailer::sanitizeHeader("\r\n"));
    }

    public function testAddressValidationRejectsInjectionAndInvalidAddresses(): void
    {
        self::assertSame('tenant@example.com', Mailer::sanitizeAddress('tenant@example.com'));
        self::assertNull(Mailer::sanitizeAddress("tenant@example.com\r\nRCPT TO:<evil@example.com>"));
        self::assertNull(Mailer::sanitizeAddress('not-an-email'));
        self::assertNull(Mailer::sanitizeAddress(''));
    }

    public function testSubjectEncodingNeverCarriesRawInjectedHeaders(): void
    {
        $encoded = Mailer::encodeHeader("Hi\r\nBcc: evil@example.com");
        self::assertStringNotContainsString("\r", $encoded);
        self::assertStringNotContainsString("\n", $encoded);
        self::assertSame('Hi Bcc: evil@example.com', $encoded);
    }

    public function testNonAsciiSubjectIsEncoded(): void
    {
        $encoded = Mailer::encodeHeader('Réparation urgente');
        self::assertStringStartsWith('=?UTF-8?B?', $encoded);
    }

    public function testDotStuffingPreservesLinesStartingWithDot(): void
    {
        self::assertSame("a\r\n..\r\nb", Mailer::dotStuff("a\r\n.\r\nb"));
        self::assertSame("..leading", Mailer::dotStuff(".leading"));
        self::assertSame("no change", Mailer::dotStuff('no change'));
    }

    public function testHtmlEscaping(): void
    {
        self::assertSame('&lt;b&gt;&amp;&#039;&quot;', Mailer::escapeHtml('<b>&\'"'));
    }
}
