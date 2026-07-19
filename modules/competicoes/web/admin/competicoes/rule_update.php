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
$title = trim((string)($_POST['title'] ?? ''));
$description = trim((string)($_POST['description'] ?? '')) ?: null;
$sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
$status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

if ($id <= 0 || $title === '') {
    flash('error', 'Dados invalidos.');
    redirect(PROJECT_URL . '/admin/competicoes/index.php');
}

$stmt = $pdo->prepare("SELECT competition_id FROM competition_rules WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$competitionId = (int)($stmt->fetchColumn() ?: 0);

if ($competitionId <= 0) {
    flash('error', 'Regra nao encontrada.');
    redirect(PROJECT_URL . '/admin/competicoes/index.php');
}

$stmt = $pdo->prepare("
    UPDATE competition_rules
    SET title = ?, description = ?, sort_order = ?, status = ?
    WHERE id = ?
");
$stmt->execute([$title, $description, $sortOrder, $status, $id]);

flash('success', 'Regra atualizada.');
redirect(PROJECT_URL . '/admin/competicoes/rules.php?id=' . $competitionId);
