<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectRole(['ADMIN', 'FINANCE']);
financeEnsureCategoryAddonSchema($pdo);
$advancedCategories = financeAdvancedCategoriesEnabled();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

if (!$advancedCategories) {
    flash('error', 'Editar categorias fica disponivel no plano Start.');
    header('Location: ' . PROJECT_URL . '/admin/financeiro/categories.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$type = in_array($_POST['type'] ?? '', ['income', 'expense', 'both'], true) ? $_POST['type'] : 'both';
$formModel = financeNormalizeFormModel((string)($_POST['form_model'] ?? 'simple'));
$status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
$parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

if ($id <= 0 || $name === '') {
    exit('Dados invalidos.');
}

if ($parentId === $id) {
    exit('Categoria pai invalida.');
}

if ($parentId) {
    $stmt = $pdo->prepare("SELECT type FROM finance_categories WHERE id = ? AND parent_id IS NULL LIMIT 1");
    $stmt->execute([$parentId]);
    $parentType = $stmt->fetchColumn();

    if (!$parentType) {
        exit('Categoria pai invalida.');
    }

    if ($parentType === 'income' || $parentType === 'expense') {
        $type = (string)$parentType;
    }
}

$stmt = $pdo->prepare("SELECT id FROM finance_categories WHERE name = ? AND id <> ? LIMIT 1");
$stmt->execute([$name, $id]);

if ($stmt->fetchColumn()) {
    flash('error', 'Ja existe uma categoria com este nome.');
    header('Location: ' . PROJECT_URL . '/admin/financeiro/category_edit.php?id=' . $id);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE finance_categories
    SET parent_id = ?,
        name = ?,
        type = ?,
        form_model = ?,
        status = ?
    WHERE id = ?
");

$stmt->execute([$parentId, $name, $type, $formModel, $status, $id]);

header('Location: ' . PROJECT_URL . '/admin/financeiro/categories.php');
exit;

