<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/lineup_helpers.php';

requireProjectAdmin();
matchLineupEnsureSchema($pdo);

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT m.*, c.name AS competition_name, c.type AS competition_type, c.context AS competition_context
    FROM matches m
    LEFT JOIN competitions c ON c.id = m.competition_id
    WHERE m.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    http_response_code(404);
    exit('Partida nao encontrada.');
}

$statusLabels = [
    'scheduled' => 'Agendada',
    'live' => 'Em andamento',
    'finished' => 'Finalizada',
    'canceled' => 'Cancelada',
];

function matchShowStatusBadge(?string $status): string
{
    return match ($status) {
        'live' => 'c-badge--success',
        'scheduled' => 'c-badge--info',
        'finished' => 'c-badge--neutral',
        'canceled' => 'c-badge--danger',
        default => 'c-badge--warning',
    };
}

function matchShowLineupModeLabel(?string $mode): string
{
    return matchLineupModeLabel($mode);
}

function matchShowTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);

    return (bool)$stmt->fetchColumn();
}

function matchShowLoadTeamRosterLineup(PDO $pdo, array $slots, array $fieldSetup): array
{
    if (!matchShowTableExists($pdo, 'team_roster')) {
        return [['team_1' => [], 'team_2' => []], ['team_1' => [], 'team_2' => []]];
    }

    $stmt = $pdo->query("
        SELECT
            tr.*,
            p.id AS player_id,
            COALESCE(NULLIF(p.nickname, ''), LEFT(SUBSTRING_INDEX(p.name, ' ', 1), 14)) AS player_name,
            pp.name AS position_name,
            pp.code AS position_code,
            pp.group_key AS group_key,
            COALESCE(p.avatar, u.avatar) AS avatar
        FROM team_roster tr
        INNER JOIN players p ON p.id = tr.player_id
        LEFT JOIN player_positions pp ON pp.id = p.position_id
        LEFT JOIN project_users u ON u.id = p.user_id
        WHERE tr.status = 'active'
          AND p.status = 'active'
        ORDER BY
            CASE pp.group_key
                WHEN 'goleiro' THEN 1
                WHEN 'zagueiro' THEN 2
                WHEN 'lateral' THEN 3
                WHEN 'meia' THEN 4
                WHEN 'ponta' THEN 5
                WHEN 'atacante' THEN 6
                ELSE 7
            END,
            tr.slot_group IS NULL,
            tr.slot_group,
            tr.slot_index,
            p.name
    ");

    $fieldPlayers = ['team_1' => [], 'team_2' => []];
    $reservePlayers = ['team_1' => [], 'team_2' => []];
    $occupied = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $member) {
        $slotGroup = trim((string)($member['slot_group'] ?? ''));
        $slotIndex = $member['slot_index'] !== null ? (int)$member['slot_index'] : null;
        $slot = null;

        if ($slotGroup !== '' && $slotIndex !== null && isset($slots[$slotGroup][$slotIndex])) {
            $slotKey = $slotGroup . ':' . $slotIndex;
            if (!isset($occupied[$slotKey])) {
                $slot = $slots[$slotGroup][$slotIndex];
                $occupied[$slotKey] = true;
            }
        }

        if ($slot === null) {
            foreach (matchLineupCandidateSlotsForSetup($member, $fieldSetup) as $candidate) {
                [$candidateGroup, $candidateIndex] = $candidate;
                $slotKey = $candidateGroup . ':' . $candidateIndex;
                if (!isset($occupied[$slotKey]) && isset($slots[$candidateGroup][$candidateIndex])) {
                    $slot = $slots[$candidateGroup][$candidateIndex];
                    $occupied[$slotKey] = true;
                    break;
                }
            }
        }

        if ($slot === null) {
            $reservePlayers['team_1'][] = $member + ['lineup_team' => 'team_1'];
            continue;
        }

        $fieldPlayers['team_1'][] = $member + [
            'x' => $slot['x'],
            'y' => $slot['y'],
            'lineup_team' => 'team_1',
        ];
    }

    return [$fieldPlayers, $reservePlayers];
}

