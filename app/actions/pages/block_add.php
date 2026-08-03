<?php
declare(strict_types=1);

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/helpers/flash.php';

requireAdmin();

global $pdo;

$pageId = (int)($_GET['page_id'] ?? 0);
$type = $_GET['type'] ?? '';

if (!$pageId || trim($type) === '') {
    http_response_code(400);
    exit('Dados invalidos');
}

$type = preg_replace('/[^a-z0-9_\-]/i', '', $type);

if ($type === '') {
    exit('Tipo invalido');
}

$schema = require APP_PATH . '/config/blocks.php';

if (!isset($schema[$type])) {
    exit('Bloco nao permitido');
}

$stmt = $pdo->prepare("
    SELECT content_path
    FROM core_page_contents
    WHERE id = :id
");
$stmt->execute(['id' => $pageId]);
$contentPath = $stmt->fetchColumn();

if (!$contentPath) {
    exit('Pagina nao encontrada');
}

$baseUrl = '';

if (defined('PROJECT_PATH')) {
    $baseUrl = '/projects/' . basename(PROJECT_PATH);
}

$jsonPath = STORAGE_PATH . '/paginas/pages/' . $contentPath;
$dir = dirname($jsonPath);

if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
}

if (!is_dir($dir) || !is_writable($dir)) {
    flash('error', 'Nao foi possivel alterar a pagina. A pasta storage/paginas/pages nao esta gravavel no servidor.');
    header("Location: {$baseUrl}/web/admin/pages/edit.php?id={$pageId}");
    exit;
}

$data = [];

if (file_exists($jsonPath)) {
    $raw = file_get_contents($jsonPath);
    $decoded = json_decode((string)$raw, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $data = $decoded;
    } else {
        error_log('JSON invalido em: ' . $jsonPath);
    }
}

$data['blocks'] = $data['blocks'] ?? [];
$blocks = array_values($data['blocks']);

$newBlock = [
    'type' => $type,
    'enabled' => true,
];

$fields = $schema[$type]['fields'] ?? [];

foreach ($fields as $fieldName => $fieldConfig) {
    if (array_key_exists('default', $fieldConfig)) {
        $newBlock[$fieldName] = $fieldConfig['default'];
    }
}

$blocks[] = $newBlock;
$data['blocks'] = array_values($blocks);

function sanitizeUtf8($data)
{
    if (is_array($data)) {
        return array_map('sanitizeUtf8', $data);
    }

    if (is_string($data)) {
        return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
    }

    return $data;
}

$data = sanitizeUtf8($data);
$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if ($json === false) {
    error_log('Erro ao gerar JSON: ' . json_last_error_msg());
    flash('error', 'Nao foi possivel gerar o conteudo da pagina.');
    header("Location: {$baseUrl}/web/admin/pages/edit.php?id={$pageId}");
    exit;
}

$saved = @file_put_contents($jsonPath, $json);

if ($saved === false) {
    error_log('Nao foi possivel salvar a pagina em JSON: ' . $jsonPath);
    flash('error', 'Nao foi possivel alterar a pagina. Verifique as permissoes de storage/paginas no servidor.');
    header("Location: {$baseUrl}/web/admin/pages/edit.php?id={$pageId}");
    exit;
}

header("Location: {$baseUrl}/web/admin/pages/edit.php?id={$pageId}");
exit;
