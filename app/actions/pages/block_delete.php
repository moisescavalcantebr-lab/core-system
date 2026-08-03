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
$index = (int)($_GET['index'] ?? 0);

if (!$pageId) {
    exit('Pagina invalida');
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
    $decoded = json_decode((string)file_get_contents($jsonPath), true);
    $data = is_array($decoded) ? $decoded : [];
}

$data['blocks'] = $data['blocks'] ?? [];
$blocks = $data['blocks'];

if (isset($blocks[$index])) {
    unset($blocks[$index]);
}

$data['blocks'] = array_values($blocks);
$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if ($json === false || @file_put_contents($jsonPath, $json) === false) {
    error_log('Nao foi possivel salvar a pagina em JSON: ' . $jsonPath);
    flash('error', 'Nao foi possivel alterar a pagina. Verifique as permissoes de storage/paginas no servidor.');
    header("Location: {$baseUrl}/web/admin/pages/edit.php?id={$pageId}");
    exit;
}

header("Location: {$baseUrl}/web/admin/pages/edit.php?id={$pageId}");
exit;
