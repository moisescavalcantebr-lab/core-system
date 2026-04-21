<?php
require __DIR__ . '/../../app/bootstrap/bootstrap.php';

$token = $_POST['token'] ?? '';

if (!$token) {
    http_response_code(400);
    exit;
}

$stmt = $pdo->prepare("
    SELECT * FROM project_access_tokens
    WHERE token = :token
    AND used = 0
    AND expires_at >= NOW()
");
$stmt->execute(['token' => $token]);

$access = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$access) {
    echo json_encode(['valid' => false]);
    exit;
}

echo json_encode([
    'valid' => true,
    'project_id' => $access['project_id'],
    'email' => $access['email']
]);