function matchShowRenderField(array $slots, array $fieldPlayers, string $emptyText = 'Nenhum titular definido.'): void
{
    $occupiedSlots = [];
    foreach ($fieldPlayers as $player) {
        $slotGroup = trim((string)($player['slot_group'] ?? ''));
        $slotIndex = $player['slot_index'] ?? null;
        if ($slotGroup !== '' && $slotIndex !== null) {
            $occupiedSlots[$slotGroup . ':' . (int)$slotIndex] = true;
        }
    }

    ?>
    <div class="c-match-field">
        <span class="c-match-field-line c-match-field-mid"></span>
        <span class="c-match-field-circle c-match-field-circle-center"></span>
        <span class="c-match-field-spot c-match-field-spot-center"></span>
        <span class="c-match-field-spot c-match-field-spot-top"></span>
        <span class="c-match-field-spot c-match-field-spot-bottom"></span>
        <span class="c-match-field-box c-match-field-box-top"></span>
        <span class="c-match-field-box c-match-field-box-bottom"></span>
        <span class="c-match-field-goal c-match-field-goal-top"></span>
        <span class="c-match-field-goal c-match-field-goal-bottom"></span>

        <?php foreach ($slots as $positionKey => $positionSlots): ?>
            <?php foreach ($positionSlots as $slotIndex => $slot): ?>
                <?php if (isset($occupiedSlots[$positionKey . ':' . (int)$slotIndex])): ?>
                    <?php continue; ?>
                <?php endif; ?>
                <span class="c-match-field-slot" style="left:<?= (int)$slot['x'] ?>%;top:<?= (int)$slot['y'] ?>%;">
                    <?= htmlspecialchars(matchLineupPositionDisplay((string)($slot['label'] ?? match ($positionKey) {
                        'goleiro' => 'GO',
                        'zagueiro' => 'ZC',
                        'lateral' => $slotIndex === 0 ? 'LE' : 'LD',
                        'meia' => $slotIndex === 0 ? 'VOL' : 'MAT',
                        'ponta' => $slotIndex === 0 ? 'PTE' : 'PTD',
                        'atacante' => 'CA',
                        default => '',
                    }))) ?>
                </span>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <?php foreach ($fieldPlayers as $player): ?>
            <div class="c-match-field-player" style="left:<?= (int)$player['x'] ?>%;top:<?= (int)$player['y'] ?>%;">
                <?= matchLineupAvatar($player['avatar'] ?? null, $player['player_name'] ?? '-', 'c-match-field-avatar') ?>
                <strong><?= htmlspecialchars((string)($player['player_name'] ?? '-')) ?></strong>
                <small><?= htmlspecialchars(matchLineupPositionDisplay($player['position_code'] ?? null)) ?></small>
            </div>
        <?php endforeach; ?>

        <?php if (empty($fieldPlayers)): ?>
            <div class="c-match-field-empty"><?= htmlspecialchars($emptyText) ?></div>
        <?php endif; ?>
    </div>
    <?php
}

$backUrl = !empty($match['competition_id'])
    ? PROJECT_URL . '/admin/competicoes/view.php?id=' . (int)$match['competition_id']
    : PROJECT_URL . '/admin/partidas/index.php';

if (($match['status'] ?? '') === 'finished' && empty($match['field_type_snapshot'])) {
    matchLineupSaveFieldSnapshot($pdo, (int)$match['id']);
    $stmt->execute([$id]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);
}

$fieldSetup = matchLineupFieldSetupForMatch($pdo, $match);
$slots = $fieldSetup['slots'];
$isInternalMatch = ($match['competition_context'] ?? '') === 'internal';
$isFriendlyMatch = ($match['competition_type'] ?? '') === 'friendly';
$friendlyCardsEnabled = function_exists('getSetting')
    ? getSetting('friendly_cards_enabled', '1') !== '0'
    : true;

