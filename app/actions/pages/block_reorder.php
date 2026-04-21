<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

global $pdo;

$data = json_decode(file_get_contents('php://input'), true);

$pageId = (int)($data['page_id'] ?? 0);
$order  = $data['order'] ?? [];

if (!$pageId || !is_array($order)) {
    exit('Dados inválidos');
}

/* =========================
BUSCAR PÁGINA
========================= */

$stmt = $pdo->prepare("
SELECT content_path
FROM core_page_contents
WHERE id = :id
");

$stmt->execute(['id' => $pageId]);

$contentPath = $stmt->fetchColumn();

if (!$contentPath) {
    exit('Página não encontrada');
}

/* =========================
JSON
========================= */

$jsonPath = STORAGE_PATH . '/paginas/pages/' . $contentPath;

$dataJson = [];

if (file_exists($jsonPath)) {
    $decoded = json_decode(file_get_contents($jsonPath), true);
    $dataJson = is_array($decoded) ? $decoded : [];
}

$blocks = $dataJson['blocks'] ?? [];

/* =========================
REORDENAR
========================= */

$newBlocks = [];

foreach ($order as $oldIndex) {
    if (isset($blocks[$oldIndex])) {
        $newBlocks[] = $blocks[$oldIndex];
    }
}

$dataJson['blocks'] = $newBlocks;

/* =========================
SALVAR
========================= */

file_put_contents(
    $jsonPath,
    json_encode($dataJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

echo json_encode(['success' => true]);