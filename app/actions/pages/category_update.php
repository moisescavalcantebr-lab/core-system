<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';

require APP_PATH . '/helpers/auth.php';

requireAdmin();

/* =========================
HEADERS
========================= */

header('Content-Type: application/json; charset=utf-8');

global $pdo;

/* =========================
INPUT
========================= */

$data = json_decode(file_get_contents('php://input'), true);

$old = trim($data['old'] ?? '');
$new = trim($data['new'] ?? '');

if (!$old || !$new) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Dados inválidos'
    ]);
    exit;
}

/* evitar operação inútil */
if ($old === $new) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Categorias iguais'
    ]);
    exit;
}

/* =========================
SEGURANÇA BÁSICA
========================= */

$old = substr($old, 0, 100);
$new = substr($new, 0, 100);

/* =========================
UPDATE GLOBAL
========================= */

$stmt = $pdo->prepare("
    UPDATE core_page_contents
    SET category = :new
    WHERE category = :old
");

$stmt->execute([
    'new' => $new,
    'old' => $old
]);

/* =========================
RESPONSE
========================= */

echo json_encode([
    'status'  => 'ok',
    'updated' => $stmt->rowCount()
]);

exit;