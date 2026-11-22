<?php

namespace Tests\Unit;

use Tests\TestCase;

final class HelpersTest extends TestCase
{
    public function testHEscapesQuotesAndHtml(): void
    {
        self::assertSame('&lt;a href=&quot;x&quot;&gt;&amp;&#039;', h('<a href="x">&\''));
        self::assertSame('123', h(123));
        self::assertSame('', h(null));
    }

    public function testSanitizeFilenameStripsTraversalControlCharsAndCrlf(): void
    {
        self::assertSame('logo.png', sanitize_filename('../logo.png'));
        self::assertSame('evilname.txt', sanitize_filename("evil\r\nname.txt"));
        self::assertSame('ab', sanitize_filename("a\x1Fb"));
        self::assertSame('file.txt', sanitize_filename('..\\..\\file.txt'));
        self::assertSame(255, strlen(sanitize_filename(str_repeat('a', 300))));
    }

    public function testCsrfTokenAndVerification(): void
    {
        $token = csrf_token();

        self::assertSame(64, strlen($token));
        self::assertSame($token, csrf_token(), 'token must be stable within a session');
        self::assertTrue(verify_csrf($token));
        self::assertFalse(verify_csrf('wrong-token'));
        self::assertFalse(verify_csrf(''));
    }

    public function testFlashMessageIsConsumedOnRead(): void
    {
        self::assertNull(flash('success'));
        flash('success', 'Saved!');

        self::assertSame('Saved!', flash('success'));
        self::assertNull(flash('success'));
    }

    public function testOldReadsFromSessionAndEscapes(): void
    {
        $_SESSION['_old']['name'] = '<b>Ada</b>';

        self::assertSame('&lt;b&gt;Ada&lt;/b&gt;', old('name'));
        self::assertSame('default', old('missing', 'default'));
        self::assertSame(['a' => 1], old_raw('items', ['a' => 1]));
    }

    public function testBaseUrlAndAssetWithoutIngressPrefix(): void
    {
        self::assertSame('', base_url());
        self::assertSame('/assets/app.css', asset('/assets/app.css'));
        self::assertSame('assets/app.css', asset('assets/app.css'));
    }

    public function testInitialsFor(): void
    {
        self::assertSame('JM', initials_for('John Michael Smith'));
        self::assertSame('A', initials_for('  ada  '));
        self::assertSame('?', initials_for(''));
    }

    public function testRegionsAndLabels(): void
    {
        self::assertSame('Ontario', regions('CA')['ON']);
        self::assertSame('California', regions('US')['CA']);
        self::assertSame('Ontario', provinces()['ON']);
        self::assertSame('State', region_label('US'));
        self::assertSame('Province', region_label('CA'));
        self::assertSame('Zip Code', postal_label('US'));
        self::assertSame('Postal Code', postal_label('CA'));
    }

    public function testDefaultPermissionsAndNotifications(): void
    {
        $permissions = defaultPermissions();

        self::assertContains('tickets.create', $permissions['tenant']);
        self::assertContains('properties.create', $permissions['landlord']);
        self::assertNotContains('properties.create', $permissions['maintenance']);
        self::assertContains('guest_chats.archive', $permissions['landlord']);
        self::assertContains('guest_chats.restore', $permissions['landlord']);
        self::assertNotContains('guest_chats.archive', $permissions['property_manager']);
        foreach ($permissions as $perms) {
            self::assertNotContains('guest_chats.delete', $perms);
        }

        $notifications = defaultNotifications();
        self::assertContains('admin', $notifications['ticket_created']);
        self::assertContains('tenant', $notifications['ticket_status_updated']);
    }

    public function testTicketAssignmentCommentBody(): void
    {
        self::assertSame(
            'Jane assigned ticket to Bob',
            ticket_assignment_comment_body('Jane', 'Bob')
        );
        self::assertSame(
            'Jane assigned ticket to Unassigned',
            ticket_assignment_comment_body('Jane', null)
        );
    }

    public function testTimestampAndDisplayHelpers(): void
    {
        self::assertSame(1_577_836_800, db_timestamp_epoch('2020-01-01 00:00:00'));
        self::assertNull(db_timestamp_epoch(null));
        self::assertNull(db_timestamp_epoch('not-a-date'));
        self::assertSame('2020-01-01', display_time('2020-01-01 00:00:00', 'Y-m-d'));
        self::assertSame('', display_time(null));
    }

    public function testAvatarUrl(): void
    {
        self::assertSame('/users/7/avatar', avatar_url(['id' => 7, 'avatar_path' => 'a.png']));
        self::assertNull(avatar_url(['id' => 7, 'avatar_path' => null]));
        self::assertNull(avatar_url(null));
    }

    public function testRenderAdDescriptionSubstitutesPlaceholdersAndEscapes(): void
    {
        $property = [
            'ad_description' => '{{name}} in {{city}} for $' . '{{rent}}' . "\n" . '<strong>Book now</strong>',
            'name' => '<b>Wellington</b>',
            'city' => 'Toronto',
            'rent_amount' => 2200,
        ];

        $html = render_ad_description($property);

        self::assertStringContainsString('&lt;b&gt;Wellington&lt;/b&gt; in Toronto for $2,200.00', $html);
        self::assertStringContainsString('&lt;strong&gt;Book now&lt;/strong&gt;', $html, 'manager copy must be escaped');
        self::assertStringContainsString('<br>', $html);
    }

    public function testRenderAdDescriptionWithoutTemplateIsEmpty(): void
    {
        self::assertSame('', render_ad_description(['ad_description' => null, 'name' => 'Test']));
        self::assertSame('', render_ad_description(['name' => 'Test']));
    }
}
