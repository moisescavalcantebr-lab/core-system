<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

ContentStudioService::ensureSchema($pdo);

$title = trim((string)($_POST['title'] ?? ''));

if ($title === '') {
    flash('error', 'Título da publicação é obrigatório.');
    redirect('/web/admin/content_studio/calendar.php');
}

$campaignId = (int)($_POST['campaign_id'] ?? 0);
$destinationUrl = trim((string)($_POST['destination_url'] ?? ''));

if ($destinationUrl === '' && $campaignId > 0) {
    $stmt = $pdo->prepare("
        SELECT c.*, p.slug AS page_slug
        FROM content_studio_campaigns c
        LEFT JOIN core_page_contents p ON p.id = c.page_id
        WHERE c.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $campaignId]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($campaign) {
        $destinationUrl = ContentStudioService::campaignTrackingUrl($campaign, 'publicacao');
    }
}

$status = in_array($_POST['status'] ?? '', ['planned', 'published', 'canceled'], true) ? (string)$_POST['status'] : 'planned';

$stmt = $pdo->prepare("
    INSERT INTO content_studio_publications
    (campaign_id, idea_id, channel_id, title, destination_url, scheduled_at, published_at, status, notes)
    VALUES
    (:campaign_id, :idea_id, :channel_id, :title, :destination_url, :scheduled_at, :published_at, :status, :notes)
");

$stmt->execute([
    ':campaign_id' => $campaignId ?: null,
    ':idea_id' => (int)($_POST['idea_id'] ?? 0) ?: null,
    ':channel_id' => (int)($_POST['channel_id'] ?? 0) ?: null,
    ':title' => $title,
    ':destination_url' => $destinationUrl ?: null,
    ':scheduled_at' => $_POST['scheduled_at'] ?: null,
    ':published_at' => $status === 'published' ? date('Y-m-d H:i:s') : null,
    ':status' => $status,
    ':notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
]);

redirect('/web/admin/content_studio/calendar.php');
