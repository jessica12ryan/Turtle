<?php

namespace Tests\Unit;

use Tests\TestCase;

final class UrlHelpersTest extends TestCase
{
    public function testPlaintextEndpointIsUpgraded(): void
    {
        self::assertSame('https://time.gov/actualtime.cgi', httpsOnlyUrl('http://time.gov/actualtime.cgi'));
        self::assertSame('https://example.com/path?q=1', httpsOnlyUrl('http://example.com/path?q=1'));
    }

    public function testHttpsEndpointIsPreserved(): void
    {
        self::assertSame('https://example.com:8443/x', httpsOnlyUrl('https://example.com:8443/x'));
    }

    public function testUnsupportedEndpointsAreRejected(): void
    {
        self::assertNull(httpsOnlyUrl(''));
        self::assertNull(httpsOnlyUrl('ftp://example.com'));
        self::assertNull(httpsOnlyUrl('example.com'));
    }

    public function testRedirectTargetsAreSanitised(): void
    {
        self::assertSame('/home', sanitize_redirect_url('/home'));
        self::assertSame('/', sanitize_redirect_url('//evil.com'));
        self::assertSame('/', sanitize_redirect_url('/\\evil.com'));
        self::assertSame('/', sanitize_redirect_url('https://evil.com/path'));
        self::assertSame('/homeX-Injected: 1', sanitize_redirect_url("/home\r\nX-Injected: 1"));
    }
}
