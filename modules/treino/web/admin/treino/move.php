<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

function trainingMoveDefaultSlots(): array
{
    return [
        'GO' => 1,
        'ZC1' => 1,
        'ZC2' => 1,
        'LE' => 1,
        'LD' => 1,
        'VOL1' => 1,
        'MAT1' => 1,
        'PTE' => 1,
        'PTD' => 1,
        'CA1' => 1,
        'CA2' => 1,
    ];
}

function trainingMoveEnsureSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS training_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            custom_slots_json JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("ALTER TABLE training_roster MODIFY COLUMN status ENUM('field','reserve','inactive') NOT NULL DEFAULT 'reserve'");
}

function trainingMoveSettings(PDO $pdo, array $team): array
{
    $settings = $pdo->query("SELECT * FROM training_settings WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        $teamSlots = json_decode((string)($team['custom_slots_json'] ?? ''), true);
        $teamSlots = is_array($teamSlots) && $teamSlots ? $teamSlots : trainingMoveDefaultSlots();
        $stmt = $pdo->prepare("INSERT INTO training_settings (id, custom_slots_json) VALUES (1, ?)");
        $stmt->execute([json_encode($teamSlots, JSON_UNESCAPED_UNICODE)]);
        return ['custom_slots_json' => json_encode($teamSlots, JSON_UNESCAPED_UNICODE)];
    }

    return $settings;
}

function trainingMoveNormalizeCustomSlots(array $customSlots): array
{
    foreach (['ZC', 'VOL', 'MAT', 'CA'] as $prefix) {
        if (!empty($customSlots[$prefix]) && empty($customSlots[$prefix . '1']) && empty($customSlots[$prefix . '2'])) {
            $customSlots[$prefix . '1'] = 1;
            if ((int)$customSlots[$prefix] > 1) {
                $customSlots[$prefix . '2'] = 1;
            }
        }
    }

    return $customSlots;
}

function trainingMoveFieldLimits(array $team): array
{
    $customSlots = json_decode((string)($team['custom_slots_json'] ?? ''), true);
    $customSlots = is_array($customSlots) ? trainingMoveNormalizeCustomSlots($customSlots) : [];

    if (!$customSlots) {
        return ['goleiro' => 1, 'zagueiro' => 2, 'lateral' => 2, 'meia' => 3, 'ponta' => 2, 'atacante' => 1];
    }

    $map = [
        'GO' => 'goleiro',
        'ZC1' => 'zagueiro',
        'ZC2' => 'zagueiro',
        'LE' => 'lateral',
        'LD' => 'lateral',
        'VOL1' => 'meia',
        'VOL2' => 'meia',
        'MAT1' => 'meia',
        'MAT2' => 'meia',
        'PTE' => 'ponta',
        'PTD' => 'ponta',
        'CA1' => 'atacante',
        'CA2' => 'atacante',
    ];
    $limits = [];

    foreach ($map as $code => $group) {
        if (!empty($customSlots[$code])) {
            $limits[$group] = ($limits[$group] ?? 0) + 1;
        }
    }

    return $limits ?: ['goleiro' => 1, 'zagueiro' => 2, 'lateral' => 2, 'meia' => 3, 'ponta' => 2, 'atacante' => 1];
}

function trainingMovePositionKey(?string $position, ?string $groupKey = null): string
{
    if ($groupKey !== null && $groupKey !== '') {
        return $groupKey;
    }

    $position = strtolower((string)$position);

    if (str_contains($position, 'goleiro')) return 'goleiro';
    if (str_contains($position, 'zagueiro')) return 'zagueiro';
    if (str_contains($position, 'lateral')) return 'lateral';
    if (str_contains($position, 'ponta')) return 'ponta';
    if (str_contains($position, 'volante') || str_contains($position, 'meia')) return 'meia';
    if (str_contains($position, 'atacante')) return 'atacante';

    return '';
}

