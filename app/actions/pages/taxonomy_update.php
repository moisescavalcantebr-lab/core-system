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
$categoryId = (int)($_POST['category_id'] ?? 0);
$subCategoryId = (int)($_POST['sub_category_id'] ?? 0);

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

$stmt = $pdo->prepare("SELECT name FROM blog_categories WHERE id = ? AND status = 1 LIMIT 1");
$stmt->execute([$categoryId]);
$category = trim((string)$stmt->fetchColumn());

if ($category === '') {
    flash('error', 'Selecione uma categoria existente.');
    header("Location: {$baseUrl}/web/admin/pages/edit.php?id={$id}");
    exit;
}

$subCategory = '';

if ($subCategoryId > 0) {
    $stmt = $pdo->prepare("SELECT name FROM blog_subcategories WHERE id = ? AND category_id = ? AND status = 1 LIMIT 1");
    $stmt->execute([$subCategoryId, $categoryId]);
    $subCategory = trim((string)$stmt->fetchColumn());

    if ($subCategory === '') {
        flash('error', 'Selecione uma subcategoria existente para esta categoria.');
        header("Location: {$baseUrl}/web/admin/pages/edit.php?id={$id}");
        exit;
    }
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
