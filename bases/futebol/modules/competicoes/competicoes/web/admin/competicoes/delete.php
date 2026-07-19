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

$stmt = $pdo->prepare("SELECT id FROM competitions WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$competition = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$competition) {
    flash('error', 'Competicao nao encontrada.');
    redirect(PROJECT_URL . '/admin/competicoes/index.php');
}

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM match_confirmations mc
        INNER JOIN matches m ON m.id = mc.match_id
        WHERE m.competition_id = ?
          AND mc.finance_entry_id IS NOT NULL
    ");
    $stmt->execute([$id]);

    if ((int)$stmt->fetchColumn() > 0) {
        flash('error', 'Esta competicao possui pagamentos financeiros vinculados e nao pode ser apagada.');
        redirect(PROJECT_URL . '/admin/competicoes/view.php?id=' . $id);
    }
} catch (Throwable $e) {
    // Bases antigas podem ainda nao ter todas as tabelas de confirmacao.
}

$pdo->beginTransaction();

try {
    $matchIds = [];
    $stmt = $pdo->prepare("SELECT id FROM matches WHERE competition_id = ?");
    $stmt->execute([$id]);
    $matchIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    foreach ($matchIds as $matchId) {
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
                $stmt->execute([$matchId]);
            } catch (Throwable $e) {
                continue;
            }
        }
    }

    foreach ([
        'competition_rules',
        'competition_participants',
        'classification_tables',
        'statistic_records',
        'matches',
    ] as $table) {
        try {
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE competition_id = ?");
            $stmt->execute([$id]);
        } catch (Throwable $e) {
            continue;
        }
    }

    $stmt = $pdo->prepare("DELETE FROM competitions WHERE id = ?");
    $stmt->execute([$id]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    flash('error', 'Nao foi possivel apagar a competicao.');
    redirect(PROJECT_URL . '/admin/competicoes/view.php?id=' . $id);
}

flash('success', 'Competicao apagada.');
redirect(PROJECT_URL . '/admin/competicoes/index.php');
