<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/../partidas/lineup_helpers.php';

requireProjectAuth();
matchLineupEnsureSchema($pdo);

$user = projectUser();
$stmt = $pdo->prepare("SELECT id FROM players WHERE user_id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$currentPlayerId = (int)($stmt->fetchColumn() ?: 0);

if ($currentPlayerId <= 0) {
    http_response_code(403);
    exit('Seu usuario ainda nao esta vinculado a um jogador.');
}

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

function matchShowRenderField(array $slots, array $fieldPlayers, string $emptyText = 'Nenhum titular definido.'): void
{
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
    ? PROJECT_URL . '/admin/player/competicao.php?id=' . (int)$match['competition_id']
    : PROJECT_URL . '/admin/player/partidas.php';

if (($match['status'] ?? '') === 'finished' && empty($match['field_type_snapshot'])) {
    matchLineupSaveFieldSnapshot($pdo, (int)$match['id']);
    $stmt->execute([$id]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);
}

$fieldSetup = matchLineupFieldSetupForMatch($pdo, $match);
$slots = $fieldSetup['slots'];

$lineupStmt = $pdo->prepare("
    SELECT ml.*, COALESCE(NULLIF(p.nickname, ''), LEFT(SUBSTRING_INDEX(p.name, ' ', 1), 14)) AS player_name, COALESCE(opp.name, pp.name) AS position_name, COALESCE(opp.code, pp.code) AS position_code, COALESCE(opp.group_key, pp.group_key) AS group_key, u.avatar
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
foreach ($lineup as $member) {
    $lineupTeam = in_array($member['lineup_team'] ?? '', ['team_1', 'team_2'], true) ? (string)$member['lineup_team'] : 'team_1';
    if (isset($slots[$member['slot_group']][(int)$member['slot_index']])) {
        $slot = $slots[$member['slot_group']][(int)$member['slot_index']];
        $fieldPlayersByTeam[$lineupTeam][] = $member + ['x' => $slot['x'], 'y' => $slot['y'], 'lineup_team' => $lineupTeam];
    }
}

$isInternalMatch = ($match['competition_context'] ?? '') === 'internal';

$title = 'Detalhes da Partida';

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title"><?= htmlspecialchars($match['participant_a'] . ' x ' . ($match['participant_b'] ?? '-')) ?></h1>
            <p class="c-page-subtitle"><?= htmlspecialchars((string)($match['competition_name'] ?? 'Partida avulsa')) ?></p>
        </div>

        <div class="c-actions">
            <a href="<?= PROJECT_URL ?>/admin/player/partidas_lineup.php?id=<?= (int)$match['id'] ?>" class="c-btn-secondary">Escalação</a>
            <a href="<?= $backUrl ?>" class="c-btn-secondary">Voltar</a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-match-show-layout">
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
                <div class="c-match-show-box c-match-card-summary">
                    <span>Cartões recebidos</span>
                    <strong>
                        <span class="c-match-card-count c-match-card-count--yellow"><?= (int)($match['yellow_cards_a'] ?? 0) ?> amarelo(s)</span>
                        <span class="c-match-card-count c-match-card-count--red"><?= (int)($match['red_cards_a'] ?? 0) ?> vermelho(s)</span>
                    </strong>
                </div>
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
            </div>

            <div class="c-card c-match-lineup-card">
                <h3><?= $isInternalMatch ? 'Titulares da partida' : 'Titulares do meu time' ?></h3>
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

.c-match-show-details {
    display: grid;
    grid-column: span 2;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
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
}

.c-match-two-fields {
    display: grid;
    gap: 12px;
}

.c-match-two-fields h4 {
    margin: 0 0 8px;
    font-size: 12px;
}

.c-match-field {
    position: relative;
    width: min(100%, 285px);
    aspect-ratio: 3 / 4;
    margin: 0 auto;
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
    .c-match-show-layout {
        grid-template-columns: 1fr;
    }

    .c-match-show-details,
    .c-match-lineup-card {
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
        width: min(100%, 285px);
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
