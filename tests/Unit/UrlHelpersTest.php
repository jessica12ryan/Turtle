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

    public function testPublicHttpsUrlGuard(): void
    {
        self::assertTrue(isPublicHttpsUrl('https://1.1.1.1/actualtime.cgi'));
        $privateUrls = [
            'http://1.1.1.1/',
            'https://127.0.0.1/',
            'https://10.0.0.5/',
            'https://169.254.169.254/latest/meta-data',
            'https://192.168.1.1/',
            'https://user:pass@1.1.1.1/',
        ];
        foreach ($privateUrls as $url) {
            self::assertFalse(isPublicHttpsUrl($url), $url);
        }
    }

    public function testRelativeRedirectsAreResolvedAgainstTheRequestUrl(): void
    {
        self::assertSame('https://example.com/a/b', resolveHttpUrl('https://example.com/a/c', 'b'));
        self::assertSame('https://example.com/x', resolveHttpUrl('https://example.com/a/c', '/x'));
        self::assertSame('https://other.example/x', resolveHttpUrl('https://example.com/a', 'https://other.example/x'));
    }
}
