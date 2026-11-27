<?php

namespace Tests\Unit;

use App\Core\View;
use Tests\TestCase;

final class PublicListingsVisibilityTest extends TestCase
{
    public function testEmptyListingsHideTheConversationSection(): void
    {
        $view = new View();

        ob_start();
        $view->render('listings/index', [
            'properties' => [],
            'photos' => [],
            'conversations' => [],
            'continueChats' => [],
            'hub' => null,
        ]);
        $output = (string) ob_get_clean();

        self::assertStringNotContainsString('data-testid="return-conversation"', $output);
        self::assertStringNotContainsString('recovery-form', $output);
        self::assertStringContainsString('No properties are currently listed.', $output);
    }

    public function testListedPropertiesShowTheConversationSection(): void
    {
        $view = new View();

        ob_start();
        $view->render('listings/index', [
            'properties' => [[
                'id' => 1,
                'name' => 'Test Property',
                'address' => '1 Main St',
                'city' => 'Testville',
                'province' => 'ON',
                'rent_amount' => 1000,
                'ad_description' => '',
                'main_photo_id' => null,
            ]],
            'photos' => [],
            'conversations' => [],
            'continueChats' => [],
            'hub' => null,
        ]);
        $output = (string) ob_get_clean();

        self::assertStringContainsString('data-testid="return-conversation"', $output);
        self::assertStringContainsString('recovery-form', $output);
    }

    public function testApplicationLinkFollowsTheListedPropertiesFlag(): void
    {
        self::assertStringContainsString('Application for Tenancy', $this->renderPublicLayout(true, true));
        self::assertStringNotContainsString('Application for Tenancy', $this->renderPublicLayout(false, true));
    }

    public function testApplicationLinkRequiresEnabledApplications(): void
    {
        self::assertStringNotContainsString('Application for Tenancy', $this->renderPublicLayout(true, false));
    }

    private function renderPublicLayout(bool $hasListedProperties, bool $applicationsEnabled = true): string
    {
        $view = new View();
        $view->layout('layouts/public', [
            'title' => 'Listings',
            'hasListedProperties' => $hasListedProperties,
            'applicationsEnabled' => $applicationsEnabled,
        ]);

        ob_start();
        $view->render('../../tests/fixtures/view_data', ['data' => ['key' => 'value']]);
        return (string) ob_get_clean();
    }
}
