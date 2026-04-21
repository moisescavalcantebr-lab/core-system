<?php
require __DIR__ . '/../../app/bootstrap/bootstrap.php';

$token = $_POST['token'] ?? '';

$pdo->prepare("
    UPDATE project_access_tokens
    SET used = 1
    WHERE token = :token
")->execute(['token' => $token]);

echo json_encode(['success' => true]);
