<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

ContentStudioService::ensureSchema($pdo);

$id = (int)($_POST['id'] ?? 0);
$title = trim((string)($_POST['title'] ?? ''));

if ($title === '') {
    flash('error', 'Titulo do roteiro e obrigatorio.');
    redirect('/web/admin/content_studio/production.php');
}

$status = in_array($_POST['status'] ?? '', ['draft', 'review', 'approved', 'published', 'archived'], true)
    ? (string)$_POST['status']
    : 'draft';

$payload = [
    ':idea_id' => (int)($_POST['idea_id'] ?? 0) ?: null,
    ':title' => $title,
    ':format' => trim((string)($_POST['format'] ?? '')) ?: null,
    ':script_body' => trim((string)($_POST['script_body'] ?? '')) ?: null,
    ':cta' => trim((string)($_POST['cta'] ?? '')) ?: null,
    ':status' => $status,
];

if ($id > 0) {
    $stmt = $pdo->prepare("
        UPDATE content_studio_scripts
        SET idea_id = :idea_id,
            title = :title,
            format = :format,
            script_body = :script_body,
            cta = :cta,
            status = :status
        WHERE id = :id
    ");
    $payload[':id'] = $id;
    $stmt->execute($payload);
    flash('success', 'Roteiro atualizado.');
} else {
    $stmt = $pdo->prepare("
        INSERT INTO content_studio_scripts
        (idea_id, title, format, script_body, cta, status)
        VALUES
        (:idea_id, :title, :format, :script_body, :cta, :status)
    ");
    $stmt->execute($payload);
    flash('success', 'Roteiro salvo.');
}

redirect('/web/admin/content_studio/production.php');
