<?php

namespace App\Core;

class Mailer
{
    private static string $lastResponse = '';

    private static function readRawResponse($socket): string
    {
        $response = '';
        $maxLines = 100;
        $lineCount = 0;
        while (true) {
            if (++$lineCount > $maxLines || feof($socket)) break;
            $line = fgets($socket, 512);
            if ($line === false) break;
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        self::$lastResponse = $response;
        return $response;
    }

    private static function readResponse($socket, string $expectedCode, string $errorMsg): bool
    {
        $response = self::readRawResponse($socket);
        $code = substr($response, 0, 3);
        if ($response === '') {
            \App\Core\Logger::error("Mailer: {$errorMsg} — no response from server.");
            return false;
        }
        if ($code !== $expectedCode) {
            \App\Core\Logger::error("Mailer: {$errorMsg} — got {$code}, expected {$expectedCode}. Response: " . trim($response));
            return false;
        }
        return true;
    }

    private static function smtpCommand($socket, string $command, string $expectedCode, string $errorMsg): bool
    {
        fwrite($socket, $command . "\r\n");
        return self::readResponse($socket, $expectedCode, $errorMsg);
    }

    /**
     * STARTTLS (except on port 465, which is implicit TLS) and SMTP AUTH.
     */
    private static function authenticate($socket, int $port, ?string $username, ?string $password): bool
    {
        $oauthEnabled = GoogleOAuth::isEnabled();
        $oauthUser = $oauthEnabled ? self::getConfig('mail_oauth_email') : '';
        $authUser = $oauthUser !== '' ? $oauthUser : (string)$username;

        if ($authUser === '') {
            return true;
        }

        if ($port !== 465) {
            if (!self::smtpCommand($socket, 'STARTTLS', '220', 'STARTTLS')) {
                return false;
            }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                \App\Core\Logger::error('Mailer: STARTTLS negotiation failed.');
                return false;
            }
            if (!self::smtpCommand($socket, 'EHLO turtle', '250', 'EHLO after STARTTLS')) {
                return false;
            }
        }

        if ($oauthEnabled) {
            $token = GoogleOAuth::getAccessToken();
            if ($token === null || $authUser === '') {
                \App\Core\Logger::error('Mailer: OAuth2 token or Gmail address unavailable. Connect Gmail OAuth2 in Settings first.');
                return false;
            }

            // RFC 7622 / XOAUTH2: user=<email>\x01auth=Bearer <token>\x01\x01
            $blob = GoogleOAuth::xoauth2Blob($authUser, $token);
            fwrite($socket, 'AUTH XOAUTH2 ' . base64_encode($blob) . "\r\n");
            $response = self::readRawResponse($socket);
            $code = substr($response, 0, 3);
            if ($code === '235') {
                return true;
            }
            if ($code === '334') {
                // Gmail returns a base64-encoded JSON error as the challenge.
                // Send an empty line to cancel the SASL exchange cleanly.
                fwrite($socket, "\r\n");
                self::readRawResponse($socket);
            }
            \App\Core\Logger::error("Mailer: OAuth2 authentication failed — got {$code}. Response: " . trim($response));
            return false;
        }

        if (!self::smtpCommand($socket, 'AUTH LOGIN', '334', 'AUTH LOGIN')) {
            return false;
        }
        if (!self::smtpCommand($socket, base64_encode($authUser), '334', 'AUTH username')) {
            return false;
        }
        if (!self::smtpCommand($socket, base64_encode((string)$password), '235', 'AUTH password')) {
            return false;
        }
        return true;
    }

