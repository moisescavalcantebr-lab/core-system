<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/../partidas/lineup_helpers.php';

requireProjectAuth();
matchLineupEnsureSchema($pdo);

$user = projectUser();
$matchId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id FROM players WHERE user_id = ? AND status = 'active' LIMIT 1");
$stmt->execute([(int)$user['id']]);
$playerId = (int)($stmt->fetchColumn() ?: 0);

if ($playerId <= 0) {
    http_response_code(403);
    exit('Seu usuario ainda nao esta vinculado a um jogador.');
}

$stmt = $pdo->prepare("
    SELECT m.*, c.name AS competition_name, c.context AS competition_context
    FROM matches m
    LEFT JOIN competitions c ON c.id = m.competition_id
    WHERE m.id = ?
      AND m.status IN ('scheduled','live','finished')
    LIMIT 1
");
$stmt->execute([$matchId]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    http_response_code(404);
    exit('Partida nao encontrada.');
}

$team = $pdo->query("SELECT team_type, custom_slots_json FROM team_profile WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$teamType = (string)($team['team_type'] ?? 'field');
$fieldSetup = matchLineupFieldSetup($teamType, $team['custom_slots_json'] ?? null);
$slots = $fieldSetup['slots'];
$isInternalAutomatic = ($match['competition_context'] ?? '') === 'internal';

if ($isInternalAutomatic) {
    matchLineupAutoAssignInternal($pdo, $matchId);
}

$lineupStmt = $pdo->prepare("
    SELECT ml.*, COALESCE(NULLIF(p.nickname, ''), LEFT(SUBSTRING_INDEX(p.name, ' ', 1), 14)) AS player_name, COALESCE(opp.name, pp.name) AS position_name, COALESCE(opp.code, pp.code) AS position_code, COALESCE(opp.group_key, pp.group_key) AS group_key, u.avatar
    FROM match_lineup ml
    INNER JOIN players p ON p.id = ml.player_id
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    LEFT JOIN player_positions opp ON opp.id = ml.override_position_id
    LEFT JOIN project_users u ON u.id = p.user_id
    WHERE ml.match_id = ?
    ORDER BY CASE ml.status WHEN 'starter' THEN 0 ELSE 1 END, ml.id ASC
");
$lineupStmt->execute([$matchId]);
$lineup = $lineupStmt->fetchAll(PDO::FETCH_ASSOC);

$confirmed = $pdo->prepare("
    SELECT p.id, COALESCE(NULLIF(p.nickname, ''), LEFT(SUBSTRING_INDEX(p.name, ' ', 1), 14)) AS name, pp.name AS position_name, pp.code AS position_code, pp.group_key, u.avatar
    FROM match_confirmations mc
    INNER JOIN players p ON p.id = mc.player_id
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    LEFT JOIN project_users u ON u.id = p.user_id
    LEFT JOIN match_lineup ml ON ml.match_id = mc.match_id AND ml.player_id = mc.player_id
    WHERE mc.match_id = ?
      AND mc.status = 'confirmed'
      AND p.status = 'active'
      AND ml.id IS NULL
    ORDER BY
        CASE WHEN ? = 'arrival_order' THEN mc.confirmed_at END ASC,
        CASE WHEN ? <> 'arrival_order' THEN pp.sort_order END ASC,
        p.name ASC
");
$confirmed->execute([$matchId, (string)($match['lineup_mode'] ?? 'team_roster'), (string)($match['lineup_mode'] ?? 'team_roster')]);
$availablePlayers = $confirmed->fetchAll(PDO::FETCH_ASSOC);

$fieldPlayers = [];
$reservePlayers = [];
$fieldPlayersByTeam = ['team_1' => [], 'team_2' => []];
$reservePlayersByTeam = ['team_1' => [], 'team_2' => []];

foreach ($lineup as $member) {
    $lineupTeam = in_array($member['lineup_team'] ?? '', ['team_1', 'team_2'], true) ? (string)$member['lineup_team'] : 'team_1';

    if (($member['status'] ?? '') === 'starter' && isset($slots[$member['slot_group']][(int)$member['slot_index']])) {
        $slot = $slots[$member['slot_group']][(int)$member['slot_index']];
        $fieldMember = $member + ['x' => $slot['x'], 'y' => $slot['y'], 'lineup_team' => $lineupTeam];
        $fieldPlayers[] = $fieldMember;
        $fieldPlayersByTeam[$lineupTeam][] = $fieldMember;
    } else {
        $reserveMember = $member + ['lineup_team' => $lineupTeam];
        $reservePlayers[] = $reserveMember;
        $reservePlayersByTeam[$lineupTeam][] = $reserveMember;
    }
}

$cardPlayers = [];

foreach ($availablePlayers as $player) {
    $cardPlayers[] = [
        'name' => $player['name'] ?? '-',
        'avatar' => $player['avatar'] ?? null,
        'position_code' => $player['position_code'] ?? null,
    ];
}

foreach ($reservePlayers as $player) {
    $cardPlayers[] = [
        'name' => $player['player_name'] ?? '-',
        'avatar' => $player['avatar'] ?? null,
        'position_code' => $player['position_code'] ?? null,
    ];
}

if (!function_exists('renderPlayerMatchLineupField')) {
    function renderPlayerMatchLineupField(array $slots, array $fieldPlayers): void
    {
        ?>
        <div class="c-lineup-field">
            <span class="c-field-line c-field-mid"></span>
            <span class="c-field-circle c-field-circle-center"></span>
            <span class="c-field-spot c-field-spot-center"></span>
            <span class="c-field-spot c-field-spot-top"></span>
            <span class="c-field-spot c-field-spot-bottom"></span>
            <span class="c-field-box c-field-box-top"></span>
            <span class="c-field-box c-field-box-bottom"></span>
            <span class="c-field-goal c-field-goal-top"></span>
            <span class="c-field-goal c-field-goal-bottom"></span>
            <?php foreach ($slots as $positionKey => $positionSlots): ?>
                <?php foreach ($positionSlots as $slotIndex => $slot): ?>
                    <span class="c-lineup-position-slot" style="left:<?= (int)$slot['x'] ?>%;top:<?= (int)$slot['y'] ?>%;">
                        <?= htmlspecialchars(matchLineupPositionDisplay(match ($positionKey) {
                            'goleiro' => 'GO',
                            'zagueiro' => 'ZC',
                            'lateral' => $slotIndex === 0 ? 'LE' : 'LD',
                            'meia' => $slotIndex === 0 ? 'VOL' : 'MAT',
                            'ponta' => $slotIndex === 0 ? 'PTE' : 'PTD',
                            'atacante' => 'CA',
                            default => '',
                        })) ?>
                    </span>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <?php foreach ($fieldPlayers as $player): ?>
                <div class="c-lineup-token" style="left:<?= (int)$player['x'] ?>%;top:<?= (int)$player['y'] ?>%;">
                    <?= matchLineupAvatar($player['avatar'] ?? null, $player['player_name'], 'c-lineup-token-avatar') ?>
                    <strong><?= htmlspecialchars($player['player_name']) ?></strong>
                    <small><?= htmlspecialchars(matchLineupPositionDisplay($player['position_code'] ?? null)) ?></small>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
}

$title = 'Escalacao da Partida';
ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Escalação da Partida</h1>
            <p class="c-page-subtitle">
                <?= htmlspecialchars($match['participant_a'] . ' x ' . ($match['participant_b'] ?? '-')) ?>
                <?php if ($isInternalAutomatic): ?>
                    · Escalação automática
                <?php endif; ?>
            </p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/player/partidas.php" class="c-btn-secondary">Voltar</a>
    </div>

    <div class="c-page-content">
        <?php if ($isInternalAutomatic): ?>
            <div class="c-match-lineup-internal-grid">
                <div class="c-card">
                    <h3>Time 1</h3>
                    <?php renderPlayerMatchLineupField($slots, $fieldPlayersByTeam['team_1']); ?>
                </div>

                <div class="c-card">
                    <h3>Time 2</h3>
                    <?php renderPlayerMatchLineupField($slots, $fieldPlayersByTeam['team_2']); ?>
                </div>
            </div>

            <?php if (!empty($reservePlayers)): ?>
                <div class="c-card c-lineup-reserve-card">
                    <h3>Reservas</h3>
                    <div class="c-lineup-reserve-grid">
                        <?php foreach ($reservePlayers as $player): ?>
                            <div class="c-lineup-reserve-player">
                                <?= matchLineupAvatar($player['avatar'] ?? null, $player['player_name'], 'c-lineup-reserve-avatar') ?>
                                <strong><?= htmlspecialchars($player['player_name']) ?></strong>
                                <span><?= htmlspecialchars(matchLineupTeamLabel($player['lineup_team'] ?? 'team_1')) ?> · <?= htmlspecialchars(matchLineupPositionDisplay($player['position_code'] ?? null)) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="c-match-lineup-grid">
                <div class="c-card">
                    <h3>Confirmados disponíveis</h3>
                    <?php if (empty($cardPlayers)): ?>
                        <p>Nenhum jogador confirmado disponível.</p>
                    <?php else: ?>
                        <div class="c-lineup-avatar-grid">
                            <?php foreach ($cardPlayers as $player): ?>
                                <div class="c-lineup-avatar-card c-lineup-avatar-card--readonly">
                                    <?= matchLineupAvatar($player['avatar'] ?? null, $player['name'], 'c-lineup-avatar') ?>
                                    <span><?= htmlspecialchars($player['name']) ?></span>
                                    <small><?= htmlspecialchars(matchLineupPositionDisplay($player['position_code'] ?? null)) ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="c-card">
                    <h3>Campo</h3>
                    <?php renderPlayerMatchLineupField($slots, $fieldPlayers); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.c-match-lineup-grid { display: grid; grid-template-columns: minmax(0, 1.08fr) minmax(0, .92fr); gap: 14px; align-items: start; }
.c-match-lineup-internal-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; align-items: start; }
.c-lineup-reserve-card { margin-top: 14px; }
.c-lineup-reserve-grid { display: grid; grid-template-columns: repeat(8, minmax(0, 1fr)); gap: 8px; }
.c-lineup-reserve-player { border: 1px solid rgba(148,163,184,.28); background: rgba(15,23,42,.34); padding: 8px; display: grid; justify-items: center; gap: 4px; text-align: center; }
.c-lineup-reserve-avatar { width: 44px; height: 44px; display: grid; place-items: center; border-radius: 50%; object-fit: cover; background: linear-gradient(135deg,#34d399,#2563eb); border: 2px solid rgba(255,255,255,.82); font-size: 10px; font-weight: 800; }
.c-lineup-reserve-player strong,
.c-lineup-reserve-player span { width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.c-lineup-reserve-player strong { font-size: 11px; }
.c-lineup-reserve-player span { font-size: 9px; color: rgba(226,232,240,.7); }
.c-lineup-avatar-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 8px; }
.c-lineup-avatar-card { position: relative; width: 100%; min-height: 118px; display: grid; justify-items: center; gap: 4px; border: 1px solid rgba(148,163,184,.28); background: rgba(15,23,42,.34); color: #fff; padding: 8px 4px; }
.c-lineup-avatar-card--readonly { cursor: default; }
.c-lineup-avatar { width: 68px; height: 68px; display: grid; place-items: center; border-radius: 50%; object-fit: cover; background: linear-gradient(135deg,#34d399,#2563eb); border: 2px solid rgba(255,255,255,.82); font-size: 12px; font-weight: 800; }
.c-lineup-avatar-card span, .c-lineup-avatar-card small { width: 100%; overflow: hidden; text-align: center; text-overflow: ellipsis; white-space: nowrap; }
.c-lineup-avatar-card span { font-size: 11px; font-weight: 700; }
.c-lineup-avatar-card small { font-size: 9px; opacity: .75; }
.c-lineup-field { position: relative; width: min(100%, 285px); aspect-ratio: 3 / 4; margin: 0 auto; overflow: hidden; border: 1px solid rgba(255,255,255,.34); background: #116132; background-image: radial-gradient(circle at 50% 50%, rgba(255,255,255,.08) 0 1px, transparent 2px), linear-gradient(rgba(255,255,255,.06) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.06) 1px, transparent 1px); background-size: 38px 38px; }
.c-field-line { position:absolute; left:0; right:0; height:1px; background:rgba(255,255,255,.45); }
.c-field-mid { top:50%; }
.c-field-circle { position:absolute; border:1px solid rgba(255,255,255,.48); border-radius:50%; pointer-events:none; }
.c-field-circle-center { width:96px; height:96px; left:50%; top:50%; transform:translate(-50%,-50%); }
.c-field-spot { position:absolute; width:4px; height:4px; border-radius:50%; background:rgba(255,255,255,.55); transform:translate(-50%,-50%); }
.c-field-spot-center { left:50%; top:50%; }
.c-field-spot-top { left:50%; top:20%; }
.c-field-spot-bottom { left:50%; top:80%; }
.c-field-box { position:absolute; left:30%; width:40%; height:16%; border:1px solid rgba(255,255,255,.55); }
.c-field-box-top { top:-1px; }
.c-field-box-bottom { bottom:-1px; }
.c-field-goal { position:absolute; left:43%; width:14%; height:18px; border:1px solid rgba(255,255,255,.55); }
.c-field-goal-top { top:-1px; }
.c-field-goal-bottom { bottom:-1px; }
.c-lineup-position-slot { position:absolute; transform:translate(-50%,-50%); width:48px; height:48px; display:grid; place-items:center; border:1px dashed rgba(255,255,255,.34); border-radius:50%; color:rgba(255,255,255,.46); font-size:10px; font-weight:800; pointer-events:none; }
.c-lineup-token { position:absolute; transform:translate(-50%, -44%); display:grid; justify-items:center; width:86px; text-align:center; }
.c-lineup-token-avatar { width:56px; height:56px; display:grid; place-items:center; border-radius:50%; object-fit:cover; background:linear-gradient(135deg,#f8fafc,#34d399); color:#0f172a; border:2px solid rgba(255,255,255,.9); font-weight:800; }
.c-lineup-token strong, .c-lineup-token small { width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; text-shadow:0 1px 2px rgba(0,0,0,.8); }
.c-lineup-token strong { font-size:9px; }
.c-lineup-token small { font-size:8px; }
@media (max-width: 1180px) { .c-match-lineup-grid, .c-match-lineup-internal-grid { grid-template-columns: 1fr; } }
@media (max-width: 700px) { .c-lineup-avatar-grid, .c-lineup-reserve-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
