<?php
declare(strict_types=1);

function coreQuickLinksDefaults(array $config = []): array
{
    $appUrl = rtrim((string)($config['app_url'] ?? ''), '/');

    return [
        [
            'label' => 'Admin do Core',
            'url' => $appUrl !== '' ? $appUrl . '/web/admin/dashboard.php' : '/web/admin/dashboard.php',
            'description' => 'Painel principal do sistema.',
            'category' => 'Sistema',
            'enabled' => true,
        ],
        [
            'label' => 'phpMyAdmin',
            'url' => $appUrl !== '' ? preg_replace('/^https?:\/\//', 'http://', $appUrl) . ':8080' : 'http://localhost:8080',
            'description' => 'Acesso ao banco de dados. Mantenha protegido.',
            'category' => 'Servidor',
            'enabled' => true,
        ],
        [
            'label' => 'DigitalOcean',
            'url' => 'https://cloud.digitalocean.com/',
            'description' => 'Servidor, Droplets e recursos da hospedagem.',
            'category' => 'Infra',
            'enabled' => true,
        ],
        [
            'label' => 'Cloudflare',
            'url' => 'https://dash.cloudflare.com/',
            'description' => 'DNS, proxy e SSL do domínio.',
            'category' => 'Infra',
            'enabled' => true,
        ],
        [
            'label' => 'Zoho Mail',
            'url' => 'https://mail.zoho.com/',
            'description' => 'E-mail corporativo e caixas do domínio.',
            'category' => 'E-mail',
            'enabled' => true,
        ],
    ];
}

function coreQuickLinksDecode(?string $json, array $config = []): array
{
    $decoded = json_decode((string)$json, true);

    if (!is_array($decoded)) {
        return coreQuickLinksDefaults($config);
    }

    $links = [];

    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }

        $links[] = [
            'label' => trim((string)($item['label'] ?? '')),
            'url' => trim((string)($item['url'] ?? '')),
            'description' => trim((string)($item['description'] ?? '')),
            'category' => trim((string)($item['category'] ?? '')),
            'enabled' => !empty($item['enabled']),
        ];
    }

    return $links;
}

function coreQuickLinksEnabled(array $links): array
{
    return array_values(array_filter($links, static function (array $link): bool {
        return !empty($link['enabled']) && ($link['label'] ?? '') !== '' && ($link['url'] ?? '') !== '';
    }));
}