function trainingMoveCandidateSlots(array $player, array $limits): array
{
    $code = (string)($player['position_code'] ?? '');
    $groupKey = trainingMovePositionKey($player['position_name'] ?? '', $player['group_key'] ?? null);

    $slots = match (true) {
        str_starts_with($code, 'GO') => [['goleiro', 0]],
        str_starts_with($code, 'ZC') => [['zagueiro', str_starts_with($code, 'ZC2') ? 1 : 0], ['zagueiro', 0], ['zagueiro', 1]],
        str_starts_with($code, 'LE') => [['lateral', 0], ['lateral', 1]],
        str_starts_with($code, 'LD') => [['lateral', 1], ['lateral', 0]],
        str_starts_with($code, 'VOL') => [['meia', 0], ['meia', 1], ['meia', 2]],
        str_starts_with($code, 'MAT') => [['meia', 1], ['meia', 2], ['meia', 0]],
        str_starts_with($code, 'PTE') => [['ponta', 0], ['ponta', 1]],
        str_starts_with($code, 'PTD') => [['ponta', 1], ['ponta', 0]],
        str_starts_with($code, 'CA') => [['atacante', 0]],
        default => [],
    };

    if (!$slots && isset($limits[$groupKey])) {
        for ($i = 0; $i < $limits[$groupKey]; $i++) {
            $slots[] = [$groupKey, $i];
        }
    }

    $unique = [];
    foreach ($slots as $slot) {
        if (isset($limits[$slot[0]]) && $slot[1] < $limits[$slot[0]]) {
            $unique[$slot[0] . ':' . $slot[1]] = $slot;
        }
    }

    return array_values($unique);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();
trainingMoveEnsureSchema($pdo);

$id = (int)($_GET['id'] ?? 0);
$target = (string)($_POST['target'] ?? 'reserve');
$target = $target === 'field' ? 'field' : 'reserve';

if ($id <= 0) {
    flash('error', 'Jogador do treino nao encontrado.');
    redirect(PROJECT_URL . '/admin/treino/index.php');
}

$stmt = $pdo->prepare("
    SELECT
        tr.*,
        pp.name AS position_name,
        pp.code AS position_code,
        pp.group_key
    FROM training_roster tr
    INNER JOIN players p ON p.id = tr.player_id
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    WHERE tr.id = ?
      AND p.status = 'active'
    LIMIT 1
");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    flash('error', 'Jogador ativo nao encontrado no treino.');
    redirect(PROJECT_URL . '/admin/treino/index.php');
}

if ($target === 'reserve') {
    $stmt = $pdo->prepare("
        UPDATE training_roster
        SET status = 'reserve', slot_group = NULL, slot_index = NULL
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    flash('success', 'Jogador movido para a reserva do treino.');
    redirect(PROJECT_URL . '/admin/treino/index.php');
}

$teamKey = (string)($row['team_key'] ?? 'time_1');
$teamKey = $teamKey === 'time_2' ? 'time_2' : 'time_1';
$team = $pdo->query("SELECT * FROM team_profile WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$trainingSettings = trainingMoveSettings($pdo, $team);
$limits = trainingMoveFieldLimits($trainingSettings);
$candidateSlots = trainingMoveCandidateSlots($row, $limits);

$stmt = $pdo->prepare("
    SELECT slot_group, slot_index
    FROM training_roster
    WHERE team_key = ?
      AND status = 'field'
      AND id <> ?
");
$stmt->execute([$teamKey, $id]);

$occupied = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $occupiedRow) {
    if (($occupiedRow['slot_group'] ?? '') !== '' && $occupiedRow['slot_index'] !== null) {
        $occupied[(string)$occupiedRow['slot_group'] . ':' . (int)$occupiedRow['slot_index']] = true;
    }
}

$slotGroup = null;
$slotIndex = null;

foreach ($candidateSlots as [$candidateGroup, $candidateIndex]) {
    $key = $candidateGroup . ':' . $candidateIndex;
    if (!isset($occupied[$key])) {
        $slotGroup = $candidateGroup;
        $slotIndex = (int)$candidateIndex;
        break;
    }
}

if ($slotGroup === null) {
    flash('error', 'Nao existe vaga livre para esta posicao no campo.');
    redirect(PROJECT_URL . '/admin/treino/index.php');
}

$stmt = $pdo->prepare("
    UPDATE training_roster
    SET status = 'field', slot_group = ?, slot_index = ?
    WHERE id = ?
");
$stmt->execute([$slotGroup, $slotIndex, $id]);

flash('success', 'Jogador escalado no campo do treino.');
redirect(PROJECT_URL . '/admin/treino/index.php');
