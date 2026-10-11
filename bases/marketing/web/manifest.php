<?php
declare(strict_types=1);

define('PROJECT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', PROJECT_PATH . '/web');
define('APP_PATH', PROJECT_PATH . '/app');
define('PROJECT_URL', '/projects/' . basename(PROJECT_PATH) . '/web');

function pwaSetting(string $key, ?string $default = null): ?string
{
    $configPath = APP_PATH . '/config/database.php';

    if (!is_file($configPath)) {
        return $default;
    }

    try {
        $db = require $configPath;
        $pdo = new PDO(
            "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}",
            $db['user'],
            $db['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->prepare("SELECT setting_value FROM project_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value !== false ? (string)$value : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function pwaMedia(?string $file): ?string
{
    if (!$file) {
        return null;
    }

    if (preg_match('#^https?://#i', $file)) {
        return $file;
    }

    return PROJECT_URL . '/' . ltrim($file, '/');
}

$project = [];
$projectJson = PROJECT_PATH . '/project.json';
if (is_file($projectJson)) {
    $project = json_decode((string)file_get_contents($projectJson), true) ?: [];
}

$name = pwaSetting('site_name', $project['name'] ?? 'Meu Projeto');
$icon = pwaMedia(pwaSetting('favicon') ?: pwaSetting('logo'));
$fallbackIcon = PROJECT_URL . '/pwa-icon.php';

header('Content-Type: application/manifest+json; charset=utf-8');

echo json_encode([
    'name' => $name,
    'short_name' => mb_substr((string)$name, 0, 18),
    'description' => 'Acesso rapido ao projeto.',
    'start_url' => PROJECT_URL . '/admin/login.php',
    'scope' => PROJECT_URL . '/',
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
