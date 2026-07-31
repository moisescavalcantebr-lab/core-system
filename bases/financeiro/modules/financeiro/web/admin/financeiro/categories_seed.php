<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectRole(['ADMIN', 'FINANCE']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

if (!financeAdvancedCategoriesEnabled()) {
    flash('error', 'Subcategorias estao disponiveis a partir do plano Start.');
    header('Location: ' . PROJECT_URL . '/admin/financeiro/categories.php');
    exit;
}

$templateKey = (string)($_POST['template'] ?? financeRecommendedCategoryTemplate($pdo));
$templates = financeCategoryTemplates();

if (!isset($templates[$templateKey])) {
    $templateKey = financeRecommendedCategoryTemplate($pdo);
}

$created = financeSeedCategoryTemplate($pdo, $templateKey);
$templateLabel = (string)($templates[$templateKey]['label'] ?? 'Modelo');

flash('success', $created > 0
    ? $templateLabel . ' carregado. Categorias criadas: ' . $created . '.'
    : 'Modelo ja estava carregado. Nenhuma categoria nova foi criada.'
);

header('Location: ' . PROJECT_URL . '/admin/financeiro/categories.php');
exit;
