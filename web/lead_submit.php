<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método não permitido.');
}

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$state = trim($_POST['state'] ?? '');
$city = trim($_POST['city'] ?? '');
$baseId = (int)($_POST['base_id'] ?? 0);
$baseSlug = strtolower(trim((string)($_POST['base_slug'] ?? '')));
$ipAddress = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
$ipAddress = $ipAddress ? trim(explode(',', (string)$ipAddress)[0]) : null;
$userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
$referer = substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 500);
$contentCampaignKey = strtolower(trim((string)($_POST['content_campaign_key'] ?? $_GET['cs_campaign'] ?? '')));
$contentSource = strtolower(trim((string)($_POST['content_source'] ?? $_GET['cs_source'] ?? '')));
$contentCampaignKey = $contentCampaignKey !== '' ? substr(preg_replace('/[^a-z0-9_\-]+/', '-', $contentCampaignKey) ?? '', 0, 120) : null;
$contentSource = $contentSource !== '' ? substr(preg_replace('/[^a-z0-9_\-]+/', '-', $contentSource) ?? '', 0, 120) : null;

if ($name === '' || $phone === '' || $email === '' || $state === '' || $city === '') {
    http_response_code(422);
    die('Preencha todos os campos.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    die('E-mail inválido.');
}

$validBaseId = null;

if ($baseId > 0) {
    $stmt = $pdo->prepare("
        SELECT id
        FROM bases
        WHERE id = :id
        AND status = 1
        AND slug != 'base'
        AND base_stage = 'published'
        LIMIT 1
    ");

    $stmt->execute(['id' => $baseId]);
    $validBaseId = $stmt->fetchColumn() ?: null;
}

if (!$validBaseId && $baseSlug !== '') {
    $stmt = $pdo->prepare("
        SELECT id
        FROM bases
        WHERE slug = :slug
        AND status = 1
        AND slug != 'base'
        AND base_stage = 'published'
        LIMIT 1
    ");

    $stmt->execute(['slug' => $baseSlug]);
    $validBaseId = $stmt->fetchColumn() ?: null;
}

$stmt = $pdo->prepare("
    SELECT id
    FROM leads
    WHERE LOWER(email) = :email
    AND implementation_status != 'converted'
    AND (
        (:base_id_filter IS NULL AND base_id IS NULL)
        OR base_id = :base_id_match
    )
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([
    'email' => $email,
    'base_id_filter' => $validBaseId,
    'base_id_match' => $validBaseId,
]);
$existingLeadId = (int)($stmt->fetchColumn() ?: 0);

if ($existingLeadId > 0) {
    header('Location: /web/projects/thanks.php?status=lead_exists');
    exit;
}

$stmt = $pdo->prepare("
    SELECT slug
    FROM projects
    WHERE LOWER(owner_email) = :email
    AND (
        (:base_id_filter IS NULL AND base_id IS NULL)
        OR base_id = :base_id_match
    )
    AND status != 'deleted'
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([
    'email' => $email,
    'base_id_filter' => $validBaseId,
    'base_id_match' => $validBaseId,
]);
$existingProjectSlug = (string)($stmt->fetchColumn() ?: '');

if ($existingProjectSlug !== '') {
    header('Location: /web/projects/thanks.php?status=project_exists&project=' . urlencode($existingProjectSlug));
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO leads
    (name, phone, email, state, city, base_id, ip_address, user_agent, referer, content_campaign_key, content_source, created_at)
    VALUES
    (:name, :phone, :email, :state, :city, :base_id, :ip_address, :user_agent, :referer, :content_campaign_key, :content_source, NOW())
");

$stmt->execute([
    'name' => $name,
    'phone' => $phone,
    'email' => $email,
    'state' => $state,
    'city' => $city,
    'base_id' => $validBaseId,
    'ip_address' => $ipAddress,
    'user_agent' => $userAgent,
    'referer' => $referer,
    'content_campaign_key' => $contentCampaignKey,
    'content_source' => $contentSource,
]);

$leadId = (int)$pdo->lastInsertId();

header('Location: /web/projects/create.php?lead_id=' . $leadId);
exit;
