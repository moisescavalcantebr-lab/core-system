<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Competicao invalida.');
    redirect(PROJECT_URL . '/admin/competicoes/index.php');
}

$stmt = $pdo->prepare("UPDATE competitions SET status = 'finished' WHERE id = ?");
$stmt->execute([$id]);

flash('success', 'Competicao finalizada.');
redirect(PROJECT_URL . '/admin/competicoes/index.php');
