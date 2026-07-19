<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

$classificationFieldsPath = __DIR__ . '/../classificacao/fields.php';
$classificationEnabled = function_exists('projectModuleProvides')
    && projectModuleProvides('individual_classification')
    && is_file($classificationFieldsPath);

if ($classificationEnabled) {
    require_once $classificationFieldsPath;
}

requireProjectAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Partida invalida.');
    redirect(PROJECT_URL . '/admin/partidas/index.php');
}

$stmt = $pdo->prepare("
    SELECT m.id, m.competition_id, c.context
    FROM matches m
    LEFT JOIN competitions c ON c.id = m.competition_id
    WHERE m.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    flash('error', 'Partida nao encontrada.');
    redirect(PROJECT_URL . '/admin/partidas/index.php');
}

$competitionId = (int)($match['competition_id'] ?? 0);
$redirectUrl = $competitionId > 0
    ? PROJECT_URL . '/admin/competicoes/view.php?id=' . $competitionId
    : PROJECT_URL . '/admin/partidas/index.php';

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM match_confirmations WHERE match_id = ? AND finance_entry_id IS NOT NULL");
    $stmt->execute([$id]);

    if ((int)$stmt->fetchColumn() > 0) {
        flash('error', 'Esta partida possui pagamento financeiro vinculado e nao pode ser apagada.');
        redirect($redirectUrl);
    }
} catch (Throwable $e) {
    // Se a tabela ainda nao existir em uma base antiga, segue o fluxo normal.
}

$pdo->beginTransaction();

try {
    foreach ([
        'match_lineup',
        'match_confirmation_logs',
        'match_confirmations',
        'statistic_records',
        'lineup_sheets',
        'scoreboards',
        'result_sets',
    ] as $table) {
        try {
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE match_id = ?");
            $stmt->execute([$id]);
        } catch (Throwable $e) {
            continue;
        }
    }

    $stmt = $pdo->prepare("DELETE FROM matches WHERE id = ?");
    $stmt->execute([$id]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    flash('error', 'Nao foi possivel apagar a partida.');
    redirect($redirectUrl);
}

if ($competitionId > 0 && ($match['context'] ?? '') === 'internal' && $classificationEnabled && function_exists('classificationRebuildInternalCompetition')) {
    classificationRebuildInternalCompetition($pdo, $competitionId);
}

flash('success', 'Partida apagada.');
redirect($redirectUrl);
