<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';

require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/helpers/flash.php';

requireAdmin();

global $pdo;

/* =========================
FUNÇÃO SLUG ÚNICO
========================= */

function generateUniqueSlug(PDO $pdo, string $slug): string
{
    $base = $slug;
    $i = 1;

    while (true) {

        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM core_page_contents 
            WHERE slug = :slug
            AND type IN ('page','blog')
        ");

        $stmt->execute(['slug' => $slug]);

        if ($stmt->fetchColumn() == 0) {
            return $slug;
        }

        $slug = $base . '-' . $i;
        $i++;
    }
}

/* =========================
INPUT
========================= */

$title       = trim($_POST['title'] ?? '');
$slug        = trim($_POST['slug'] ?? '');
$modelSlug   = trim($_POST['model_slug'] ?? '');
$category    = trim($_POST['category'] ?? '') ?: null;
$subCategory = trim($_POST['sub_category'] ?? '') ?: null;

if (!$title || !$slug) {
    flash('error', 'Título e slug são obrigatórios.');
    header('Location: /public/admin/pages/create.php');
    exit;
}

/* =========================
NORMALIZAR SLUG
========================= */

$slug = strtolower($slug);
$slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
$slug = trim($slug, '-');

/* =========================
GERAR SLUG ÚNICO
========================= */

$slug = generateUniqueSlug($pdo, $slug);

/* =========================
TIPO
========================= */

$type = ($modelSlug === 'model_blog') ? 'blog' : 'page';

/* =========================
BASE URL
========================= */

$baseUrl = '';

if (defined('PROJECT_PATH')) {
    $baseUrl = '/projects/' . basename(PROJECT_PATH);
}

/* =========================
PATH JSON
========================= */

$fileName = uniqid('page_', true) . '.json';

$jsonDir  = STORAGE_PATH . '/paginas/pages/';
$jsonPath = $jsonDir . $fileName;

/* garantir pasta */
if (!is_dir($jsonDir)) {
    mkdir($jsonDir, 0755, true);
}

/* =========================
CRIAR JSON
========================= */

$data = [];

/* CLONAR MODELO */
if ($modelSlug) {

    $stmt = $pdo->prepare("
        SELECT content_path
        FROM core_page_contents
        WHERE slug = :slug
        AND type = 'model'
        LIMIT 1
    ");

    $stmt->execute(['slug' => $modelSlug]);

    $modelPath = $stmt->fetchColumn();

    if (!$modelPath) {
        flash('error', 'Modelo não encontrado.');
        header("Location: {$baseUrl}/public/admin/pages/create.php");
        exit;
    }

    $modelJsonPath = $jsonDir . $modelPath;

    if (file_exists($modelJsonPath)) {
        $json = file_get_contents($modelJsonPath);
        $decoded = json_decode($json, true);

        $data = is_array($decoded) ? $decoded : [];
    }
}

/* =========================
GARANTIR ESTRUTURA
========================= */

if (!isset($data['blocks']) || !is_array($data['blocks'])) {
    $data['blocks'] = [];
}

/* =========================
SALVAR JSON
========================= */

file_put_contents(
    $jsonPath,
    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

/* =========================
SALVAR NO BANCO
========================= */

$stmt = $pdo->prepare("
INSERT INTO core_page_contents
(title, slug, type, model_slug, category, sub_category, content_path)
VALUES
(:title, :slug, :type, :model_slug, :category, :sub_category, :path)
");

$stmt->execute([
    'title'        => $title,
    'slug'         => $slug,
    'type'         => $type,
    'model_slug'   => $modelSlug,
    'category'     => $category,
    'sub_category' => $subCategory,
    'path'         => $fileName
]);

$newId = (int)$pdo->lastInsertId();

/* =========================
SUCCESS
========================= */

flash('success', 'Página criada com sucesso.');

/* =========================
REDIRECT
========================= */

header("Location: {$baseUrl}/public/admin/pages/edit.php?id={$newId}");
exit;