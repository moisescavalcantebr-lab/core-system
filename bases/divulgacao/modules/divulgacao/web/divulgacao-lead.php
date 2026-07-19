<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap/project_bootstrap.php';
require PROJECT_PATH . '/modules/divulgacao/web/admin/divulgacao/helpers.php';

divulgacaoEnsureSchema($pdo);
csrf_verify();

$pageId = (int)($_POST['page_id'] ?? 0);
$slug = divulgacaoSlug((string)($_POST['slug'] ?? ''));
$name = trim((string)($_POST['name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

if ($pageId <= 0 || $name === '' || $email === '') {
    redirect(PROJECT_URL . '/divulgacao.php?slug=' . urlencode($slug));
}

$stmt = $pdo->prepare('SELECT id, slug, action_type, destination_url, whatsapp FROM divulgacao_pages WHERE id = ? LIMIT 1');
$stmt->execute([$pageId]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    redirect(PROJECT_URL . '/divulgacao.php?slug=' . urlencode($slug));
}

$slug = (string)$page['slug'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect(PROJECT_URL . '/divulgacao.php?slug=' . urlencode($slug));
}

$stmt = $pdo->prepare("
    INSERT INTO divulgacao_leads
        (page_id, name, phone, email, message, ip_address, user_agent)
    VALUES
        (?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $pageId,
    $name,
    $phone,
    $email,
    $message,
    $_SERVER['REMOTE_ADDR'] ?? null,
    substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
]);

$actionType = divulgacaoActionType((string)($page['action_type'] ?? 'capture'));
if ($actionType === 'redirect') {
    $destinationUrl = divulgacaoExternalUrl((string)($page['destination_url'] ?? ''));
    if ($destinationUrl !== '') {
        redirect($destinationUrl);
    }
}

if ($actionType === 'whatsapp') {
    $phoneNumber = preg_replace('/\D+/', '', (string)($page['whatsapp'] ?? ''));
    if ($phoneNumber !== '') {
        redirect('https://wa.me/' . $phoneNumber);
    }
}

redirect(PROJECT_URL . '/divulgacao.php?slug=' . urlencode($slug) . '&lead=success');