    public static function getConfig(string $key, string $default = ''): string
    {
        $envMap = [
            'mail_host' => 'MAIL_HOST',
            'mail_port' => 'MAIL_PORT',
            'mail_username' => 'MAIL_USERNAME',
            'mail_password' => 'MAIL_PASSWORD',
            'mail_from_address' => 'MAIL_FROM_ADDRESS',
            'mail_from_name' => 'MAIL_FROM_NAME',
            'mail_auth_mode' => 'MAIL_AUTH_MODE',
            'mail_oauth_client_id' => 'MAIL_OAUTH_CLIENT_ID',
            'mail_oauth_client_secret' => 'MAIL_OAUTH_CLIENT_SECRET',
            'mail_oauth_refresh_token' => 'MAIL_OAUTH_REFRESH_TOKEN',
            'mail_oauth_access_token' => 'MAIL_OAUTH_ACCESS_TOKEN',
            'mail_oauth_expires_at' => 'MAIL_OAUTH_EXPIRES_AT',
            'mail_oauth_email' => 'MAIL_OAUTH_EMAIL',
        ];
        $envKey = $envMap[$key] ?? strtoupper($key);
        try {
            $row = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = ?", [$key]);
            if ($row && $row['value'] !== '') {
                return $row['value'];
            }
        } catch (\Throwable $e) {
        }
        return $_ENV[$envKey] ?? $default;
    }

