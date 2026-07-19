<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

ContentStudioService::ensureSchema($pdo);

$name = trim((string)($_POST['name'] ?? ''));

if ($name === '') {
    flash('error', 'Nome da campanha é obrigatório.');
    redirect('/web/admin/content_studio/campaigns.php');
}

$slug = ContentStudioService::slug((string)($_POST['slug'] ?? $name));
$campaignKey = ContentStudioService::slug((string)($_POST['campaign_key'] ?? $slug));
$status = in_array($_POST['status'] ?? '', ['draft', 'active', 'paused', 'finished'], true)
    ? (string)$_POST['status']
    : 'draft';

$projectId = (int)($_POST['project_id'] ?? 0);
$pageId = (int)($_POST['page_id'] ?? 0);

$stmt = $pdo->prepare("SELECT id FROM content_studio_campaigns WHERE slug = :slug OR campaign_key = :campaign_key LIMIT 1");
$stmt->execute([
    ':slug' => $slug,
    ':campaign_key' => $campaignKey,
]);

if ($stmt->fetchColumn()) {
    flash('error', 'Já existe campanha com este slug ou chave.');
    redirect('/web/admin/content_studio/campaigns.php');
}

$startsAt = trim((string)($_POST['starts_at'] ?? ''));
$endsAt = trim((string)($_POST['ends_at'] ?? ''));

if ($startsAt !== '' && $endsAt !== '' && $startsAt > $endsAt) {
    flash('error', 'A data final não pode ser anterior ao início.');
    redirect('/web/admin/content_studio/campaigns.php');
}

$stmt = $pdo->prepare("
    INSERT INTO content_studio_campaigns
    (name, slug, campaign_key, objective, project_id, page_id, landing_url, status, starts_at, ends_at, notes)
    VALUES
    (:name, :slug, :campaign_key, :objective, :project_id, :page_id, :landing_url, :status, :starts_at, :ends_at, :notes)
");

$stmt->execute([
    ':name' => $name,
    ':slug' => $slug,
    ':campaign_key' => $campaignKey,
    ':objective' => trim((string)($_POST['objective'] ?? '')) ?: null,
    ':project_id' => $projectId > 0 ? $projectId : null,
    ':page_id' => $pageId > 0 ? $pageId : null,
    ':landing_url' => trim((string)($_POST['landing_url'] ?? '')) ?: null,
    ':status' => $status,
    ':starts_at' => $startsAt ?: null,
    ':ends_at' => $endsAt ?: null,
    ':notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
]);

redirect('/web/admin/content_studio/campaigns.php');
