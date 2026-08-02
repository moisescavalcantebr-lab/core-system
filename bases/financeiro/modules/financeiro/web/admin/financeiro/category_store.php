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
    flash('error', 'Criar categorias e subcategorias fica disponivel no plano Start.');
    header('Location: ' . PROJECT_URL . '/admin/financeiro/categories.php');
    exit;
}

$name = trim((string)($_POST['name'] ?? ''));
$type = in_array($_POST['type'] ?? '', ['income', 'expense', 'both'], true) ? $_POST['type'] : 'both';
$formModel = financeNormalizeFormModel((string)($_POST['form_model'] ?? 'simple'));
$parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

if ($name === '') {
    exit('Nome obrigatorio.');
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

$stmt = $pdo->prepare("SELECT id FROM finance_categories WHERE name = ? LIMIT 1");
$stmt->execute([$name]);

if ($stmt->fetchColumn()) {
    flash('error', 'Ja existe uma categoria com este nome.');
    header('Location: ' . PROJECT_URL . '/admin/financeiro/categories.php');
    exit;
}

$stmt = $pdo->prepare("INSERT INTO finance_categories (parent_id, name, type, form_model, status) VALUES (?, ?, ?, ?, 'active')");
$stmt->execute([$parentId, $name, $type, $formModel]);

header('Location: ' . PROJECT_URL . '/admin/financeiro/categories.php');
exit;

