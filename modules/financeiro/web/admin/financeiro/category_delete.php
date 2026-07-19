<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectRole(['ADMIN', 'FINANCE']);
financeEnsureCategoryAddonSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $pdo->prepare("UPDATE finance_entries SET category_id = NULL WHERE category_id = ?")->execute([$id]);
    $pdo->prepare("UPDATE finance_entries SET category_id = NULL WHERE category_id IN (SELECT id FROM finance_categories WHERE parent_id = ?)")->execute([$id]);
    $pdo->prepare("DELETE FROM finance_categories WHERE parent_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM finance_categories WHERE id = ?")->execute([$id]);
}

header('Location: ' . PROJECT_URL . '/admin/financeiro/categories.php');
exit;

