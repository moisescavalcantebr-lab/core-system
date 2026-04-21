<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap/bootstrap.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$token = $data['token'] ?? '';

if (!$token) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE project_install_tokens
    SET used = 1
    WHERE token = :token
");

$stmt->execute(['token' => $token]);

echo json_encode(['success' => true]);
