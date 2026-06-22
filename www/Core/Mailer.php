<?php

namespace App\Core;

class Mailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        $host = $_ENV['MAIL_HOST'] ?? 'mailpit';
        $port = (int)($_ENV['MAIL_PORT'] ?? 1025);
        $username = ($_ENV['MAIL_USERNAME'] ?? '') ?: null;
        $password = ($_ENV['MAIL_PASSWORD'] ?? '') ?: null;
        $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@turtleapp.com';
        $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'Turtle';

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
            error_log("Mailer: Failed to connect to {$host}:{$port} - {$errstr}");
            return false;
        }

        $response = fread($socket, 512);

        fwrite($socket, "EHLO turtle\r\n");
        $response = fread($socket, 512);

        if ($username !== null) {
            if ($port === 587) {
                fwrite($socket, "STARTTLS\r\n");
                $response = fread($socket, 512);
                if (str_starts_with($response, '220')) {
                    stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                    fwrite($socket, "EHLO turtle\r\n");
                    $response = fread($socket, 512);
                }
            }

            fwrite($socket, "AUTH LOGIN\r\n");
            $response = fread($socket, 512);
            fwrite($socket, base64_encode($username) . "\r\n");
            $response = fread($socket, 512);
            fwrite($socket, base64_encode($password) . "\r\n");
            $response = fread($socket, 512);
        }

        fwrite($socket, "MAIL FROM:<{$fromAddress}>\r\n");
        fread($socket, 512);
        fwrite($socket, "RCPT TO:<{$to}>\r\n");
        fread($socket, 512);
        fwrite($socket, "DATA\r\n");
        fread($socket, 512);
        fwrite($socket, $mailStr . "\r\n.\r\n");
        fread($socket, 512);
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

        $html = "
        <div style=\"max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden;\">
            <div style=\"background: #0d9488; padding: 20px; text-align: center;\">
                <h1 style=\"color: white; margin: 0; font-size: 24px;\">Turtle</h1>
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
