<?php

namespace App\Core;

class Mailer
{
    private static function readResponse($socket, string $expectedCode, string $errorMsg): bool
    {
        $response = '';
        while (true) {
            $line = fgets($socket, 512);
            if ($line === false) break;
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $code = substr($response, 0, 3);
        if ($code !== $expectedCode) {
            error_log("Mailer: {$errorMsg} — got {$code}, expected {$expectedCode}. Response: " . trim($response));
            return false;
        }
        return true;
    }

    private static function smtpCommand($socket, string $command, string $expectedCode, string $errorMsg): bool
    {
        fwrite($socket, $command . "\r\n");
        return self::readResponse($socket, $expectedCode, $errorMsg);
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

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, 10);
        if (!$socket) {
            error_log("Mailer: Failed to connect to {$host}:{$port} — {$errstr}");
            return false;
        }

        stream_set_timeout($socket, 10);

        if (!self::readResponse($socket, '220', 'Connection greeting')) {
            fclose($socket);
            return false;
        }

        if (!self::smtpCommand($socket, "EHLO turtle", '250', 'EHLO')) {
            fclose($socket);
            return false;
        }

        if ($username !== null) {
            if ($port === 587) {
                if (!self::smtpCommand($socket, "STARTTLS", '220', 'STARTTLS')) {
                    fclose($socket);
                    return false;
                }
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if (!self::smtpCommand($socket, "EHLO turtle", '250', 'EHLO after STARTTLS')) {
                    fclose($socket);
                    return false;
                }
            }

            if (!self::smtpCommand($socket, "AUTH LOGIN", '334', 'AUTH LOGIN')) {
                fclose($socket);
                return false;
            }
            if (!self::smtpCommand($socket, base64_encode($username), '334', 'AUTH username')) {
                fclose($socket);
                return false;
            }
            if (!self::smtpCommand($socket, base64_encode($password), '235', 'AUTH password')) {
                fclose($socket);
                return false;
            }
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
            error_log("Mailer: Message delivery failed — got {$code}. Response: " . trim($response));
            fclose($socket);
            return false;
        }

        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        return true;
    }

    public static function sendTemplate(string $to, string $subject, string $greeting, string $body, string $actionUrl = '', string $actionText = ''): bool
    {
        $actionHtml = '';
        if ($actionUrl && $actionText) {
            $actionHtml = "<p style=\"text-align: center; margin: 30px 0;\"><a href=\"{$actionUrl}\" style=\"display: inline-block; padding: 12px 24px; background-color: #0d9488; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold;\">{$actionText}</a></p>";
        }

        $logoHtml = '<h1 style="color: white; margin: 0; font-size: 24px;">Turtle</h1>';
        try {
            $logo = \site_logo();
            if ($logo && isset($_SERVER['HTTP_HOST'])) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $logoUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $logo;
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

        return self::send($to, $subject, $html);
    }
}
