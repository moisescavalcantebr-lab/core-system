<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

ContentStudioService::ensureSchema($pdo);

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Roteiro invalido.');
    redirect('/web/admin/content_studio/production.php');
}

$stmt = $pdo->prepare('UPDATE content_studio_prompts SET script_id = NULL WHERE script_id = :id');
$stmt->execute([':id' => $id]);

$stmt = $pdo->prepare('DELETE FROM content_studio_scripts WHERE id = :id');
$stmt->execute([':id' => $id]);

flash('success', 'Roteiro removido.');
redirect('/web/admin/content_studio/production.php');