$lineupStmt = $pdo->prepare("
    SELECT ml.*, COALESCE(NULLIF(p.nickname, ''), LEFT(SUBSTRING_INDEX(p.name, ' ', 1), 14)) AS player_name, COALESCE(opp.name, pp.name) AS position_name, COALESCE(opp.code, pp.code) AS position_code, COALESCE(opp.group_key, pp.group_key) AS group_key, COALESCE(p.avatar, u.avatar) AS avatar
    FROM match_lineup ml
    INNER JOIN players p ON p.id = ml.player_id
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    LEFT JOIN player_positions opp ON opp.id = ml.override_position_id
    LEFT JOIN project_users u ON u.id = p.user_id
    WHERE ml.match_id = ?
      AND ml.status = 'starter'
    ORDER BY COALESCE(ml.lineup_team, 'team_1') ASC, ml.slot_group ASC, ml.slot_index ASC, p.name ASC
");
$lineupStmt->execute([$id]);
$lineup = $lineupStmt->fetchAll(PDO::FETCH_ASSOC);

$fieldPlayersByTeam = ['team_1' => [], 'team_2' => []];
$reservePlayersByTeam = ['team_1' => [], 'team_2' => []];
foreach ($lineup as $member) {
    $lineupTeam = in_array($member['lineup_team'] ?? '', ['team_1', 'team_2'], true) ? (string)$member['lineup_team'] : 'team_1';
    if (isset($slots[$member['slot_group']][(int)$member['slot_index']])) {
        $slot = $slots[$member['slot_group']][(int)$member['slot_index']];
        $fieldPlayersByTeam[$lineupTeam][] = $member + ['x' => $slot['x'], 'y' => $slot['y'], 'lineup_team' => $lineupTeam];
    }
}

$reserveStmt = $pdo->prepare("
    SELECT ml.*, COALESCE(NULLIF(p.nickname, ''), LEFT(SUBSTRING_INDEX(p.name, ' ', 1), 14)) AS player_name, COALESCE(opp.name, pp.name) AS position_name, COALESCE(opp.code, pp.code) AS position_code, COALESCE(opp.group_key, pp.group_key) AS group_key, COALESCE(p.avatar, u.avatar) AS avatar
    FROM match_lineup ml
    INNER JOIN players p ON p.id = ml.player_id
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    LEFT JOIN player_positions opp ON opp.id = ml.override_position_id
    LEFT JOIN project_users u ON u.id = p.user_id
    WHERE ml.match_id = ?
      AND ml.status = 'reserve'
    ORDER BY COALESCE(ml.lineup_team, 'team_1') ASC, pp.sort_order ASC, p.name ASC
");
$reserveStmt->execute([$id]);
$reserveLineup = $reserveStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($reserveLineup as $member) {
    $lineupTeam = in_array($member['lineup_team'] ?? '', ['team_1', 'team_2'], true) ? (string)$member['lineup_team'] : 'team_1';
    $reservePlayersByTeam[$lineupTeam][] = $member + ['lineup_team' => $lineupTeam];
}

if ($isFriendlyMatch && !$isInternalMatch && ($match['lineup_mode'] ?? 'team_roster') === 'team_roster' && empty($fieldPlayersByTeam['team_1'])) {
    matchLineupSyncTeamRosterSnapshot($pdo, (int)$match['id']);
    $lineupStmt->execute([$id]);
    $lineup = $lineupStmt->fetchAll(PDO::FETCH_ASSOC);

    $fieldPlayersByTeam = ['team_1' => [], 'team_2' => []];
    $reservePlayersByTeam = ['team_1' => [], 'team_2' => []];

    foreach ($lineup as $member) {
        $lineupTeam = in_array($member['lineup_team'] ?? '', ['team_1', 'team_2'], true) ? (string)$member['lineup_team'] : 'team_1';
        if (isset($slots[$member['slot_group']][(int)$member['slot_index']])) {
            $slot = $slots[$member['slot_group']][(int)$member['slot_index']];
            $fieldPlayersByTeam[$lineupTeam][] = $member + ['x' => $slot['x'], 'y' => $slot['y'], 'lineup_team' => $lineupTeam];
        }
    }

    $reserveStmt->execute([$id]);
    $reserveLineup = $reserveStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reserveLineup as $member) {
        $lineupTeam = in_array($member['lineup_team'] ?? '', ['team_1', 'team_2'], true) ? (string)$member['lineup_team'] : 'team_1';
        $reservePlayersByTeam[$lineupTeam][] = $member + ['lineup_team' => $lineupTeam];
    }
}

$title = 'Ver Partida';

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title"><?= htmlspecialchars($match['participant_a'] . ' x ' . ($match['participant_b'] ?? '-')) ?></h1>
            <p class="c-page-subtitle"><?= htmlspecialchars((string)($match['competition_name'] ?? 'Partida avulsa')) ?></p>
        </div>

        <div>
            <a href="<?= PROJECT_URL ?>/admin/partidas/edit.php?id=<?= (int)$match['id'] ?>" class="c-btn-secondary">Editar</a>
            <?php if (!$isFriendlyMatch && ($match['status'] ?? '') !== 'finished'): ?>
                <a href="<?= PROJECT_URL ?>/admin/partidas/lineup.php?id=<?= (int)$match['id'] ?>" class="c-btn-secondary">Escalação</a>
            <?php endif; ?>
            <form action="<?= PROJECT_URL ?>/admin/partidas/delete.php?id=<?= (int)$match['id'] ?>" method="POST" style="display:inline;" onsubmit="return confirm('Apagar esta partida?');">
                <?= csrf_field(); ?>
                <button class="c-btn-secondary">Apagar</button>
            </form>
            <a href="<?= $backUrl ?>" class="c-btn-secondary">Voltar</a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-match-show-layout <?= $isInternalMatch ? 'c-match-show-layout--internal' : '' ?>">
            <div class="c-match-show-details">
                <div class="c-match-show-box c-match-show-score">
                    <span>Placar</span>
                    <strong><?= htmlspecialchars($match['participant_a']) ?></strong>
                    <div>
                        <?php if ($match['score_a'] !== null && $match['score_b'] !== null): ?>
                            <?= (int)$match['score_a'] ?> <small>x</small> <?= (int)$match['score_b'] ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </div>
                    <strong><?= htmlspecialchars((string)($match['participant_b'] ?? '-')) ?></strong>
                </div>
                <div class="c-match-show-box">
                    <span>Competição</span>
                    <strong><?= htmlspecialchars((string)($match['competition_name'] ?? '-')) ?></strong>
                </div>
                <div class="c-match-show-box">
                    <span>Status</span>
                    <strong><span class="c-badge <?= matchShowStatusBadge($match['status'] ?? null) ?>"><?= htmlspecialchars($statusLabels[$match['status']] ?? $match['status']) ?></span></strong>
                </div>
                <?php if (!$isInternalMatch && (!$isFriendlyMatch || $friendlyCardsEnabled)): ?>
                    <div class="c-match-show-box c-match-card-summary">
                        <span>Cartões recebidos</span>
                        <strong>
                            <span class="c-match-card-count c-match-card-count--yellow"><?= (int)($match['yellow_cards_a'] ?? 0) ?> amarelo(s)</span>
                            <span class="c-match-card-count c-match-card-count--red"><?= (int)($match['red_cards_a'] ?? 0) ?> vermelho(s)</span>
                        </strong>
                    </div>
                <?php endif; ?>
                <div class="c-match-show-box c-match-show-meta">
                    <span>Data</span>
                    <strong><?= htmlspecialchars((string)($match['match_date'] ?? '-')) ?></strong>
                </div>
                <div class="c-match-show-box c-match-show-meta">
                    <span>Local</span>
                    <strong><?= htmlspecialchars((string)($match['venue'] ?? '-')) ?></strong>
                </div>
                <div class="c-match-show-box c-match-show-meta">
                    <span>Modo de escalação</span>
                    <strong><?= htmlspecialchars(matchShowLineupModeLabel($match['lineup_mode'] ?? null)) ?></strong>
                </div>

                <?php if (!$isInternalMatch): ?>
                    <div class="c-match-reserves-panel">
                        <h3>Reservas da partida</h3>
                        <?php if (empty($reservePlayersByTeam['team_1'])): ?>
                            <p>Nenhum reserva definido.</p>
                        <?php else: ?>
                            <div class="c-match-reserves-list">
                                <?php foreach ($reservePlayersByTeam['team_1'] as $player): ?>
                                    <div class="c-match-reserve-player">
                                        <?= matchLineupAvatar($player['avatar'] ?? null, $player['player_name'] ?? '-', 'c-match-reserve-avatar') ?>
                                        <span><?= htmlspecialchars((string)($player['player_name'] ?? '-')) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="c-match-lineup-card">
                <?php if ($isInternalMatch): ?>
                    <div class="c-match-two-fields">
                        <div>
                            <h4>Time 1</h4>
                            <?php matchShowRenderField($slots, $fieldPlayersByTeam['team_1']); ?>
                        </div>
                        <div>
                            <h4>Time 2</h4>
                            <?php matchShowRenderField($slots, $fieldPlayersByTeam['team_2']); ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php matchShowRenderField($slots, $fieldPlayersByTeam['team_1']); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.c-match-show-layout {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    align-items: start;
}

.c-match-show-layout--internal {
    grid-template-columns: minmax(300px, .78fr) minmax(0, 1.22fr);
}

.c-match-show-details {
    display: grid;
    grid-column: span 2;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.c-match-show-layout--internal .c-match-show-details,
.c-match-show-layout--internal .c-match-lineup-card {
    grid-column: auto;
}

.c-match-show-box {
    border: 1px solid var(--border-color);
    background: color-mix(in srgb, var(--bg-card) 88%, var(--bg-hover));
    padding: 11px;
    min-width: 0;
}

.c-match-show-box span {
    display: block;
    margin-bottom: 6px;
    color: var(--text-secondary);
    font-size: 12px;
    font-weight: 700;
}

.c-match-show-box strong {
    display: block;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
}

.c-match-show-score {
    grid-row: span 2;
}

.c-match-show-score > div {
    margin: 8px 0;
    font-size: 28px;
    font-weight: 900;
    line-height: 1;
}

.c-match-show-score small {
    color: var(--text-secondary);
    font-size: 14px;
}

.c-match-card-summary {
    grid-column: auto;
}

.c-match-card-summary strong {
    display: grid;
    grid-template-columns: 1fr;
    gap: 6px;
}

.c-match-show-meta {
    display: grid;
    grid-template-columns: 1fr;
}

.c-match-card-count {
    display: grid;
    min-height: 28px;
    place-items: center;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 900;
}

.c-match-card-count--yellow {
    background: color-mix(in srgb, var(--warning-color, #f59e0b) 18%, transparent);
    color: var(--warning-color, #f59e0b);
}

.c-match-card-count--red {
    background: color-mix(in srgb, var(--danger-color, #ef4444) 18%, transparent);
    color: var(--danger-color, #ef4444);
}

.c-match-lineup-card h3 {
    margin-bottom: 10px;
}

.c-match-lineup-card {
    grid-column: 3;
    min-width: 0;
}

.c-match-reserves-panel {
    grid-column: span 2;
    padding: 0;
}

.c-match-reserves-panel h3 {
    margin: 0 0 8px;
    font-size: 14px;
}

.c-match-reserves-panel p {
    color: var(--text-secondary);
    margin: 0;
}

.c-match-reserves-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.c-match-reserve-player {
    display: grid;
    justify-items: center;
    gap: 4px;
    min-width: 54px;
    text-align: center;
}

.c-match-reserve-avatar {
    display: grid;
    width: 38px;
    height: 38px;
    place-items: center;
    border-radius: 999px;
    background:
        radial-gradient(circle at 35% 28%, rgba(255,255,255,.95) 0 7px, transparent 8px),
        linear-gradient(135deg, #f3d4bf 0 44%, #1f7a3f 45% 100%);
    border: 2px solid rgba(255,255,255,.86);
    box-shadow: 0 2px 5px rgba(0,0,0,.34);
    color: #08111f;
    font-size: 10px;
    font-weight: 900;
    overflow: hidden;
}

img.c-match-reserve-avatar {
    object-fit: cover;
}

.c-match-reserve-player span {
    max-width: 64px;
    overflow: hidden;
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.c-match-two-fields {
    display: grid;
    gap: 12px;
}

.c-match-show-layout--internal .c-match-two-fields {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: start;
    gap: 14px;
}

.c-match-two-fields h4 {
    margin: 0 0 8px;
    font-size: 12px;
}

.c-match-field {
    position: relative;
    width: min(100%, 320px);
    aspect-ratio: 3 / 4;
    margin: 0;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,.28);
    background-color: #146b3a;
    background-image:
        radial-gradient(circle at 50% 50%, rgba(255,255,255,.08) 0 1px, transparent 2px),
        linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px),
        linear-gradient(180deg, rgba(255,255,255,.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.035) 50%, transparent 50%);
    background-size: 36px 36px, 36px 36px, 36px 36px, 50% 100%;
}

.c-match-field-line,
.c-match-field-circle,
.c-match-field-spot,
.c-match-field-box,
.c-match-field-goal {
    position: absolute;
    pointer-events: none;
}

.c-match-field-mid {
    left: 0;
    top: 50%;
    width: 100%;
    height: 1px;
    background: rgba(255,255,255,.55);
}

.c-match-field-circle {
    width: 24%;
    aspect-ratio: 1;
    border: 1px solid rgba(255,255,255,.45);
    border-radius: 999px;
    transform: translate(-50%, -50%);
}

.c-match-field-circle-center {
    left: 50%;
    top: 50%;
}

.c-match-field-spot {
    width: 5px;
    height: 5px;
    border-radius: 999px;
    background: rgba(255,255,255,.65);
    transform: translate(-50%, -50%);
}

.c-match-field-spot-center { left: 50%; top: 50%; }
.c-match-field-spot-top { left: 50%; top: 22%; }
.c-match-field-spot-bottom { left: 50%; top: 78%; }

.c-match-field-box {
    left: 28%;
    width: 44%;
    height: 18%;
    border: 1px solid rgba(255,255,255,.55);
}

.c-match-field-box-top {
    top: 0;
    border-top: 0;
}

.c-match-field-box-bottom {
    bottom: 0;
    border-bottom: 0;
}

.c-match-field-goal {
    left: 42%;
    width: 16%;
    height: 5%;
    border: 1px solid rgba(255,255,255,.5);
    background: rgba(255,255,255,.04);
}

.c-match-field-goal-top {
    top: 0;
    border-top: 0;
}

.c-match-field-goal-bottom {
    bottom: 0;
    border-bottom: 0;
}

.c-match-field-slot {
    position: absolute;
    display: grid;
    width: 38px;
    height: 38px;
    place-items: center;
    border: 1px dashed rgba(255,255,255,.32);
    border-radius: 999px;
    color: rgba(255,255,255,.55);
    font-size: 10px;
    font-weight: 800;
    transform: translate(-50%, -50%);
}

.c-match-field-player {
    position: absolute;
    display: grid;
    justify-items: center;
    min-width: 72px;
    color: #fff;
    text-align: center;
    text-shadow: 0 1px 3px rgba(0,0,0,.9);
    transform: translate(-50%, -50%);
    z-index: 3;
}

.c-match-field-avatar {
    display: grid;
    width: 38px;
    height: 38px;
    place-items: center;
    border-radius: 999px;
    background:
        radial-gradient(circle at 35% 28%, rgba(255,255,255,.95) 0 7px, transparent 8px),
        linear-gradient(135deg, #f3d4bf 0 44%, #1f7a3f 45% 100%);
    border: 2px solid rgba(255,255,255,.92);
    box-shadow: 0 2px 5px rgba(0,0,0,.45);
    color: #08111f;
    font-size: 11px;
    font-weight: 900;
    overflow: hidden;
}

img.c-match-field-avatar {
    object-fit: cover;
}

.c-match-field-player strong,
.c-match-field-player small {
    display: block;
    max-width: 70px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.c-match-field-player strong {
    margin-top: 2px;
    font-size: 10px;
}

.c-match-field-player small {
    font-size: 9px;
    opacity: .86;
}

.c-match-field-empty {
    position: absolute;
    inset: auto 12px 12px;
    color: rgba(255,255,255,.74);
    font-size: 11px;
    text-align: center;
}

@media (max-width: 980px) {
    .c-match-show-layout,
    .c-match-show-layout--internal {
        grid-template-columns: 1fr;
    }

    .c-match-show-details,
    .c-match-lineup-card,
    .c-match-reserves-panel {
        grid-column: auto;
    }
}

@media (max-width: 700px) {
    .c-match-show-details {
        grid-template-columns: 1fr;
    }

    .c-match-card-summary {
        grid-column: auto;
    }

    .c-match-field {
        width: 100%;
        max-width: none;
    }

    .c-match-field-avatar {
        width: 48px;
        height: 48px;
        font-size: 12px;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
