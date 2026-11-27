<?php

namespace Tests\Unit;

use App\Core\View;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class LoginApplicationLinkVisibilityTest extends TestCase
{
    public function testApplicationLinkRequiresAListedPropertyAndEnabledApplications(): void
    {
        self::assertStringNotContainsString('/applications/create', $this->renderLogin(false, false));
        self::assertStringNotContainsString('/applications/create', $this->renderLogin(true, false));
        self::assertStringNotContainsString('/applications/create', $this->renderLogin(false, true));
        self::assertStringContainsString('/applications/create', $this->renderLogin(true, true));
    }

    public function testOtherLoginLinksAreUnaffected(): void
    {
        $output = $this->renderLogin(false, false);

        self::assertStringContainsString('/forgot-password', $output);
        self::assertStringContainsString('/onboarding', $output);
    }

    #[DataProvider('languageProvider')]
    public function testApplicationLinkIsTranslatedInEveryLanguage(string $language, string $label): void
    {
        $_SESSION['_language'] = $language;

        self::assertStringContainsString($label, $this->renderLogin(true, true));
        self::assertStringNotContainsString($label, $this->renderLogin(false, true));
    }

    public static function languageProvider(): array
    {
        return [
            'en' => ['en', 'Application for Tenancy'],
            'fr' => ['fr', 'Demande de location'],
            'es' => ['es', 'Solicitud de alquiler'],
        ];
    }

    private function renderLogin(bool $hasListedProperties, bool $applicationsEnabled): string
    {
        $view = new View();

        ob_start();
        $view->render('auth/login', [
            'hasListedProperties' => $hasListedProperties,
            'applicationsEnabled' => $applicationsEnabled,
        ]);
        return (string) ob_get_clean();
    }
}
