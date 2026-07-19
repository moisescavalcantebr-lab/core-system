<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

$settingsService = new SettingsService($pdo);
$links = [];
$labels = $_POST['label'] ?? [];
$urls = $_POST['url'] ?? [];
$descriptions = $_POST['description'] ?? [];
$categories = $_POST['category'] ?? [];
$enabled = $_POST['enabled'] ?? [];

foreach ($labels as $index => $label) {
    $label = trim((string)$label);
    $url = trim((string)($urls[$index] ?? ''));

    if ($label === '' && $url === '') {
        continue;
    }

    if ($label === '' || $url === '') {
        flash('error', 'Informe nome e URL em todos os acessos preenchidos.');
        redirect('/web/admin/quick_links/index.php');
    }

    $links[] = [
        'label' => $label,
        'url' => $url,
        'description' => trim((string)($descriptions[$index] ?? '')),
        'category' => trim((string)($categories[$index] ?? '')),
        'enabled' => isset($enabled[$index]),
    ];
}

$settingsService->set('quick_links', json_encode($links, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

flash('success', 'Acessos rápidos atualizados.');
redirect('/web/admin/quick_links/index.php');