    public static function send(string $to, string $subject, string $body): bool
    {
        $host = self::getConfig('mail_host', 'mailpit');
        $port = (int)(self::getConfig('mail_port', '1025'));
        $username = self::getConfig('mail_username', '') ?: null;
        $password = self::getConfig('mail_password', '') ?: null;
        $fromAddress = self::getConfig('mail_from_address', 'noreply@turtleapp.com');
        $fromName = self::getConfig('mail_from_name', 'Turtle');

        $html = "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><title>{$subject}</title></head><body style=\"font-family: Arial, sans-serif; padding: 20px;\">{$body}</body></html>";

        $mailData = [
            'From' => "{$fromName} <{$fromAddress}>",
            'To' => $to,
            'Subject' => $subject,
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/html; charset=UTF-8',
            'Message-ID' => '<' . time() . '.' . uniqid() . '@turtleapp.com>',
        ];

        $mailStr = '';
        foreach ($mailData as $key => $value) {
            $mailStr .= "{$key}: {$value}\r\n";
        }
        $mailStr .= "\r\n{$html}";

        $prefix = ($port === 465) ? 'ssl://' : '';
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
        if (!$socket) {
            \App\Core\Logger::error("Mailer: Failed to connect to {$prefix}{$host}:{$port} — {$errstr}");
            return false;
        }

        stream_set_timeout($socket, 10);

        if (!self::readResponse($socket, '220', 'Connection greeting')) {
            fclose($socket);
            return false;
        }

        if (!self::smtpCommand($socket, 'EHLO turtle', '250', 'EHLO')) {
            fclose($socket);
            return false;
        }

        if (!self::authenticate($socket, $port, $username, $password)) {
            fclose($socket);
            return false;
        }

        if (!self::smtpCommand($socket, "MAIL FROM:<{$fromAddress}>", '250', 'MAIL FROM')) {
            fclose($socket);
            return false;
        }
        if (!self::smtpCommand($socket, "RCPT TO:<{$to}>", '250', 'RCPT TO')) {
            fclose($socket);
            return false;
        }
        if (!self::smtpCommand($socket, "DATA", '354', 'DATA')) {
            fclose($socket);
            return false;
        }

        fwrite($socket, $mailStr . "\r\n.\r\n");
        $response = '';
        while (true) {
            $line = fgets($socket, 512);
            if ($line === false) break;
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $code = substr($response, 0, 3);
        if ($code !== '250') {
            \App\Core\Logger::error("Mailer: Message delivery failed — got {$code}. Response: " . trim($response));
            fclose($socket);
            return false;
        }

        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        \log_activity('notifications.email_sent', "Email sent to {$to} — {$subject}");
        return true;
    }

    public static function sendTemplate(string $to, string $subject, string $greeting, string $body, string $actionUrl = '', string $actionText = '', array $attachments = []): bool
    {
        $actionHtml = '';
        if ($actionUrl && $actionText) {
            $actionHtml = "<p style=\"text-align: center; margin: 30px 0;\"><a href=\"{$actionUrl}\" style=\"display: inline-block; padding: 12px 24px; background-color: #0d9488; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold;\">{$actionText}</a></p>";
        }

        $logoHtml = '<h1 style="color: white; margin: 0; font-size: 24px;">Turtle</h1>';
        try {
            $logo = \site_logo();
            if ($logo) {
                $logoUrl = \site_url() . $logo;
                $logoHtml = '<img src="' . $logoUrl . '" alt="Logo" style="max-height: 50px; width: auto;">';
            }
        } catch (\Throwable $e) {}

        $html = "
        <div style=\"max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden;\">
            <div style=\"background: #0d9488; padding: 20px; text-align: center;\">
                {$logoHtml}
            </div>
            <div style=\"padding: 30px;\">
                <h2 style=\"color: #1f2937; margin-top: 0;\">{$greeting}</h2>
                <p style=\"color: #4b5563; line-height: 1.6;\">{$body}</p>
                {$actionHtml}
                <hr style=\"border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;\">
                <p style=\"color: #9ca3af; font-size: 12px;\">If you did not expect this email, please ignore it.</p>
            </div>
        </div>";

        if (!empty($attachments)) {
            return self::sendWithAttachments($to, $subject, $html, $attachments);
        }

        return self::send($to, $subject, $html);
    }

    public static function sendWithAttachments(string $to, string $subject, string $htmlBody, array $attachments): bool
    {
        $boundary = '----=' . md5(uniqid());

        $body = "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 7bit\r\n\r\n"
            . $htmlBody . "\r\n";

        foreach ($attachments as $att) {
            $filePath = $att['path'] ?? '';
            $fileName = $att['name'] ?? basename($filePath);
            if (!file_exists($filePath) || !is_readable($filePath)) {
                continue;
            }
            $fileContent = file_get_contents($filePath);
            if ($fileContent === false) continue;
            $encoded = chunk_split(base64_encode($fileContent));
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $mimeType = match ($ext) {
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'txt' => 'text/plain',
                default => 'application/octet-stream',
            };

            $safeName = sanitize_filename($fileName);
            $body .= "--{$boundary}\r\n"
                . "Content-Type: {$mimeType}; name=\"{$safeName}\"\r\n"
                . "Content-Disposition: attachment; filename=\"{$safeName}\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . $encoded . "\r\n";
        }

        $body .= "--{$boundary}--\r\n";

        $host = self::getConfig('mail_host', 'mailpit');
        $port = (int)(self::getConfig('mail_port', '1025'));
        $username = self::getConfig('mail_username', '') ?: null;
        $password = self::getConfig('mail_password', '') ?: null;
        $fromAddress = self::getConfig('mail_from_address', 'noreply@turtleapp.com');
        $fromName = self::getConfig('mail_from_name', 'Turtle');

        $headers = "From: {$fromName} <{$fromAddress}>\r\n"
            . "To: {$to}\r\n"
            . "Subject: {$subject}\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n"
            . "Message-ID: <" . time() . '.' . uniqid() . "@turtleapp.com>\r\n";

        $mailStr = $headers . "\r\n" . $body;

        $prefix = ($port === 465) ? 'ssl://' : '';
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
        if (!$socket) {
            \App\Core\Logger::error("Mailer: Failed to connect to {$prefix}{$host}:{$port} — {$errstr}");
            return false;
        }

        stream_set_timeout($socket, 10);

        if (!self::readResponse($socket, '220', 'Connection greeting')) {
            fclose($socket);
            return false;
        }

        if (!self::smtpCommand($socket, 'EHLO turtle', '250', 'EHLO')) {
            fclose($socket);
            return false;
        }

        if (!self::authenticate($socket, $port, $username, $password)) {
            fclose($socket);
            return false;
        }

        if (!self::smtpCommand($socket, "MAIL FROM:<{$fromAddress}>", '250', 'MAIL FROM')) {
            fclose($socket);
            return false;
        }
        if (!self::smtpCommand($socket, "RCPT TO:<{$to}>", '250', 'RCPT TO')) {
            fclose($socket);
            return false;
        }
        if (!self::smtpCommand($socket, "DATA", '354', 'DATA')) {
            fclose($socket);
            return false;
        }

        fwrite($socket, $mailStr . "\r\n.\r\n");
        $response = '';
        while (true) {
            $line = fgets($socket, 512);
            if ($line === false) break;
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $code = substr($response, 0, 3);
        if ($code !== '250') {
            \App\Core\Logger::error("Mailer: Message delivery failed — got {$code}. Response: " . trim($response));
            fclose($socket);
            return false;
        }

        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        \log_activity('notifications.email_sent', "Email sent to {$to} — {$subject}");
        return true;
    }
}
