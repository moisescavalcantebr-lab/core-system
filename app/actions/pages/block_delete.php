<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

global $pdo;

/* =========================
INPUT
========================= */

$pageId = (int)($_GET['page_id'] ?? 0);
$index  = (int)($_GET['index'] ?? 0);

if (!$pageId) {
    exit('Página inválida');
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
BASE URL
========================= */

$baseUrl = '';

if (defined('PROJECT_PATH')) {
    $baseUrl = '/projects/' . basename(PROJECT_PATH);
}

/* =========================
CAMINHO JSON
========================= */

$jsonPath = STORAGE_PATH . '/paginas/pages/' . $contentPath;

/* garantir pasta */

$dir = dirname($jsonPath);

if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

/* =========================
CARREGAR JSON
========================= */

$data = [];

if (file_exists($jsonPath)) {
    $decoded = json_decode(file_get_contents($jsonPath), true);
    $data = is_array($decoded) ? $decoded : [];
}

/* garantir estrutura */

$data['blocks'] = $data['blocks'] ?? [];
$blocks = $data['blocks'];

/* =========================
REMOVER BLOCO
========================= */

if (isset($blocks[$index])) {
    unset($blocks[$index]);
    $blocks = array_values($blocks); // reindexar
}

/* =========================
SALVAR JSON
========================= */

$data['blocks'] = $blocks;

file_put_contents(
    $jsonPath,
    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

/* =========================
REDIRECT (PADRÃO CORE)
========================= */

header("Location: {$baseUrl}/public/admin/pages/edit.php?id={$pageId}");
exit;