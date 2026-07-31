<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectRole(['ADMIN', 'FINANCE']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT status FROM finance_categories WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $status = $stmt->fetchColumn();

    if ($status) {
        $newStatus = $status === 'active' ? 'inactive' : 'active';
        $update = $pdo->prepare("UPDATE finance_categories SET status = ? WHERE id = ?");
        $update->execute([$newStatus, $id]);
    }
}

header('Location: ' . PROJECT_URL . '/admin/financeiro/categories.php');
exit;

