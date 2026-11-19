<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\GoogleOAuth;
use App\Core\Logger;
use App\Core\Mailer;

class OAuthController
{
    /**
     * Start the Google authorization-code flow. The client credentials must
     * already be saved, because the in-app flow uses them on the callback too.
     */
    public function googleConnect(): void
    {
        $clientId = Mailer::getConfig('mail_oauth_client_id');
        $clientSecret = Mailer::getConfig('mail_oauth_client_secret');
        if ($clientId === '' || $clientSecret === '') {
            flash('error', __('Save the Google Client ID and Client Secret before connecting.'));
            redirect('/settings?tab=general');
        }

        $state = bin2hex(random_bytes(24));
        $_SESSION['oauth_google_state'] = $state;
        $authUrl = GoogleOAuth::authorizationUrl($clientId, $state);

        // The shared redirect() helper deliberately blocks absolute external
        // URLs; whitelist only Google's authorization endpoint here.
        if (!str_starts_with($authUrl, 'https://accounts.google.com/o/oauth2/v2/auth?')) {
            flash('error', __('Could not build a valid Google authorization URL.'));
            redirect('/settings?tab=general');
        }
        header('Location: ' . $authUrl);
        exit;
    }

    /**
     * Google redirects here with the one-time code. Store the refresh token
     * so future mail sends can mint access tokens without user interaction.
     */
    public function googleCallback(): void
    {
        $state = (string)($_GET['state'] ?? '');
        $expected = (string)($_SESSION['oauth_google_state'] ?? '');
        unset($_SESSION['oauth_google_state']);

        if ($expected === '' || $state === '' || !hash_equals($expected, $state)) {
            Logger::warning('Google OAuth callback rejected: invalid state.');
            flash('error', __('Invalid OAuth state. Please try connecting again.'));
            redirect('/settings?tab=general');
        }

        if (!empty($_GET['error'])) {
            $reason = strip_tags((string)$_GET['error']);
            Logger::error('Google OAuth callback returned an error: ' . $reason);
            flash('error', __('Google could not complete the connection: :reason', [':reason' => $reason]));
            redirect('/settings?tab=general');
        }

        $code = (string)($_GET['code'] ?? '');
        if ($code === '') {
            flash('error', __('Google did not return an authorization code.'));
            redirect('/settings?tab=general');
        }

        $tokens = GoogleOAuth::exchangeCode(
            $code,
            Mailer::getConfig('mail_oauth_client_id'),
            Mailer::getConfig('mail_oauth_client_secret')
        );

        if (empty($tokens['refresh_token'])) {
            // Google only issues a refresh token when offline access is granted.
            flash('error', __('Google did not return a refresh token. Confirm the OAuth client is configured correctly and try again.'));
            redirect('/settings?tab=general');
        }

        GoogleOAuth::saveTokens($tokens);
        log_activity('settings.mail_oauth_connected', __('Gmail OAuth2 connected'));
        flash('success', __('Gmail OAuth2 connected successfully.'));
        redirect('/settings?tab=general');
    }

    public function googleDisconnect(): void
    {
        if (!isset($_POST['_csrf']) || !verify_csrf($_POST['_csrf'])) {
            flash('error', __('Invalid security token.'));
            redirect('/settings?tab=general');
        }

        $keys = [
            'mail_oauth_refresh_token',
            'mail_oauth_access_token',
            'mail_oauth_expires_at',
            'mail_oauth_email',
        ];
        foreach ($keys as $key) {
            try {
                Database::execute(
                    "INSERT INTO settings (`key`, `value`) VALUES (?, '') ON DUPLICATE KEY UPDATE `value` = ''",
                    [$key]
                );
            } catch (\Throwable $e) {
                Logger::error('Could not clear OAuth setting ' . $key . ': ' . $e->getMessage());
            }
        }

        log_activity('settings.mail_oauth_disconnected', __('Gmail OAuth2 disconnected'));
        flash('success', __('Gmail OAuth2 disconnected.'));
        redirect('/settings?tab=general');
    }
}
