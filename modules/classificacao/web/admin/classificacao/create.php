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

$competition = null;
$competitionId = (int)($_GET['competition_id'] ?? 0);

if ($competitionId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM competitions WHERE id = ? LIMIT 1");
    $stmt->execute([$competitionId]);
    $competition = $stmt->fetch(PDO::FETCH_ASSOC);
}

$title = 'Nova Classificacao';
$table = [
    'id' => null,
    'competition_id' => $competition ? (int)$competition['id'] : null,
    'name' => '',
    'description' => '',
    'active_fields' => json_encode(classificationDefaultFields($competition['context'] ?? null)),
    'sort_field' => ($competition['context'] ?? '') === 'internal' ? 'points' : 'position',
    'sort_direction' => ($competition['context'] ?? '') === 'internal' ? 'desc' : 'asc',
    'status' => 'active',
];
$formAction = PROJECT_URL . '/admin/classificacao/store.php' . ($competition ? '?competition_id=' . (int)$competition['id'] : '');
$submitLabel = 'Salvar Classificacao';

ob_start();
require __DIR__ . '/form.php';
$content = ob_get_clean();

require APP_PATH . '/views/layout_admin.php';
