<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

ContentStudioService::ensureSchema($pdo);

$mediaId = (int)($_POST['media_id'] ?? 0);

if ($mediaId <= 0) {
    flash('error', 'Selecione uma imagem da biblioteca.');
    redirect('/web/admin/content_studio/media.php');
}

$stmt = $pdo->prepare('SELECT id FROM core_media WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $mediaId]);

if (!$stmt->fetchColumn()) {
    flash('error', 'Imagem nao encontrada na biblioteca.');
    redirect('/web/admin/content_studio/media.php');
}

$usageType = trim((string)($_POST['usage_type'] ?? 'reference'));
if (!in_array($usageType, ['reference', 'creative', 'cover', 'publication', 'proof'], true)) {
    $usageType = 'reference';
}

$stmt = $pdo->prepare("
    INSERT INTO content_studio_assets
    (media_id, campaign_id, idea_id, publication_id, usage_type, title, notes, status)
    VALUES
    (:media_id, :campaign_id, :idea_id, :publication_id, :usage_type, :title, :notes, :status)
");

$stmt->execute([
    ':media_id' => $mediaId,
    ':campaign_id' => (int)($_POST['campaign_id'] ?? 0) ?: null,
    ':idea_id' => (int)($_POST['idea_id'] ?? 0) ?: null,
    ':publication_id' => (int)($_POST['publication_id'] ?? 0) ?: null,
    ':usage_type' => $usageType,
    ':title' => trim((string)($_POST['title'] ?? '')) ?: null,
    ':notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
    ':status' => 'active',
]);

flash('success', 'Imagem vinculada ao Content Studio.');
redirect('/web/admin/content_studio/media.php');
