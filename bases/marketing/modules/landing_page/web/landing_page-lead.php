<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap/project_bootstrap.php';
require PROJECT_PATH . '/modules/landing_page/web/admin/landing_page/helpers.php';

landingPageEnsureSchema($pdo);
csrf_verify();

$pageId = (int)($_POST['page_id'] ?? 0);
$slug = landingPageSlug((string)($_POST['slug'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

if ($pageId <= 0) {
    redirect(PROJECT_URL . '/landing_page.php?slug=' . urlencode($slug));
}

$stmt = $pdo->prepare("SELECT id, slug, action_type, destination_url, whatsapp, lead_fields_json FROM landing_page_pages WHERE id = ? AND status = 'published' LIMIT 1");
$stmt->execute([$pageId]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    redirect(PROJECT_URL . '/landing_page.php?slug=' . urlencode($slug));
}

$slug = (string)$page['slug'];

$values = landingPageLeadValues($page, $_POST);
if ($values === null) {
    redirect(PROJECT_URL . '/landing_page.php?slug=' . urlencode($slug) . '&lead=invalid');
}

$stmt = $pdo->prepare("
    INSERT INTO landing_page_leads
        (page_id, name, phone, email, message, ip_address, user_agent)
    VALUES
        (?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $pageId,
    $values['name'],
    $values['phone'],
    $values['email'] !== '' ? $values['email'] : null,
    $message,
    $_SERVER['REMOTE_ADDR'] ?? null,
    substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
]);

$actionType = landingPageActionType((string)($page['action_type'] ?? 'capture'));
if ($actionType === 'redirect') {
    $destinationUrl = landingPageExternalUrl((string)($page['destination_url'] ?? ''));
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

redirect(PROJECT_URL . '/landing_page.php?slug=' . urlencode($slug) . '&lead=success');
