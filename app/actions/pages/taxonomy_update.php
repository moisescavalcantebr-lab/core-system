<?php
declare(strict_types=1);

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/helpers/flash.php';

requireAdmin();

global $pdo;

$id = (int)($_POST['id'] ?? 0);
$category = trim((string)($_POST['category'] ?? ''));
$subCategory = trim((string)($_POST['sub_category'] ?? ''));

if (!$id) {
    flash('error', 'Pagina invalida.');
    header('Location: /web/admin/pages/index.php');
    exit;
}

$baseUrl = '';

if (defined('PROJECT_PATH')) {
    $baseUrl = '/projects/' . basename(PROJECT_PATH);
}

$stmt = $pdo->prepare("
    SELECT id, type, category, sub_category
    FROM core_page_contents
    WHERE id = :id
    LIMIT 1
");

$stmt->execute(['id' => $id]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page || ($page['type'] ?? '') !== 'blog') {
    flash('error', 'Categorias sao editaveis apenas em paginas de blog.');
    header("Location: {$baseUrl}/web/admin/pages/edit.php?id={$id}");
    exit;
}

$categories = $pdo->query("
    SELECT DISTINCT category
    FROM core_page_contents
    WHERE area='public'
      AND type='blog'
      AND category IS NOT NULL
      AND category != ''
    ORDER BY category
")->fetchAll(PDO::FETCH_COLUMN);

$currentCategory = trim((string)($page['category'] ?? ''));
if ($currentCategory !== '' && !in_array($currentCategory, $categories, true)) {
    $categories[] = $currentCategory;
}

if ($category === '' || !in_array($category, $categories, true)) {
    flash('error', 'Selecione uma categoria existente.');
    header("Location: {$baseUrl}/web/admin/pages/edit.php?id={$id}");
    exit;
}

$stmt = $pdo->prepare("
    SELECT DISTINCT sub_category
    FROM core_page_contents
    WHERE area='public'
      AND type='blog'
      AND category = :category
      AND sub_category IS NOT NULL
      AND sub_category != ''
    ORDER BY sub_category
");

$stmt->execute(['category' => $category]);
$subCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);

$currentSubCategory = trim((string)($page['sub_category'] ?? ''));
if ($category === $currentCategory && $currentSubCategory !== '' && !in_array($currentSubCategory, $subCategories, true)) {
    $subCategories[] = $currentSubCategory;
}

if ($subCategory !== '' && !in_array($subCategory, $subCategories, true)) {
    flash('error', 'Selecione uma subcategoria existente para esta categoria.');
    header("Location: {$baseUrl}/web/admin/pages/edit.php?id={$id}");
    exit;
}

$stmt = $pdo->prepare("
    UPDATE core_page_contents
    SET category = :category,
        sub_category = :sub_category,
        updated_at = NOW()
    WHERE id = :id
      AND type = 'blog'
");

$stmt->execute([
    'category' => $category,
    'sub_category' => $subCategory !== '' ? $subCategory : null,
    'id' => $id,
]);

flash('success', 'Categoria do blog atualizada.');
header("Location: {$baseUrl}/web/admin/pages/edit.php?id={$id}");
exit;
