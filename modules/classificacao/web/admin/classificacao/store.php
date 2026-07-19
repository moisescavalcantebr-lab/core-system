<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/fields.php';

requireProjectAdmin();

if (!function_exists('projectModuleProvides') || !projectModuleProvides('individual_classification')) {
    flash('error', 'Classificacao individual precisa do modulo Classificacao.');
    redirect(PROJECT_URL . '/admin/competicoes/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$fields = $_POST['active_fields'] ?? [];
$competitionId = (int)($_POST['competition_id'] ?? ($_GET['competition_id'] ?? 0));

if ($competitionId <= 0 || !is_array($fields) || empty($fields)) {
    exit('Informe a competicao e pelo menos um campo.');
}

$stmt = $pdo->prepare("SELECT name, context FROM competitions WHERE id = ? LIMIT 1");
$stmt->execute([$competitionId]);
$competition = $stmt->fetch(PDO::FETCH_ASSOC);
$name = (string)($competition['name'] ?? '');

if ($name === '') {
    exit('Competicao nao encontrada.');
}

$stmt = $pdo->prepare("SELECT id FROM classification_tables WHERE competition_id = ? LIMIT 1");
$stmt->execute([$competitionId]);
if ($stmt->fetch(PDO::FETCH_ASSOC)) {
    exit('Esta competicao ja possui classificacao.');
}

$available = array_keys(classificationAvailableFields());
$fields = array_values(array_intersect($available, $fields));
$sortField = in_array($_POST['sort_field'] ?? '', array_merge(['name'], $available), true) ? $_POST['sort_field'] : 'position';
$sortDirection = ($_POST['sort_direction'] ?? '') === 'desc' ? 'desc' : 'asc';
$status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

$stmt = $pdo->prepare("
    INSERT INTO classification_tables (competition_id, name, description, active_fields, sort_field, sort_direction, status)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $competitionId,
    $name,
    null,
    json_encode($fields),
    $sortField,
    $sortDirection,
    $status,
]);

$newId = (int)$pdo->lastInsertId();

if (($competition['context'] ?? '') === 'internal') {
    classificationRebuildInternalCompetition($pdo, $competitionId);
}

header('Location: ' . PROJECT_URL . '/admin/classificacao/index.php');
exit;
