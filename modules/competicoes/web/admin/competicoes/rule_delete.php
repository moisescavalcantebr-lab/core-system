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
$competitionId = (int)($_GET['competition_id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM competition_rules WHERE id = ?");
    $stmt->execute([$id]);
}

flash('success', 'Regra excluida.');
redirect(PROJECT_URL . '/admin/competicoes/rules.php?id=' . $competitionId);
