<?php

class MailService
{
    public static function send(string $to, string $subject, string $html): ?string
    {
        $config = require ROOT_PATH . '/env/env.production.php';

        $apiKey = $config['mail']['api_key'];
        $from   = $config['mail']['from'];

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

            file_put_contents(
                ROOT_PATH . '/storage/mail_error.log',
                date('Y-m-d H:i:s') . " CURL ERROR: " . $error . PHP_EOL,
                FILE_APPEND
            );

            return null;
        }

        $data = json_decode($response, true);

        /* =========================
           ERRO API
        ========================= */

        if ($httpCode !== 200 && $httpCode !== 202) {

            file_put_contents(
                ROOT_PATH . '/storage/mail_error.log',
                date('Y-m-d H:i:s') . " API ERROR: " . $response . PHP_EOL,
                FILE_APPEND
            );

            return null;
        }

        /* =========================
           SUCESSO
        ========================= */

        return $data['id'] ?? null;
    }
}