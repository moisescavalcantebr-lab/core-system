<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/lineup_helpers.php';

$classificationFieldsPath = __DIR__ . '/../classificacao/fields.php';
$classificationEnabled = function_exists('projectModuleProvides')
    && projectModuleProvides('individual_classification')
    && is_file($classificationFieldsPath);

if ($classificationEnabled) {
    require_once $classificationFieldsPath;
}

requireProjectAdmin();
matchLineupEnsureSchema($pdo);

function liveUpdateWantsJson(): bool
{
    return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch'
        || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
}

function liveUpdateJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$id = (int)($_GET['id'] ?? 0);
$action = (string)($_POST['action'] ?? 'score');
$scoreA = max(0, (int)($_POST['score_a'] ?? 0));
$scoreB = max(0, (int)($_POST['score_b'] ?? 0));
$yellowCardsA = max(0, (int)($_POST['yellow_cards_a'] ?? 0));
$yellowCardsB = max(0, (int)($_POST['yellow_cards_b'] ?? 0));
$redCardsA = max(0, (int)($_POST['red_cards_a'] ?? 0));
$redCardsB = max(0, (int)($_POST['red_cards_b'] ?? 0));

function ensureMatchCardColumns(PDO $pdo): void
{
    $columns = [
        'yellow_cards_a' => "ALTER TABLE matches ADD COLUMN yellow_cards_a INT NOT NULL DEFAULT 0 AFTER score_b",
        'yellow_cards_b' => "ALTER TABLE matches ADD COLUMN yellow_cards_b INT NOT NULL DEFAULT 0 AFTER yellow_cards_a",
        'red_cards_a' => "ALTER TABLE matches ADD COLUMN red_cards_a INT NOT NULL DEFAULT 0 AFTER yellow_cards_b",
        'red_cards_b' => "ALTER TABLE matches ADD COLUMN red_cards_b INT NOT NULL DEFAULT 0 AFTER red_cards_a",
    ];

    foreach ($columns as $column => $sql) {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'matches'
                  AND COLUMN_NAME = ?
            ");
            $stmt->execute([$column]);

            if ((int)$stmt->fetchColumn() === 0) {
                $pdo->exec($sql);
            }
        } catch (Throwable $e) {
            continue;
        }
    }
}

function ensureMatchAttendanceSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS match_attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            match_id INT NOT NULL,
            player_id INT NOT NULL,
            status ENUM('present','excused_absence','no_response','confirmed_absent','justified_absent') NOT NULL DEFAULT 'no_response',
            points DECIMAL(4,1) NOT NULL DEFAULT 0.0,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_match_player_attendance (match_id, player_id),
            INDEX(match_id),
            INDEX(player_id),
            INDEX(status),
            INDEX(points)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function attendancePoints(string $status): string
{
    return match ($status) {
        'present' => '1.0',
        'excused_absence' => '0.5',
        'confirmed_absent' => '-1.0',
        default => '0.0',
    };
}

