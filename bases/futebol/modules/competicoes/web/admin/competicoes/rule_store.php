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

$competitionId = (int)($_GET['id'] ?? 0);
$title = trim((string)($_POST['title'] ?? ''));
$description = trim((string)($_POST['description'] ?? '')) ?: null;
$sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
$status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

if ($competitionId <= 0 || $title === '') {
    flash('error', 'Informe o titulo da regra.');
    redirect(PROJECT_URL . '/admin/competicoes/index.php');
}

$stmt = $pdo->prepare("
    INSERT INTO competition_rules (competition_id, title, description, sort_order, status)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([$competitionId, $title, $description, $sortOrder, $status]);

flash('success', 'Regra adicionada.');
redirect(PROJECT_URL . '/admin/competicoes/rules.php?id=' . $competitionId);
