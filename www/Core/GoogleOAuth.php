<?php

namespace App\Core;

/**
 * Google OAuth2 for SMTP (XOAUTH2).
 *
 * Access tokens are short-lived, so the refresh token is what actually needs
 * to be configured. Tokens are stored in the settings table, which is
 * persistent in both Docker and the HA add-on.
 */
class GoogleOAuth
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPE = 'https://mail.google.com/';

    public static function redirectUri(): string
    {
        // Home Assistant ingress proxies the add-on under a generated prefix
        // and reports it via X-Forwarded-Prefix. Google must redirect to that
        // public URL so the browser reaches the app again.
        $prefix = \ingress_prefix();
        if ($prefix !== '') {
            $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || in_array($forwardedProto, ['https', 'https, http'], true);
            $scheme = $https ? 'https' : 'http';
            $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
            return $scheme . '://' . $host . rtrim($prefix, '/') . '/oauth/google/callback';
        }

        return rtrim(\site_url(), '/') . '/oauth/google/callback';
    }

    /**
     * The SASL XOAUTH2 initial response sent after AUTH XOAUTH2.
     */
    public static function xoauth2Blob(string $email, string $token): string
    {
        return "user={$email}\x01auth=Bearer {$token}\x01\x01";
    }

    public static function authorizationUrl(string $clientId, string $state): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /**
     * Exchange the one-time authorization code for access + refresh tokens.
     *
     * @return array<string, string>
     */
    public static function exchangeCode(string $code, string $clientId, string $clientSecret): array
    {
        return self::post([
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => self::redirectUri(),
            'grant_type' => 'authorization_code',
        ]);
    }

    /**
     * Mint a fresh access token from a refresh token.
     *
     * @return array<string, string>
     */
    public static function refreshAccessToken(string $refreshToken, string $clientId, string $clientSecret): array
    {
        return self::post([
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'refresh_token',
        ]);
    }

    /**
     * Persist a token response. Google only returns a refresh token on the
     * initial exchange (or on rotation), so never overwrite it with emptiness.
     *
     * @param array<string, string> $tokens
     */
    public static function saveTokens(array $tokens): void
    {
        if (!empty($tokens['access_token'])) {
            self::setSetting('mail_oauth_access_token', (string)$tokens['access_token']);
            self::setSetting('mail_oauth_expires_at', (string)(time() + max(60, (int)($tokens['expires_in'] ?? 3600))));
        }
        if (!empty($tokens['refresh_token'])) {
            self::setSetting('mail_oauth_refresh_token', (string)$tokens['refresh_token']);
        }
    }

    public static function isConnected(): bool
    {
        return Mailer::getConfig('mail_oauth_refresh_token') !== '';
    }

    /**
     * OAuth2 is active when explicitly selected, or when credentials were
     * supplied via .env / add-on options without setting the mode explicitly.
     */
    public static function isEnabled(): bool
    {
        $mode = Mailer::getConfig('mail_auth_mode', '');
        if ($mode === 'oauth2') {
            return true;
        }
        if ($mode !== '') {
            return false;
        }
        return self::isConnected();
    }

    /**
     * Return a currently usable access token, refreshing it when needed.
     */
    public static function getAccessToken(): ?string
    {
        if (!self::isEnabled()) {
            return null;
        }

        $access = Mailer::getConfig('mail_oauth_access_token');
        $expiresAt = (int)Mailer::getConfig('mail_oauth_expires_at', '0');
        if ($access !== '' && $expiresAt > time() + 60) {
            return $access;
        }

        $refresh = Mailer::getConfig('mail_oauth_refresh_token');
        $clientId = Mailer::getConfig('mail_oauth_client_id');
        $clientSecret = Mailer::getConfig('mail_oauth_client_secret');
        if ($refresh === '' || $clientId === '' || $clientSecret === '') {
            Logger::warning('Mailer: OAuth2 refresh needs a refresh token, Client ID and Client Secret.');
            return null;
        }

        $tokens = self::refreshAccessToken($refresh, $clientId, $clientSecret);
        if (empty($tokens['access_token'])) {
            return null;
        }
        self::saveTokens($tokens);
        Logger::info('Mailer: Gmail OAuth2 access token refreshed.');
        return (string)$tokens['access_token'];
    }

    /**
     * @param array<string, string> $params
     * @return array<string, string>
     */
    private static function post(array $params): array
    {
        $body = http_build_query($params);

        if (function_exists('curl_init')) {
            $ch = curl_init(self::TOKEN_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                ],
            ]);
            $response = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                Logger::error('Google OAuth token request failed: ' . $error);
                return [];
            }
            $data = json_decode((string)$response, true);
            if (!is_array($data)) {
                Logger::error('Google OAuth token request returned an invalid response.');
                return [];
            }
            if ($status < 200 || $status >= 300) {
                // Never log the raw response — it can contain tokens.
                Logger::error("Google OAuth token request failed (HTTP {$status}): " . (string)($data['error'] ?? 'unknown error'));
                return [];
            }
            return $data;
        }

        // Fallback when the curl extension is unavailable.
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                'content' => $body,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents(self::TOKEN_URL, false, $context);
        if ($response === false) {
            Logger::error('Google OAuth token request failed (no curl or stream transport).');
            return [];
        }
        $data = json_decode($response, true);
        if (!is_array($data) || isset($data['error'])) {
            Logger::error('Google OAuth token request failed: ' . (string)($data['error'] ?? 'invalid response'));
            return [];
        }
        return $data;
    }

    private static function setSetting(string $key, string $value): void
    {
        try {
            Database::execute(
                "INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?",
                [$key, $value, $value]
            );
        } catch (\Throwable $e) {
            Logger::error('Could not save OAuth setting ' . $key . ': ' . $e->getMessage());
        }
    }
}