function saveMatchAttendance(PDO $pdo, int $matchId, array $attendance): void
{
    if ($matchId <= 0 || empty($attendance)) {
        return;
    }

    $allowedStatuses = ['present', 'excused_absence', 'no_response', 'confirmed_absent', 'justified_absent'];
    $stmt = $pdo->prepare("
        INSERT INTO match_attendance (match_id, player_id, status, points, notes)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            points = VALUES(points),
            notes = VALUES(notes),
            updated_at = CURRENT_TIMESTAMP
    ");

    foreach ($attendance as $playerId => $data) {
        $playerId = (int)$playerId;
        $status = is_array($data) ? (string)($data['status'] ?? 'no_response') : 'no_response';
        $notes = is_array($data) ? trim((string)($data['notes'] ?? '')) : '';

        if ($playerId <= 0 || !in_array($status, $allowedStatuses, true)) {
            continue;
        }

        $stmt->execute([$matchId, $playerId, $status, attendancePoints($status), $notes !== '' ? $notes : null]);
    }
}

function saveDefaultMatchAttendance(PDO $pdo, int $matchId): void
{
    if ($matchId <= 0) {
        return;
    }

    $stmt = $pdo->prepare("
        SELECT p.id AS player_id, mc.status AS confirmation_status
        FROM players p
        LEFT JOIN match_confirmations mc ON mc.match_id = ? AND mc.player_id = p.id
        WHERE p.status = 'active'
    ");
    $stmt->execute([$matchId]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $attendance = [];
    foreach ($players as $player) {
        $status = match ($player['confirmation_status'] ?? null) {
            'confirmed' => 'present',
            'declined' => 'excused_absence',
            default => 'no_response',
        };
        $attendance[(int)$player['player_id']] = ['status' => $status, 'notes' => ''];
    }

    saveMatchAttendance($pdo, $matchId, $attendance);
}

ensureMatchCardColumns($pdo);
ensureMatchAttendanceSchema($pdo);

if ($id <= 0) {
    if (liveUpdateWantsJson()) {
        liveUpdateJson(['ok' => false, 'message' => 'Partida invalida.'], 422);
    }
    unset($_SESSION['flash']);
    redirect(PROJECT_URL . '/admin/partidas/index.php?notice=invalid');
}

$stmt = $pdo->prepare("SELECT id, status, competition_id FROM matches WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    if (liveUpdateWantsJson()) {
        liveUpdateJson(['ok' => false, 'message' => 'Partida nao encontrada.'], 404);
    }
    unset($_SESSION['flash']);
    redirect(PROJECT_URL . '/admin/partidas/index.php?notice=not_found');
}

$returnUrl = !empty($match['competition_id'])
    ? PROJECT_URL . '/admin/competicoes/view.php?id=' . (int)$match['competition_id']
    : PROJECT_URL . '/admin/partidas/index.php';

$postedAttendance = is_array($_POST['attendance'] ?? null) ? $_POST['attendance'] : [];
if (!empty($postedAttendance)) {
    saveMatchAttendance($pdo, $id, $postedAttendance);
}

if ($action === 'finish') {
    if (empty($postedAttendance)) {
        saveDefaultMatchAttendance($pdo, $id);
    }

    matchLineupSaveFieldSnapshot($pdo, $id);

    $stmt = $pdo->prepare("
        UPDATE matches
        SET score_a = ?,
            score_b = ?,
            yellow_cards_a = ?,
            yellow_cards_b = ?,
            red_cards_a = ?,
            red_cards_b = ?,
            status = 'finished'
        WHERE id = ?
    ");
    $stmt->execute([$scoreA, $scoreB, $yellowCardsA, $yellowCardsB, $redCardsA, $redCardsB, $id]);

    if (!empty($match['competition_id']) && $classificationEnabled && function_exists('classificationRebuildInternalCompetition')) {
        classificationRebuildInternalCompetition($pdo, (int)$match['competition_id']);
    }

    flash('success', 'Partida finalizada.');
    redirect($returnUrl);
}

$nextStatus = ($match['status'] ?? '') === 'scheduled' ? 'live' : (string)$match['status'];

$stmt = $pdo->prepare("
    UPDATE matches
    SET score_a = ?,
        score_b = ?,
        yellow_cards_a = ?,
        yellow_cards_b = ?,
        red_cards_a = ?,
        red_cards_b = ?,
        status = ?
    WHERE id = ?
");
$stmt->execute([$scoreA, $scoreB, $yellowCardsA, $yellowCardsB, $redCardsA, $redCardsB, $nextStatus, $id]);

if (liveUpdateWantsJson()) {
    liveUpdateJson([
        'ok' => true,
        'message' => 'Atualizado.',
        'values' => [
            'score_a' => $scoreA,
            'score_b' => $scoreB,
            'yellow_cards_a' => $yellowCardsA,
            'yellow_cards_b' => $yellowCardsB,
            'red_cards_a' => $redCardsA,
            'red_cards_b' => $redCardsB,
            'status' => $nextStatus,
        ],
    ]);
}

if ($action === 'adjust') {
    unset($_SESSION['flash']);
    redirect(PROJECT_URL . '/admin/partidas/attendance.php?id=' . $id);
}

unset($_SESSION['flash']);
redirect(PROJECT_URL . '/admin/partidas/index.php?notice=score_saved');
