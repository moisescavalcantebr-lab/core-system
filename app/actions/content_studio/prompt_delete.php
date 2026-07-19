<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

ContentStudioService::ensureSchema($pdo);

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Prompt invalido.');
    redirect('/web/admin/content_studio/production.php');
}

$stmt = $pdo->prepare('DELETE FROM content_studio_prompts WHERE id = :id');
$stmt->execute([':id' => $id]);

flash('success', 'Prompt removido.');
redirect('/web/admin/content_studio/production.php');
