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
    SELECT m.*, c.name AS competition_name, c.context AS competition_context
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

$team = $pdo->query("SELECT team_type, custom_slots_json FROM team_profile WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$teamType = (string)($team['team_type'] ?? 'field');
$fieldSetup = matchLineupFieldSetup($teamType, $team['custom_slots_json'] ?? null);
$limits = $fieldSetup['limits'];
$slots = $fieldSetup['slots'];
$isInternalAutomatic = ($match['competition_context'] ?? '') === 'internal';

if ($isInternalAutomatic) {
    matchLineupAutoAssignInternal($pdo, $id);
} else {
    matchLineupSeedTeamRosterMatch($pdo, $id);
}

$confirmed = $pdo->prepare("
    SELECT p.id, COALESCE(NULLIF(p.nickname, ''), LEFT(SUBSTRING_INDEX(p.name, ' ', 1), 14)) AS name, pp.name AS position_name, pp.code AS position_code, pp.group_key, COALESCE(p.avatar, u.avatar) AS avatar
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
$confirmed->execute([$id, (string)($match['lineup_mode'] ?? 'team_roster'), (string)($match['lineup_mode'] ?? 'team_roster')]);
$availablePlayers = $confirmed->fetchAll(PDO::FETCH_ASSOC);

$lineupStmt = $pdo->prepare("
    SELECT ml.*, COALESCE(NULLIF(p.nickname, ''), LEFT(SUBSTRING_INDEX(p.name, ' ', 1), 14)) AS player_name, COALESCE(opp.name, pp.name) AS position_name, COALESCE(opp.code, pp.code) AS position_code, COALESCE(opp.group_key, pp.group_key) AS group_key, COALESCE(p.avatar, u.avatar) AS avatar
    FROM match_lineup ml
    INNER JOIN players p ON p.id = ml.player_id
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    LEFT JOIN player_positions opp ON opp.id = ml.override_position_id
    LEFT JOIN project_users u ON u.id = p.user_id
    WHERE ml.match_id = ?
    ORDER BY CASE ml.status WHEN 'starter' THEN 0 ELSE 1 END, ml.id ASC
");
$lineupStmt->execute([$id]);
$lineup = $lineupStmt->fetchAll(PDO::FETCH_ASSOC);

$statusStmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN mc.status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_total,
        SUM(CASE WHEN mc.status = 'declined' THEN 1 ELSE 0 END) AS declined_total
    FROM match_confirmations mc
    WHERE mc.match_id = ?
");
$statusStmt->execute([$id]);
$totals = $statusStmt->fetch(PDO::FETCH_ASSOC) ?: ['confirmed_total' => 0, 'declined_total' => 0];

$activePlayersTotal = (int)$pdo->query("SELECT COUNT(*) FROM players WHERE status = 'active'")->fetchColumn();
$confirmedTotal = (int)($totals['confirmed_total'] ?? 0);
$declinedTotal = (int)($totals['declined_total'] ?? 0);
$pendingTotal = max(0, $activePlayersTotal - $confirmedTotal - $declinedTotal);

$fieldPlayers = [];
$reservePlayers = [];
$fieldPlayersByTeam = ['team_1' => [], 'team_2' => []];
$reservePlayersByTeam = ['team_1' => [], 'team_2' => []];

foreach ($lineup as $member) {
    $lineupTeam = in_array($member['lineup_team'] ?? '', ['team_1', 'team_2'], true) ? (string)$member['lineup_team'] : 'team_1';

    if (($member['status'] ?? '') === 'starter' && isset($slots[$member['slot_group']][(int)$member['slot_index']])) {
        $slot = $slots[$member['slot_group']][(int)$member['slot_index']];
        $lineupMember = $member + ['x' => $slot['x'], 'y' => $slot['y'], 'lineup_team' => $lineupTeam];
        $fieldPlayers[] = $lineupMember;
        $fieldPlayersByTeam[$lineupTeam][] = $lineupMember;
    } else {
        $reserveMember = $member + ['lineup_team' => $lineupTeam];
        $reservePlayers[] = $reserveMember;
        $reservePlayersByTeam[$lineupTeam][] = $reserveMember;
    }
}

$cardPlayers = [];

foreach ($availablePlayers as $player) {
    $cardPlayers[] = [
        'id' => (int)$player['id'],
        'name' => $player['name'] ?? '-',
        'avatar' => $player['avatar'] ?? null,
        'position_code' => $player['position_code'] ?? null,
    ];
}

foreach ($reservePlayers as $player) {
    $cardPlayers[] = [
        'id' => (int)$player['player_id'],
        'name' => $player['player_name'] ?? '-',
        'avatar' => $player['avatar'] ?? null,
        'position_code' => $player['position_code'] ?? null,
    ];
}

$matchPlayersStmt = $pdo->prepare("
    SELECT
        p.id,
        COALESCE(NULLIF(p.nickname, ''), LEFT(SUBSTRING_INDEX(p.name, ' ', 1), 14)) AS name,
        pp.name AS position_name,
        pp.code AS position_code,
        pp.group_key,
        COALESCE(p.avatar, u.avatar) AS avatar,
        COALESCE(mc.status, 'pending') AS presence_status,
        ml.id AS lineup_id,
        ml.status AS lineup_status
    FROM players p
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    LEFT JOIN project_users u ON u.id = p.user_id
    LEFT JOIN match_confirmations mc ON mc.match_id = ? AND mc.player_id = p.id
    LEFT JOIN match_lineup ml ON ml.match_id = ? AND ml.player_id = p.id
    WHERE p.status = 'active'
    ORDER BY
        CASE COALESCE(mc.status, 'pending') WHEN 'confirmed' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END,
        CASE ml.status WHEN 'starter' THEN 0 WHEN 'reserve' THEN 1 ELSE 2 END,
        pp.sort_order ASC,
        p.name ASC
");
$matchPlayersStmt->execute([$id, $id]);
$matchPlayers = $matchPlayersStmt->fetchAll(PDO::FETCH_ASSOC);

$matchPlayerGroups = [
    'starter' => ['title' => 'Titulares', 'players' => []],
    'reserve' => ['title' => 'Reservas', 'players' => []],
    'available' => ['title' => 'Disponíveis', 'players' => []],
    'declined' => ['title' => 'Fora da partida', 'players' => []],
];

foreach ($matchPlayers as $player) {
    $presence = (string)($player['presence_status'] ?? 'pending');
    $lineupStatus = (string)($player['lineup_status'] ?? '');

    if ($presence === 'declined') {
        $matchPlayerGroups['declined']['players'][] = $player;
    } elseif ($lineupStatus === 'starter') {
        $matchPlayerGroups['starter']['players'][] = $player;
    } elseif ($lineupStatus === 'reserve') {
        $matchPlayerGroups['reserve']['players'][] = $player;
    } else {
        $matchPlayerGroups['available']['players'][] = $player;
    }
}

$positions = $pdo->query("
    SELECT id, code, name, group_label
    FROM player_positions
    WHERE status = 'active'
      AND COALESCE(group_key, '') <> 'reserva'
      AND code NOT LIKE 'RES%'
    ORDER BY sort_order ASC, name ASC
")->fetchAll(PDO::FETCH_ASSOC);
$groupedPositions = [];
foreach ($positions as $position) {
    $groupedPositions[$position['group_label'] ?? 'Outras'][] = $position;
}

if (!function_exists('renderMatchLineupField')) {
    function renderMatchLineupField(array $slots, array $fieldPlayers): void
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
                    <form action="<?= PROJECT_URL ?>/admin/partidas/lineup_remove.php?id=<?= (int)$player['id'] ?>" method="POST">
                        <?= csrf_field(); ?>
                        <button title="Remover do campo">x</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
}

$title = 'Escalacao da Partida';
$rightSidebarEnabled = true;

ob_start();
?>
<div class="c-card">
    <h3>Escalação automática</h3>
    <?php if ($isInternalAutomatic): ?>
        <p>Jogadores confirmados entram nos campos de Time 1 e Time 2 conforme suas posições, com distribuição aleatória e equilibrada.</p>
        <p>Depois de distribuído, o jogador permanece no mesmo time até ser removido.</p>
    <?php else: ?>
        <p>Use esta tela para montar a escalação da partida com os jogadores confirmados.</p>
    <?php endif; ?>
</div>

<style>
.c-lineup-info-list {
    display: grid;
    gap: 8px;
}

.c-lineup-inline-info .c-lineup-info-list {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 7px;
}

.c-lineup-info-list div {
    border: 1px solid rgba(148, 163, 184, .24);
    background: rgba(15, 23, 42, .34);
    padding: 8px;
}

.c-lineup-info-list span,
.c-lineup-info-list strong {
    display: block;
}

.c-lineup-info-list span {
    color: rgba(226, 232, 240, .72);
    margin-bottom: 4px;
}

.c-lineup-history-button {
    display: block;
    margin-top: 12px;
    text-align: center;
    grid-column: 1 / -1;
}

.c-lineup-card-note {
    color: rgba(226, 232, 240, .72);
    margin: -2px 0 12px;
}
</style>
<?php
$rightSidebarContent = ob_get_clean();

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Escalação da Partida</h1>
            <p class="c-page-subtitle"><?= htmlspecialchars($match['participant_a'] . ' x ' . ($match['participant_b'] ?? '-')) ?></p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/competicoes/view.php?id=<?= (int)$match['competition_id'] ?>" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <?php if ($isInternalAutomatic): ?>
            <div class="c-match-lineup-internal-grid">
                <div class="c-card">
                    <h3>Time 1</h3>
                    <?php renderMatchLineupField($slots, $fieldPlayersByTeam['team_1']); ?>
                </div>

                <div class="c-card">
                    <h3>Time 2</h3>
                    <?php renderMatchLineupField($slots, $fieldPlayersByTeam['team_2']); ?>
                </div>

                <div class="c-card c-lineup-inline-info">
                    <h3>Informações</h3>
                    <div class="c-lineup-info-list">
                        <div><span>Competição</span><strong><?= htmlspecialchars($match['competition_name'] ?? '-') ?></strong></div>
                        <div><span>Partida</span><strong><?= htmlspecialchars($match['participant_a'] . ' x ' . ($match['participant_b'] ?? '-')) ?></strong></div>
                        <div><span>Modo</span><strong><?= htmlspecialchars(matchLineupModeLabel($match['lineup_mode'] ?? 'team_roster')) ?></strong></div>
                        <div><span>Data</span><strong><?= htmlspecialchars($match['match_date'] ?? '-') ?></strong></div>
                        <div><span>Local</span><strong><?= htmlspecialchars($match['venue'] ?? '-') ?></strong></div>
                        <div><span>Confirmados</span><strong><?= $confirmedTotal ?></strong></div>
                        <div><span>Pendentes</span><strong><?= $pendingTotal ?></strong></div>
                        <div><span>Não vão</span><strong><?= $declinedTotal ?></strong></div>
                        <div><span>Em campo</span><strong><?= count($fieldPlayers) ?></strong></div>
                        <div><span>Reservas</span><strong><?= count($reservePlayers) ?></strong></div>
                    </div>
                    <a href="<?= PROJECT_URL ?>/admin/partidas/confirmations_history.php?id=<?= (int)$match['id'] ?>" class="c-btn-secondary c-lineup-history-button">
                        Histórico de confirmações
                    </a>
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
                <div class="c-lineup-roster-panel">
                    <div class="c-lineup-panel-heading">
                        <div>
                            <h3>Elenco da partida</h3>
                            <p class="c-lineup-card-note">Ajuste apenas esta partida sem alterar o Meu Time.</p>
                        </div>
                        <div class="c-lineup-mini-summary">
                            <span><?= count($fieldPlayers) ?> campo</span>
                            <span><?= count($reservePlayers) ?> reservas</span>
                            <span><?= $declinedTotal ?> fora</span>
                        </div>
                    </div>

                    <?php if (empty($matchPlayers)): ?>
                        <p>Nenhum jogador ativo cadastrado.</p>
                    <?php else: ?>
                        <?php foreach ($matchPlayerGroups as $groupKey => $group): ?>
                            <?php if (empty($group['players'])): ?>
                                <?php continue; ?>
                            <?php endif; ?>
                            <section class="c-lineup-player-section c-lineup-player-section--<?= htmlspecialchars($groupKey) ?>">
                                <h4>
                                    <?= htmlspecialchars($group['title']) ?>
                                    <span><?= count($group['players']) ?></span>
                                </h4>
                                <div class="c-lineup-presence-grid">
                                    <?php foreach ($group['players'] as $player): ?>
                                        <?php
                                            $presence = (string)($player['presence_status'] ?? 'pending');
                                            $lineupStatus = (string)($player['lineup_status'] ?? '');
                                            $statusLabel = match ($lineupStatus) {
                                                'starter' => 'Titular',
                                                'reserve' => 'Reserva',
                                                default => match ($presence) {
                                                    'declined' => 'Fora',
                                                    'confirmed' => 'Vai',
                                                    default => 'Pendente',
                                                },
                                            };
                                            $statusClass = match ($statusLabel) {
                                                'Titular', 'Vai' => 'is-confirmed',
                                                'Reserva' => 'is-reserve',
                                                'Fora' => 'is-declined',
                                                default => 'is-pending',
                                            };
                                        ?>
                                        <div class="c-lineup-player-card <?= $presence === 'declined' ? 'is-muted' : '' ?>">
                                            <div class="c-lineup-player-main">
                                                <?= matchLineupAvatar($player['avatar'] ?? null, $player['name'], 'c-lineup-avatar') ?>
                                                <div>
                                                    <strong><?= htmlspecialchars($player['name']) ?></strong>
                                                    <small><?= htmlspecialchars(matchLineupPositionDisplay($player['position_code'] ?? null)) ?></small>
                                                </div>
                                                <span class="c-lineup-presence-badge <?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span>
                                            </div>

                                            <div class="c-lineup-player-actions">
                                                <?php if ($presence === 'declined'): ?>
                                                    <form action="<?= PROJECT_URL ?>/admin/partidas/lineup_presence.php?id=<?= (int)$match['id'] ?>" method="POST">
                                                        <?= csrf_field(); ?>
                                                        <input type="hidden" name="player_id" value="<?= (int)$player['id'] ?>">
                                                        <input type="hidden" name="presence" value="confirmed">
                                                        <button class="c-btn-secondary">Vai</button>
                                                    </form>
                                                <?php else: ?>
                                                    <?php if (empty($player['lineup_id'])): ?>
                                                        <form action="<?= PROJECT_URL ?>/admin/partidas/lineup_store.php?id=<?= (int)$match['id'] ?>" method="POST" class="c-lineup-scale-form">
                                                            <?= csrf_field(); ?>
                                                            <input type="hidden" name="player_id" value="<?= (int)$player['id'] ?>">
                                                            <select name="override_position_id" class="c-lineup-position-select" aria-label="Posição nesta partida">
                                                                <option value="">Auto</option>
                                                                <?php foreach ($groupedPositions as $groupLabel => $groupPositions): ?>
                                                                    <optgroup label="<?= htmlspecialchars($groupLabel) ?>">
                                                                        <?php foreach ($groupPositions as $position): ?>
                                                                            <option value="<?= (int)$position['id'] ?>">
                                                                                <?= htmlspecialchars(($position['code'] ?? '') . ' - ' . $position['name']) ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </optgroup>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <button class="c-btn-secondary">Escalar</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form action="<?= PROJECT_URL ?>/admin/partidas/lineup_remove.php?id=<?= (int)$player['lineup_id'] ?>" method="POST">
                                                            <?= csrf_field(); ?>
                                                            <button class="c-btn-secondary">Remover</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form action="<?= PROJECT_URL ?>/admin/partidas/lineup_presence.php?id=<?= (int)$match['id'] ?>" method="POST">
                                                        <?= csrf_field(); ?>
                                                        <input type="hidden" name="player_id" value="<?= (int)$player['id'] ?>">
                                                        <input type="hidden" name="presence" value="declined">
                                                        <button class="c-btn-secondary">Não vai</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="c-lineup-field-panel">
                    <?php renderMatchLineupField($slots, $fieldPlayers); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.c-match-lineup-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(260px, .58fr); gap: 18px; align-items: start; }
.c-match-lineup-internal-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(260px, .58fr); gap: 14px; align-items: start; }
.c-lineup-inline-info { align-self: start; }
.c-lineup-mode-notice { display: grid; gap: 4px; margin-bottom: 14px; border-left: 3px solid #38bdf8; }
.c-lineup-mode-notice span { color: rgba(226, 232, 240, .78); }
.c-lineup-reserve-card { margin-top: 14px; }
.c-lineup-reserve-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px; }
.c-lineup-reserve-player { border: 1px solid rgba(148,163,184,.28); background: rgba(15,23,42,.34); padding: 8px; display: grid; justify-items: center; gap: 4px; text-align: center; }
.c-lineup-reserve-avatar { width: 44px; height: 44px; display: grid; place-items: center; border-radius: 50%; object-fit: cover; background: linear-gradient(135deg,#34d399,#2563eb); border: 2px solid rgba(255,255,255,.82); font-size: 10px; font-weight: 800; }
.c-lineup-reserve-player strong,
.c-lineup-reserve-player span { width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.c-lineup-reserve-player strong { font-size: 11px; }
.c-lineup-reserve-player span { font-size: 9px; color: rgba(226,232,240,.7); }
.c-lineup-avatar-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 8px; }
.c-lineup-roster-panel { border: 1px solid rgba(148,163,184,.24); background: rgba(15,23,42,.16); padding: 10px; min-width: 0; }
.c-lineup-panel-heading { display: flex; justify-content: space-between; align-items: start; gap: 12px; margin-bottom: 8px; }
.c-lineup-panel-heading h3 { margin: 0 0 4px; }
.c-lineup-mini-summary { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
.c-lineup-mini-summary span { border: 1px solid rgba(148,163,184,.2); background: rgba(30,41,59,.48); color: rgba(226,232,240,.78); padding: 4px 7px; font-size: 10px; font-weight: 800; white-space: nowrap; }
.c-lineup-player-section { display: grid; gap: 6px; padding-top: 8px; }
.c-lineup-player-section + .c-lineup-player-section { margin-top: 8px; border-top: 1px solid rgba(148,163,184,.16); }
.c-lineup-player-section h4 { display: flex; align-items: center; gap: 7px; margin: 0; color: rgba(226,232,240,.92); font-size: 12px; }
.c-lineup-player-section h4 span { border-radius: 5px; background: rgba(148,163,184,.14); color: rgba(226,232,240,.72); padding: 2px 6px; font-size: 10px; }
.c-lineup-presence-grid { display: grid; grid-template-columns: repeat(2, minmax(260px, 1fr)); gap: 6px; }
.c-lineup-player-card { border: 1px solid rgba(148,163,184,.18); background: rgba(15,23,42,.24); padding: 6px; display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 8px; min-width: 0; }
.c-lineup-player-card.is-muted { opacity: .72; }
.c-lineup-player-main { display: grid; grid-template-columns: 34px minmax(0, 1fr) auto; align-items: center; gap: 7px; min-width: 0; }
.c-lineup-player-main strong,
.c-lineup-player-main small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.c-lineup-player-main strong { font-size: 12px; }
.c-lineup-player-main small { color: rgba(226,232,240,.7); font-size: 10px; }
.c-lineup-presence-badge { border-radius: 5px; padding: 3px 6px; font-size: 10px; font-weight: 800; white-space: nowrap; }
.c-lineup-presence-badge.is-confirmed { background: rgba(34,197,94,.14); color: #22c55e; }
.c-lineup-presence-badge.is-reserve { background: rgba(245,158,11,.14); color: #f59e0b; }
.c-lineup-presence-badge.is-declined { background: rgba(239,68,68,.14); color: #ef4444; }
.c-lineup-presence-badge.is-pending { background: rgba(148,163,184,.14); color: rgba(226,232,240,.8); }
.c-lineup-player-actions { display: flex; align-items: center; justify-content: flex-end; gap: 5px; flex-wrap: wrap; min-width: 0; }
.c-lineup-player-actions form { margin: 0; }
.c-lineup-player-actions .c-btn-secondary { min-height: 26px; padding: 5px 8px; font-size: 10px; }
.c-lineup-scale-form { display: grid; grid-template-columns: minmax(88px, 150px) auto; gap: 5px; }
.c-lineup-avatar-card { position: relative; width: 100%; min-height: 118px; display: grid; justify-items: center; gap: 4px; border: 1px solid rgba(148,163,184,.28); background: rgba(15,23,42,.34); color: #fff; padding: 8px 4px; cursor: pointer; }
.c-lineup-avatar { width: 34px; height: 34px; display: grid; place-items: center; border-radius: 50%; object-fit: cover; background: linear-gradient(135deg,#34d399,#2563eb); border: 2px solid rgba(255,255,255,.82); font-size: 10px; font-weight: 800; }
.c-lineup-avatar-card span, .c-lineup-avatar-card small { width: 100%; overflow: hidden; text-align: center; text-overflow: ellipsis; white-space: nowrap; }
.c-lineup-avatar-card span { font-size: 11px; font-weight: 700; }
.c-lineup-avatar-card small { font-size: 9px; opacity: .75; }
.c-lineup-position-select { width: 100%; border: 1px solid rgba(148,163,184,.28); background: rgba(15,23,42,.72); color: #e5e7eb; padding: 5px 6px; font-size: 10px; }
.c-lineup-field-panel { min-width: 0; display: flex; align-items: flex-start; justify-content: center; }
.c-lineup-field { position: relative; width: min(100%, 286px); aspect-ratio: 3 / 4; margin: 0; overflow: hidden; border: 1px solid rgba(255,255,255,.34); background: #116132; background-image: radial-gradient(circle at 50% 50%, rgba(255,255,255,.08) 0 1px, transparent 2px), linear-gradient(rgba(255,255,255,.06) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.06) 1px, transparent 1px); background-size: 34px 34px; }
.c-field-line { position:absolute; left:0; right:0; height:1px; background:rgba(255,255,255,.45); }
.c-field-mid { top:50%; }
.c-field-circle { position:absolute; border:1px solid rgba(255,255,255,.48); border-radius:50%; pointer-events:none; }
.c-field-circle-center { width:86px; height:86px; left:50%; top:50%; transform:translate(-50%,-50%); }
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
.c-lineup-token { position: absolute; transform: translate(-50%, -44%); display: grid; justify-items: center; width: 72px; text-align: center; }
.c-lineup-token-avatar { width: 42px; height: 42px; display: grid; place-items: center; border-radius: 50%; object-fit: cover; background: linear-gradient(135deg,#f8fafc,#34d399); color:#0f172a; border: 2px solid rgba(255,255,255,.9); font-weight:800; }
.c-lineup-token strong, .c-lineup-token small { width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-shadow: 0 1px 2px rgba(0,0,0,.8); }
.c-lineup-token strong { font-size: 9px; }
.c-lineup-token small { font-size: 8px; }
.c-lineup-token form { position:absolute; right:12px; top:0; opacity:0; }
.c-lineup-token:hover form { opacity:1; }
.c-lineup-token button { width:18px; height:18px; border:1px solid rgba(255,255,255,.4); background:rgba(15,23,42,.86); color:#fff; padding:0; cursor:pointer; }
@media (max-width: 1180px) { .c-match-lineup-grid, .c-match-lineup-internal-grid { grid-template-columns: 1fr; } .c-lineup-field-panel { justify-content: flex-start; } }
@media (max-width: 700px) { .c-lineup-avatar-grid, .c-lineup-reserve-grid, .c-lineup-inline-info .c-lineup-info-list, .c-lineup-presence-grid { grid-template-columns: 1fr; } .c-lineup-panel-heading { display: grid; } .c-lineup-mini-summary { justify-content: flex-start; } .c-lineup-player-card { grid-template-columns: 1fr; } .c-lineup-player-main { grid-template-columns: 34px minmax(0, 1fr) auto; } .c-lineup-player-actions { justify-content: flex-start; } .c-lineup-scale-form { grid-template-columns: minmax(0, 1fr) auto; width: 100%; } .c-lineup-player-actions .c-btn-secondary { width: auto; } .c-lineup-field { width: 100%; max-width: none; } .c-lineup-token-avatar { width: 48px; height: 48px; font-size: 12px; } .c-lineup-token form { opacity:1; } }
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
