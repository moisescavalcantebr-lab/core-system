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
$type   = $_GET['type'] ?? '';

if (!$pageId || trim($type) === '') {
    http_response_code(400);
    exit('Dados invalidos');
}

/* sanitizar */
$type = preg_replace('/[^a-z0-9_\-]/i', '', $type);

if ($type === '') {
    exit('Tipo invalido');
}

/* =========================
VALIDAR SCHEMA
========================= */

$schema = require APP_PATH . '/config/blocks.php';

if (!isset($schema[$type])) {
    exit('Bloco não permitido');
}

/* =========================
BUSCAR PáGINA
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

    $raw = file_get_contents($jsonPath);
    $decoded = json_decode($raw, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $data = $decoded;
    } else {
        error_log("JSON invÃ¡lido em: " . $jsonPath);
    }
}

/* garantir estrutura */
$data['blocks'] = $data['blocks'] ?? [];
$blocks = $data['blocks'];

/*  CRiTICO: normalizar indices */
$blocks = array_values($blocks);

/* =========================
CRIAR BLOCO
========================= */

$newBlock = [
    'type'    => $type,
    'enabled' => true
];

/* defaults do schema */

$fields = $schema[$type]['fields'] ?? [];

foreach ($fields as $fieldName => $fieldConfig) {

    if (array_key_exists('default', $fieldConfig)) {
        $newBlock[$fieldName] = $fieldConfig['default'];
    }
}

/* =========================
ADICIONAR BLOCO
========================= */

$blocks[] = $newBlock;

/* ðŸ”¥ garantir consistÃªncia */
$blocks = array_values($blocks);

$data['blocks'] = $blocks;





function sanitizeUtf8($data) {
    if (is_array($data)) {
        return array_map('sanitizeUtf8', $data);
    }

    if (is_string($data)) {
        return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
    }

    return $data;
}

$data = sanitizeUtf8($data);
/* =========================
SALVAR JSON
========================= */

$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if ($json === false) {
    error_log('Erro ao gerar JSON: ' . json_last_error_msg());
    exit('Erro ao salvar dados');
}

file_put_contents($jsonPath, $json);

/* =========================
REDIRECT
========================= */

header("Location: {$baseUrl}/public/admin/pages/edit.php?id={$pageId}");
exit;