<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/competition_helpers.php';

requireProjectAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

function competitionRelatedCount(PDO $pdo, int $competitionId): int
{
    $total = 0;

    foreach (['competition_participants', 'statistic_records', 'matches'] as $table) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE competition_id = ?");
            $stmt->execute([$competitionId]);
            $total += (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            continue;
        }
    }

    return $total;
}

$id = (int)($_GET['id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$context = in_array($_POST['context'] ?? '', ['external', 'internal'], true) ? $_POST['context'] : 'external';
$type = in_array($_POST['type'] ?? '', ['championship','cup','league','tournament','friendly','training','challenge','ranking','other'], true) ? $_POST['type'] : 'tournament';
$status = in_array($_POST['status'] ?? '', ['draft','active','finished','canceled'], true) ? $_POST['status'] : 'active';
$startsAt = trim((string)($_POST['starts_at'] ?? '')) ?: null;
$endsAt = trim((string)($_POST['ends_at'] ?? '')) ?: null;
$notes = trim((string)($_POST['notes'] ?? '')) ?: null;

if ($id <= 0 || $name === '') {
    flash('error', 'Dados invalidos.');
    redirect(PROJECT_URL . '/admin/competicoes/index.php');
}

if ($context === 'internal') {
    if (!function_exists('projectModuleProvides') || !projectModuleProvides('internal_competitions')) {
        flash('error', 'Treino interno precisa do modulo Treinos Internos.');
        redirect(PROJECT_URL . '/admin/competicoes/index.php');
    }

    $type = 'training';
} elseif ($type === 'training') {
    $type = 'tournament';
}

$stmt = $pdo->prepare("SELECT * FROM competitions WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$competition = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$competition) {
    flash('error', 'Competicao nao encontrada.');
    redirect(PROJECT_URL . '/admin/competicoes/index.php');
}

if (competitionIsDefaultFriendly($competition)) {
    flash('error', 'O Amistoso padrão não pode ser editado.');
    redirect(PROJECT_URL . '/admin/competicoes/view.php?id=' . $id);
}

$hasRelatedData = competitionRelatedCount($pdo, $id) > 0;

if ($hasRelatedData) {
    $stmt = $pdo->prepare("
        UPDATE competitions
        SET name = ?, season = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $name,
        trim((string)($_POST['season'] ?? '')) ?: null,
        $id,
    ]);
} else {
    $stmt = $pdo->prepare("
        UPDATE competitions
        SET name = ?, context = ?, type = ?, season = ?, status = ?, starts_at = ?, ends_at = ?, notes = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $name,
        $context,
        $type,
        trim((string)($_POST['season'] ?? '')) ?: null,
        $status,
        $startsAt,
        $endsAt,
        $notes,
        $id,
    ]);
}

flash('success', 'Competicao atualizada.');
redirect(PROJECT_URL . '/admin/competicoes/index.php');
