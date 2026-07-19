<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap/bootstrap.php';

function corePwaShortName(string $name): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($name, 0, 18);
    }

    return substr($name, 0, 18);
}

function corePwaAsset(?string $file): ?string
{
    $file = trim((string)$file);
    if ($file === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $file)) {
        return $file;
    }

    return '/web/assets/uploads/' . ltrim($file, '/');
}

$name = trim((string)($coreSettings['app_name'] ?? 'Meu Projeto Web'));
$icon = corePwaAsset((string)($coreSettings['app_favicon'] ?? '')) ?: corePwaAsset((string)($coreSettings['app_logo'] ?? ''));
$fallbackIcon = '/web/pwa-icon.php';

header('Content-Type: application/manifest+json; charset=utf-8');

echo json_encode([
    'name' => $name,
    'short_name' => corePwaShortName($name),
    'description' => 'Acesso rapido ao painel do core.',
    'start_url' => '/web/admin/login.php',
    'scope' => '/web/',
    'display' => 'standalone',
    'background_color' => '#050817',
    'theme_color' => '#3b82f6',
    'icons' => [
        [
            'src' => $icon ?: $fallbackIcon . '?size=192',
            'sizes' => '192x192',
            'type' => $icon ? 'image/png' : 'image/svg+xml',
            'purpose' => 'any',
        ],
        [
            'src' => $icon ?: $fallbackIcon . '?size=512',
            'sizes' => '512x512',
            'type' => $icon ? 'image/png' : 'image/svg+xml',
            'purpose' => 'any maskable',
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
