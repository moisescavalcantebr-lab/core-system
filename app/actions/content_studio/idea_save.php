<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

ContentStudioService::ensureSchema($pdo);

$title = trim((string)($_POST['title'] ?? ''));

if ($title === '') {
    flash('error', 'Título da ideia é obrigatório.');
    redirect('/web/admin/content_studio/ideas.php');
}

$status = in_array($_POST['status'] ?? '', ['idea', 'draft', 'review', 'approved', 'published', 'archived'], true)
    ? (string)$_POST['status']
    : 'idea';
$priority = in_array($_POST['priority'] ?? '', ['low', 'normal', 'high'], true)
    ? (string)$_POST['priority']
    : 'normal';

$stmt = $pdo->prepare("
    INSERT INTO content_studio_ideas
    (campaign_id, niche_id, persona_id, title, hook, format, priority, status, notes)
    VALUES
    (:campaign_id, :niche_id, :persona_id, :title, :hook, :format, :priority, :status, :notes)
");

$stmt->execute([
    ':campaign_id' => (int)($_POST['campaign_id'] ?? 0) ?: null,
    ':niche_id' => (int)($_POST['niche_id'] ?? 0) ?: null,
    ':persona_id' => (int)($_POST['persona_id'] ?? 0) ?: null,
    ':title' => $title,
    ':hook' => trim((string)($_POST['hook'] ?? '')) ?: null,
    ':format' => trim((string)($_POST['format'] ?? '')) ?: null,
    ':priority' => $priority,
    ':status' => $status,
    ':notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
]);

redirect('/web/admin/content_studio/ideas.php');
