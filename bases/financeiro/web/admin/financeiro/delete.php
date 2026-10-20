<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__.'/helpers.php';

requireProjectRole(['ADMIN', 'FINANCE']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    if(financeTeamEnabled()){
        financeTeamEnsureSchema($pdo);
        if(financeTeamTracked($pdo,$id)){http_response_code(422);exit('Use cancelar com motivo para preservar o historico.');}
    }
    $stmt = $pdo->prepare("DELETE FROM finance_entries WHERE id = ?");
    $stmt->execute([$id]);
}

header('Location: ' . PROJECT_URL . '/admin/financeiro/index.php');
exit;

