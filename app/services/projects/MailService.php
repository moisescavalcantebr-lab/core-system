<?php

class MailService
{
    public static function send(string $to, string $subject, string $html): ?string
    {
        $config = require ROOT_PATH . '/env/env.production.php';
        $mailConfig = $config['mail'] ?? [];
        $provider = strtolower((string)($mailConfig['provider'] ?? 'smtp'));

        if ($provider === 'resend') {
            return self::sendResend($mailConfig, $to, $subject, $html);
        }

        if ($provider === 'smtp') {
            return self::sendSmtp($mailConfig, $to, $subject, $html);
        }

        self::logError("MAIL CONFIG ERROR: provider nao suportado: {$provider}");
        return null;
    }

    private static function sendResend(array $mailConfig, string $to, string $subject, string $html): ?string
    {
        $apiKey = (string)($mailConfig['api_key'] ?? '');
        $from = (string)($mailConfig['from'] ?? '');

        if ($apiKey === '' || $from === '') {
            self::logError('RESEND CONFIG ERROR: api_key/from ausente.');
            return null;
        }

        $payload = [
            "from" => $from,
            "to" => [$to],
            "subject" => $subject,
            "html" => $html
        ];

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.resend.com/emails",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $apiKey,
                "Content-Type: application/json"
            ]
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        /* =========================
           ERRO CURL
        ========================= */

        if ($error) {
            self::logError("CURL ERROR: " . $error);
            return null;
        }

        $data = json_decode($response, true);

        /* =========================
           ERRO API
        ========================= */

        if ($httpCode !== 200 && $httpCode !== 202) {
            self::logError("API ERROR: " . $response);
            return null;
        }

        /* =========================
           SUCESSO
        ========================= */

        return $data['id'] ?? null;
    }

    private static function sendSmtp(array $mailConfig, string $to, string $subject, string $html): ?string
    {
        $host = (string)($mailConfig['host'] ?? '');
        $port = (int)($mailConfig['port'] ?? 587);
        $encryption = strtolower((string)($mailConfig['encryption'] ?? 'tls'));
        $username = (string)($mailConfig['username'] ?? '');
        $password = (string)($mailConfig['password'] ?? '');
        $from = (string)($mailConfig['from'] ?? $username);
        $replyTo = (string)($mailConfig['reply_to'] ?? '');
        $timeout = (int)($mailConfig['timeout'] ?? 15);

        [$fromEmail, $fromName] = self::parseAddress($from);

        if ($host === '' || $username === '' || $password === '' || $fromEmail === '') {
            self::logError('SMTP CONFIG ERROR: host/username/password/from ausente.');
            return null;
        }

        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);

        if (!$socket) {
            self::logError("SMTP CONNECT ERROR: {$errno} {$errstr}");
            return null;
        }

        stream_set_timeout($socket, $timeout);

        try {
            self::smtpExpect($socket, [220]);
            self::smtpCommand($socket, 'EHLO ' . self::smtpLocalName(), [250]);

            if ($encryption === 'tls') {
                self::smtpCommand($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP TLS ERROR: nao foi possivel iniciar criptografia.');
                }
                self::smtpCommand($socket, 'EHLO ' . self::smtpLocalName(), [250]);
            }

            self::smtpCommand($socket, 'AUTH LOGIN', [334]);
            self::smtpCommand($socket, base64_encode($username), [334]);
            self::smtpCommand($socket, base64_encode($password), [235]);
            self::smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            self::smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::smtpCommand($socket, 'DATA', [354]);

            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'From: ' . self::formatAddress($fromEmail, $fromName),
                'To: <' . $to . '>',
                'Subject: ' . self::encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ];

            if ($replyTo !== '') {
                [$replyEmail, $replyName] = self::parseAddress($replyTo);
                if ($replyEmail !== '') {
                    $headers[] = 'Reply-To: ' . self::formatAddress($replyEmail, $replyName);
                }
            }

            $message = implode("\r\n", $headers) . "\r\n\r\n" . self::escapeSmtpBody($html) . "\r\n.";
            self::smtpCommand($socket, $message, [250]);
            self::smtpCommand($socket, 'QUIT', [221]);
            fclose($socket);

            return 'smtp-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        } catch (Throwable $exception) {
            fclose($socket);
            self::logError($exception->getMessage());
            return null;
        }
    }

    private static function smtpCommand($socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");
        return self::smtpExpect($socket, $expectedCodes);
    }

    private static function smtpExpect($socket, array $expectedCodes): string
    {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('SMTP ERROR: ' . trim($response));
        }

        return $response;
    }

    private static function parseAddress(string $address): array
    {
        $address = trim($address);
        if (preg_match('/^(.*?)<([^>]+)>$/', $address, $matches)) {
            return [trim($matches[2]), trim(trim($matches[1]), "\"' ")];
        }

        return [$address, ''];
    }

    private static function formatAddress(string $email, string $name = ''): string
    {
        if ($name === '') {
            return '<' . $email . '>';
        }

        return self::encodeHeader($name) . ' <' . $email . '>';
    }

    private static function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }

        return str_replace(["\r", "\n"], '', $value);
    }

    private static function escapeSmtpBody(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = str_replace("\n.", "\n..", $body);
        return str_replace("\n", "\r\n", $body);
    }

    private static function smtpLocalName(): string
    {
        return $_SERVER['SERVER_NAME'] ?? 'localhost';
    }

    private static function logError(string $message): void
    {
        file_put_contents(
            ROOT_PATH . '/storage/mail_error.log',
            date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL,
            FILE_APPEND
        );
    }
}
