<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

ContentStudioService::ensureSchema($pdo);

$id = (int)($_POST['id'] ?? 0);
$title = trim((string)($_POST['title'] ?? ''));
$promptText = trim((string)($_POST['prompt_text'] ?? ''));

if ($title === '' || $promptText === '') {
    flash('error', 'Titulo e prompt sao obrigatorios.');
    redirect('/web/admin/content_studio/production.php');
}

$status = in_array($_POST['status'] ?? '', ['active', 'draft', 'archived'], true)
    ? (string)$_POST['status']
    : 'active';

$payload = [
    ':idea_id' => (int)($_POST['idea_id'] ?? 0) ?: null,
    ':script_id' => (int)($_POST['script_id'] ?? 0) ?: null,
    ':title' => $title,
    ':prompt_text' => $promptText,
    ':context' => trim((string)($_POST['context'] ?? '')) ?: null,
    ':status' => $status,
];

if ($id > 0) {
    $stmt = $pdo->prepare("
        UPDATE content_studio_prompts
        SET idea_id = :idea_id,
            script_id = :script_id,
            title = :title,
            prompt_text = :prompt_text,
            context = :context,
            status = :status
        WHERE id = :id
    ");
    $payload[':id'] = $id;
    $stmt->execute($payload);
    flash('success', 'Prompt atualizado.');
} else {
    $stmt = $pdo->prepare("
        INSERT INTO content_studio_prompts
        (idea_id, script_id, title, prompt_text, context, status)
        VALUES
        (:idea_id, :script_id, :title, :prompt_text, :context, :status)
    ");
    $stmt->execute($payload);
    flash('success', 'Prompt salvo.');
}

redirect('/web/admin/content_studio/production.php');
