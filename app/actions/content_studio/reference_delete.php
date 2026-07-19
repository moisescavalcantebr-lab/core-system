<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

ContentStudioService::ensureSchema($pdo);

$type = (string)($_POST['type'] ?? '');
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Registro invalido.');
    redirect('/web/admin/content_studio/settings.php');
}

$map = [
    'channel' => [
        'table' => 'content_studio_channels',
        'usage_sql' => 'SELECT COUNT(*) FROM content_studio_publications WHERE channel_id = :id',
    ],
    'niche' => [
        'table' => 'content_studio_niches',
        'usage_sql' => 'SELECT COUNT(*) FROM content_studio_ideas WHERE niche_id = :id',
    ],
    'persona' => [
        'table' => 'content_studio_personas',
        'usage_sql' => 'SELECT COUNT(*) FROM content_studio_ideas WHERE persona_id = :id',
    ],
];

if (!isset($map[$type])) {
    flash('error', 'Tipo invalido.');
    redirect('/web/admin/content_studio/settings.php');
}

$stmt = $pdo->prepare($map[$type]['usage_sql']);
$stmt->execute([':id' => $id]);
$inUse = (int)$stmt->fetchColumn() > 0;

if ($inUse) {
    $stmt = $pdo->prepare("UPDATE {$map[$type]['table']} SET status = 'inactive' WHERE id = :id");
    $stmt->execute([':id' => $id]);
    flash('success', 'Registro em uso foi desativado para preservar o historico.');
} else {
    $stmt = $pdo->prepare("DELETE FROM {$map[$type]['table']} WHERE id = :id");
    $stmt->execute([':id' => $id]);
    flash('success', 'Registro removido.');
}

redirect('/web/admin/content_studio/settings.php');
