<?php
require __DIR__ . '/../../app/bootstrap/bootstrap.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$productId = (int)($data['product_id'] ?? 0);
$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');

if (!$productId || !$name || !$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO product_leads
    (product_id, name, email, phone)
    VALUES
    (:product_id, :name, :email, :phone)
");

$stmt->execute([
    'product_id' => $productId,
    'name' => $name,
    'email' => $email,
    'phone' => $phone
]);

echo json_encode(['success' => true]);
