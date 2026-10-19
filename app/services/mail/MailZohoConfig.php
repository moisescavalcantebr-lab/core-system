<?php
declare(strict_types=1);

final class MailZohoConfig
{
    public const SCOPES = ['ZohoMail.accounts.READ', 'ZohoMail.folders.READ', 'ZohoMail.messages.READ', 'ZohoMail.messages.CREATE'];

    public static function endpoints(string $region): array
    {
        return match ($region) {
            'us' => ['accounts'=>'https://accounts.zoho.com', 'mail'=>'https://mail.zoho.com'],
            'eu' => ['accounts'=>'https://accounts.zoho.eu', 'mail'=>'https://mail.zoho.eu'],
            default => throw new DomainException('Região Zoho não disponível nesta integração.'),
        };
    }

    public static function validate(array $data, string $canonical): array
    {
        $endpoints = self::endpoints((string)($data['region'] ?? ''));
        if (!is_string($data['client_id'] ?? null) || !preg_match('/^1000\.[a-zA-Z0-9._-]{8,190}$/D', $data['client_id'])
            || !is_string($data['client_secret'] ?? null) || strlen($data['client_secret']) < 8 || strlen($data['client_secret']) > 512
            || !filter_var($data['pilot_email'] ?? '', FILTER_VALIDATE_EMAIL)
            || ($data['redirect_uri'] ?? '') !== $canonical.'/web/admin/central_contas/oauth_zoho_callback.php') {
            throw new DomainException('Confira o cliente Zoho privado, a caixa autorizada e o callback HTTPS.');
        }
        return array_replace($data,['provider'=>'zoho', 'endpoints'=>$endpoints]);
    }
}
