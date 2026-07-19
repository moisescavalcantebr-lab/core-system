<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

function trainingEnsureSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS training_roster (
            id INT AUTO_INCREMENT PRIMARY KEY,
            player_id INT NOT NULL,
            team_key ENUM('time_1','time_2') NOT NULL DEFAULT 'time_1',
            status ENUM('field','reserve','inactive') NOT NULL DEFAULT 'reserve',
            slot_group VARCHAR(40) NULL,
            slot_index INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_training_player (player_id),
            INDEX(team_key),
            INDEX(status),
            INDEX(slot_group, slot_index)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("ALTER TABLE training_roster MODIFY COLUMN status ENUM('field','reserve','inactive') NOT NULL DEFAULT 'reserve'");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS training_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            custom_slots_json JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function trainingDefaultSlots(): array
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

function trainingSettings(PDO $pdo, array $team): array
{
    $settings = $pdo->query("SELECT * FROM training_settings WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        $teamSlots = json_decode((string)($team['custom_slots_json'] ?? ''), true);
        $teamSlots = is_array($teamSlots) && $teamSlots ? $teamSlots : trainingDefaultSlots();
        $stmt = $pdo->prepare("INSERT INTO training_settings (id, custom_slots_json) VALUES (1, ?)");
        $stmt->execute([json_encode($teamSlots, JSON_UNESCAPED_UNICODE)]);
        return ['custom_slots_json' => json_encode($teamSlots, JSON_UNESCAPED_UNICODE)];
    }

    return $settings;
}

function trainingInitials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $substr = function_exists('mb_substr') ? 'mb_substr' : 'substr';
    $upper = function_exists('mb_strtoupper') ? 'mb_strtoupper' : 'strtoupper';
    return $upper($substr($parts[0] ?? '', 0, 1) . $substr($parts[1] ?? '', 0, 1));
}

function trainingAvatarHtml(?string $avatar, string $name, string $class): string
{
    if ($avatar !== null && trim($avatar) !== '') {
        $src = PROJECT_URL . '/' . ltrim($avatar, '/');
        return '<img class="' . htmlspecialchars($class) . '" src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($name) . '">';
    }

    return '<span class="' . htmlspecialchars($class) . '">' . htmlspecialchars(trainingInitials($name)) . '</span>';
}

function trainingPositionDisplay(?string $code): string
{
    return preg_replace('/\d+$/', '', (string)$code) ?: '-';
}

function trainingNormalizeCustomSlots(array $customSlots): array
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

function trainingCustomFieldSetup(array $team): ?array
{
    $customSlots = json_decode((string)($team['custom_slots_json'] ?? ''), true);
    $customSlots = is_array($customSlots) ? trainingNormalizeCustomSlots($customSlots) : [];

    if (!$customSlots) {
        return null;
    }

    $slots = [];
    $limits = [];
    $addSlot = static function (string $group, int $x, int $y, string $label) use (&$slots, &$limits): void {
        $slots[$group][] = ['x' => $x, 'y' => $y, 'label' => $label];
        $limits[$group] = ($limits[$group] ?? 0) + 1;
    };

    if (!empty($customSlots['GO'])) {
        $addSlot('goleiro', 50, 88, 'GO');
    }

    $zcSlots = array_values(array_filter(['ZC1', 'ZC2'], static fn (string $code): bool => !empty($customSlots[$code])));
    foreach ($zcSlots as $index => $code) {
        $addSlot('zagueiro', count($zcSlots) === 1 ? 50 : ($index === 0 ? 38 : 62), 72, 'ZC');
    }

    if (!empty($customSlots['LE'])) {
        $addSlot('lateral', 18, 62, 'LE');
    }
    if (!empty($customSlots['LD'])) {
        $addSlot('lateral', 82, 62, 'LD');
    }

    $volSlots = array_values(array_filter(['VOL1', 'VOL2'], static fn (string $code): bool => !empty($customSlots[$code])));
    foreach ($volSlots as $index => $code) {
        $addSlot('meia', count($volSlots) === 1 ? 50 : ($index === 0 ? 42 : 58), 55, 'VOL');
    }

    $matSlots = array_values(array_filter(['MAT1', 'MAT2'], static fn (string $code): bool => !empty($customSlots[$code])));
    foreach ($matSlots as $index => $code) {
        $addSlot('meia', count($matSlots) === 1 ? 50 : ($index === 0 ? 38 : 62), 40, 'MAT');
    }

    if (!empty($customSlots['PTE'])) {
        $addSlot('ponta', 24, 24, 'PTE');
    }
    if (!empty($customSlots['PTD'])) {
        $addSlot('ponta', 76, 24, 'PTD');
    }

    $attackSlots = array_values(array_filter(['CA1', 'CA2'], static fn (string $code): bool => !empty($customSlots[$code])));
    foreach ($attackSlots as $index => $code) {
        $addSlot('atacante', count($attackSlots) === 1 ? 50 : ($index === 0 ? 38 : 62), 20, 'CA');
    }

    return array_sum($limits) > 0 ? ['limits' => $limits, 'slots' => $slots] : null;
}

function trainingFieldSetup(array $team): array
{
    $customSetup = trainingCustomFieldSetup($team);
    if ($customSetup !== null) {
        return $customSetup;
    }

    return [
        'limits' => ['goleiro' => 1, 'zagueiro' => 2, 'lateral' => 2, 'meia' => 3, 'ponta' => 2, 'atacante' => 1],
        'slots' => [
            'goleiro' => [['x' => 50, 'y' => 88, 'label' => 'GO']],
            'zagueiro' => [['x' => 38, 'y' => 72, 'label' => 'ZC'], ['x' => 62, 'y' => 72, 'label' => 'ZC']],
            'lateral' => [['x' => 18, 'y' => 62, 'label' => 'LE'], ['x' => 82, 'y' => 62, 'label' => 'LD']],
            'meia' => [['x' => 50, 'y' => 55, 'label' => 'VOL'], ['x' => 38, 'y' => 40, 'label' => 'MAT'], ['x' => 62, 'y' => 40, 'label' => 'MAT']],
            'ponta' => [['x' => 24, 'y' => 24, 'label' => 'PTE'], ['x' => 76, 'y' => 24, 'label' => 'PTD']],
            'atacante' => [['x' => 50, 'y' => 20, 'label' => 'CA']],
        ],
    ];
}

function trainingTeamLabel(string $teamKey): string
{
    return $teamKey === 'time_2' ? 'Time 2' : 'Time 1';
}

trainingEnsureSchema($pdo);

$team = $pdo->query("SELECT * FROM team_profile WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: ['name' => 'Meu Time'];
$trainingSettings = trainingSettings($pdo, $team);
$fieldSetup = trainingFieldSetup($trainingSettings);
$slotMap = $fieldSetup['slots'];

$trainingRows = $pdo->query("
    SELECT
        tr.*,
        COALESCE(NULLIF(p.nickname, ''), LEFT(SUBSTRING_INDEX(p.name, ' ', 1), 14)) AS player_name,
        COALESCE(p.avatar, u.avatar) AS avatar,
        pp.code AS position_code,
        pp.name AS position_name
    FROM training_roster tr
    INNER JOIN players p ON p.id = tr.player_id
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    LEFT JOIN project_users u ON u.id = p.user_id
    WHERE p.status = 'active'
    ORDER BY tr.team_key ASC, tr.status ASC, tr.slot_group ASC, tr.slot_index ASC, p.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$assignedIds = [];
$teams = [
    'time_1' => ['field' => [], 'reserve' => []],
    'time_2' => ['field' => [], 'reserve' => []],
];
$inactiveRows = [];
$occupied = ['time_1' => [], 'time_2' => []];
$invalidSlotIds = [];

foreach ($trainingRows as $row) {
    $teamKey = (string)($row['team_key'] ?? 'time_1');
    $teamKey = $teamKey === 'time_2' ? 'time_2' : 'time_1';
    $assignedIds[] = (int)$row['player_id'];

    if (($row['status'] ?? '') === 'inactive') {
        $inactiveRows[] = $row;
        continue;
    }

    if (($row['status'] ?? '') !== 'field') {
        $teams[$teamKey]['reserve'][] = $row;
        continue;
    }

    $slotGroup = (string)($row['slot_group'] ?? '');
    $slotIndex = $row['slot_index'] !== null ? (int)$row['slot_index'] : null;
    $slot = $slotIndex !== null ? ($slotMap[$slotGroup][$slotIndex] ?? null) : null;

    if ($slot === null) {
        $invalidSlotIds[] = (int)$row['id'];
        $teams[$teamKey]['reserve'][] = $row;
        continue;
    }

    $occupied[$teamKey][$slotGroup . ':' . $slotIndex] = true;
    $row['_x'] = (int)$slot['x'];
    $row['_y'] = (int)$slot['y'];
    $teams[$teamKey]['field'][] = $row;
}

if ($invalidSlotIds) {
    $placeholders = implode(',', array_fill(0, count($invalidSlotIds), '?'));
    $stmt = $pdo->prepare("UPDATE training_roster SET status = 'reserve', slot_group = NULL, slot_index = NULL WHERE id IN ($placeholders)");
    $stmt->execute($invalidSlotIds);
}

$available = [];
$sqlAssigned = $assignedIds ? implode(',', array_fill(0, count($assignedIds), '?')) : '';
$availableSql = "
    SELECT
        p.id,
        COALESCE(NULLIF(p.nickname, ''), LEFT(SUBSTRING_INDEX(p.name, ' ', 1), 14)) AS player_name,
        COALESCE(p.avatar, u.avatar) AS avatar,
        pp.code AS position_code,
        pp.name AS position_name
    FROM players p
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    LEFT JOIN project_users u ON u.id = p.user_id
    WHERE p.status = 'active'
";
if ($sqlAssigned !== '') {
    $availableSql .= " AND p.id NOT IN ($sqlAssigned)";
}
$availableSql .= " ORDER BY pp.sort_order ASC, p.name ASC";
$stmt = $pdo->prepare($availableSql);
$stmt->execute($assignedIds);
$available = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = 'Treino';
ob_start();
?>

<div class="c-page c-training-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Treino</h1>
            <p class="c-page-subtitle">Divida o elenco em dois times sem alterar o Meu Time.</p>
        </div>
        <div class="c-page-actions">
            <a href="<?= PROJECT_URL ?>/admin/treino/disponiveis.php" class="c-btn-secondary">Jogadores</a>
            <a href="<?= PROJECT_URL ?>/admin/treino/config.php" class="c-btn-secondary">Posições do treino</a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-training-layout">
            <?php if (!empty($available)): ?>
                <section class="c-training-panel c-training-available">
                    <div class="c-training-section-title">
                        <h2>Disponíveis</h2>
                        <span><?= count($available) ?></span>
                    </div>

                    <div class="c-training-player-list">
                        <?php foreach ($available as $player): ?>
                            <div class="c-training-player-card">
                                <?= trainingAvatarHtml($player['avatar'] ?? null, (string)$player['player_name'], 'c-training-list-avatar') ?>
                                <strong><?= htmlspecialchars((string)$player['player_name']) ?></strong>
                                <span><?= htmlspecialchars(trainingPositionDisplay($player['position_code'] ?? null)) ?></span>
                                <form action="<?= PROJECT_URL ?>/admin/treino/store.php" method="POST">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="player_id" value="<?= (int)$player['id'] ?>">
                                    <input type="hidden" name="team_key" value="time_1">
                                    <button class="c-btn-secondary" title="Enviar para Time 1">1</button>
                                </form>
                                <form action="<?= PROJECT_URL ?>/admin/treino/store.php" method="POST">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="player_id" value="<?= (int)$player['id'] ?>">
                                    <input type="hidden" name="team_key" value="time_2">
                                    <button class="c-btn-secondary" title="Enviar para Time 2">2</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php foreach (['time_1', 'time_2'] as $teamKey): ?>
                <?php $teamHasContent = count($teams[$teamKey]['field']) > 0 || count($teams[$teamKey]['reserve']) > 0; ?>
                <?php if ($teamHasContent): ?>
                <section class="c-training-team c-training-team--<?= htmlspecialchars($teamKey) ?>">
                    <div class="c-training-section-title">
                        <h2><?= htmlspecialchars(trainingTeamLabel($teamKey)) ?></h2>
                        <span><?= count($teams[$teamKey]['field']) ?> em campo e <?= count($teams[$teamKey]['reserve']) ?> reserva<?= count($teams[$teamKey]['reserve']) === 1 ? '' : 's' ?></span>
                    </div>

                    <div class="c-team-field c-training-field">
                        <label class="c-training-toggle-x">
                            <input type="checkbox" data-training-hide-x>
                            <span>Ocultar x</span>
                        </label>
                        <div class="c-team-field-line c-team-field-center"></div>
                        <div class="c-team-field-circle c-team-field-circle-center"></div>
                        <div class="c-team-field-spot c-team-field-spot-center"></div>
                        <div class="c-team-field-spot c-team-field-spot-top"></div>
                        <div class="c-team-field-spot c-team-field-spot-bottom"></div>
                        <div class="c-team-field-box c-team-field-box-top"></div>
                        <div class="c-team-field-box c-team-field-box-bottom"></div>
                        <div class="c-team-field-goal c-team-field-goal-top"></div>
                        <div class="c-team-field-goal c-team-field-goal-bottom"></div>

                        <?php foreach ($slotMap as $positionKey => $slots): ?>
                            <?php foreach ($slots as $slotIndex => $slot): ?>
                                <?php if (isset($occupied[$teamKey][$positionKey . ':' . $slotIndex])): ?>
                                    <?php continue; ?>
                                <?php endif; ?>
                                <div
                                    class="c-team-position-slot c-team-position-slot--empty"
                                    style="left:<?= (int)$slot['x'] ?>%;top:<?= (int)$slot['y'] ?>%;"
                                >
                                    <?= htmlspecialchars(trainingPositionDisplay((string)($slot['label'] ?? ''))) ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>

                        <?php foreach ($teams[$teamKey]['field'] as $player): ?>
                            <div class="c-training-token c-training-token--<?= htmlspecialchars($teamKey) ?>" style="left:<?= (int)$player['_x'] ?>%;top:<?= (int)$player['_y'] ?>%;">
                                <?= trainingAvatarHtml($player['avatar'] ?? null, (string)$player['player_name'], 'c-training-token-avatar') ?>
                                <strong title="<?= htmlspecialchars((string)$player['player_name']) ?>"><?= htmlspecialchars((string)$player['player_name']) ?></strong>
                                <span><?= htmlspecialchars(trainingPositionDisplay($player['position_code'] ?? null)) ?></span>
                                <form action="<?= PROJECT_URL ?>/admin/treino/move.php?id=<?= (int)$player['id'] ?>" method="POST">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="target" value="reserve">
                                    <button title="Enviar para reserva">x</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if (!empty($teams[$teamKey]['reserve'])): ?>
                    <section class="c-training-panel c-training-reserve-panel c-training-reserve-panel--<?= htmlspecialchars($teamKey) ?>">
                        <div class="c-training-section-title">
                            <h2>Reservas</h2>
                        </div>

                        <div class="c-training-reserve-list">
                            <?php foreach ($teams[$teamKey]['reserve'] as $player): ?>
                                <div class="c-training-reserve-item">
                                    <?= trainingAvatarHtml($player['avatar'] ?? null, (string)$player['player_name'], 'c-training-list-avatar') ?>
                                    <strong><?= htmlspecialchars((string)$player['player_name']) ?></strong>
                                    <span><?= htmlspecialchars(trainingPositionDisplay($player['position_code'] ?? null)) ?></span>
                                    <form action="<?= PROJECT_URL ?>/admin/treino/move.php?id=<?= (int)$player['id'] ?>" method="POST">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="target" value="field">
                                        <button class="c-btn-secondary c-training-mini-action" title="Enviar para o campo">+</button>
                                    </form>
                                    <form action="<?= PROJECT_URL ?>/admin/treino/remove.php?id=<?= (int)$player['id'] ?>" method="POST">
                                        <?= csrf_field(); ?>
                                        <button class="c-btn-secondary c-training-mini-action" title="Remover do treino">x</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
body:has(.c-training-page) .c-layout {
    grid-template-columns: 220px minmax(0, 1fr);
}

.c-training-layout {
    display: grid;
    grid-template-columns: 260px minmax(0, 236px) minmax(0, 236px) 260px;
    grid-template-areas:
        "available available available available"
        "time1 reserve1 reserve2 time2";
    gap: 12px;
    align-items: start;
    --team-field-height: clamp(230px, 23vw, 286px);
}

.c-training-available {
    grid-area: available;
}

.c-training-team,
.c-training-panel {
    background: transparent;
    min-width: 0;
}

.c-training-team--time_1 {
    grid-area: time1;
}

.c-training-team--time_2 {
    grid-area: time2;
}

.c-training-reserve-panel--time_1 {
    grid-area: reserve1;
}

.c-training-reserve-panel--time_2 {
    grid-area: reserve2;
}

.c-training-reserve-panel {
    border: 0;
    padding: 0;
}

.c-training-reserve-panel--time_2 .c-training-section-title {
    justify-content: flex-end;
    text-align: right;
}

.c-training-section-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
}

.c-training-section-title h2 {
    margin: 0;
    font-size: 15px;
}

.c-training-section-title span {
    color: #9fb1c7;
    font-size: 12px;
}

.c-team-field {
    position: relative;
    width: 100%;
    max-width: 260px;
    aspect-ratio: 3 / 4;
    min-height: var(--team-field-height);
    overflow: hidden;
    background:
        radial-gradient(circle at 50% 50%, rgba(255,255,255,.08) 0 1px, transparent 2px),
        linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px),
        linear-gradient(180deg, rgba(255,255,255,.05) 1px, transparent 1px),
        #145c38;
    background-size: 42px 42px, 42px 42px, 42px 42px, 100% 100%;
    border: 1px solid rgba(255,255,255,.28);
}

.c-training-field {
    margin: 0;
}

.c-training-toggle-x {
    position: absolute;
    top: 6px;
    right: 6px;
    z-index: 6;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 5px;
    border: 1px solid rgba(148, 163, 184, .28);
    border-radius: 4px;
    background: rgba(15, 23, 42, .62);
    color: rgba(226, 232, 240, .82);
    font-size: 9px;
    line-height: 1;
    cursor: pointer;
}

.c-training-toggle-x input {
    width: 11px;
    height: 11px;
    margin: 0;
}

.c-training-page.is-hiding-field-actions .c-training-token form {
    display: none;
}

.c-training-team--time_1 .c-training-field {
    border-color: rgba(34, 197, 94, .78);
    box-shadow: 0 0 0 1px rgba(34, 197, 94, .16);
}

.c-training-team--time_2 .c-training-field {
    border-color: rgba(56, 189, 248, .78);
    box-shadow: 0 0 0 1px rgba(56, 189, 248, .16);
}

.c-team-field-line,
.c-team-field-circle,
.c-team-field-spot,
.c-team-field-box,
.c-team-field-goal {
    position: absolute;
    pointer-events: none;
    border-color: rgba(255, 255, 255, .48);
}

.c-team-field-center {
    left: 0;
    top: 50%;
    width: 100%;
    border-top: 1px solid rgba(255,255,255,.48);
}

.c-team-field-circle-center {
    left: 50%;
    top: 50%;
    width: 34%;
    aspect-ratio: 1;
    transform: translate(-50%, -50%);
    border: 1px solid rgba(255,255,255,.48);
    border-radius: 50%;
}

.c-team-field-spot {
    width: 5px;
    height: 5px;
    background: rgba(255,255,255,.62);
    border-radius: 50%;
    transform: translate(-50%, -50%);
}

.c-team-field-spot-center { left: 50%; top: 50%; }
.c-team-field-spot-top { left: 50%; top: 18%; }
.c-team-field-spot-bottom { left: 50%; top: 82%; }

.c-team-field-box {
    left: 26%;
    width: 48%;
    height: 15%;
    border: 1px solid rgba(255,255,255,.48);
}

.c-team-field-box-top { top: 0; border-top: 0; }
.c-team-field-box-bottom { bottom: 0; border-bottom: 0; }

.c-team-field-goal {
    left: 42%;
    width: 16%;
    height: 4%;
    border: 1px solid rgba(255,255,255,.48);
}

.c-team-field-goal-top { top: 0; border-top: 0; }
.c-team-field-goal-bottom { bottom: 0; border-bottom: 0; }

.c-team-position-slot,
.c-training-token {
    position: absolute;
    transform: translate(-50%, -50%);
}

.c-team-position-slot {
    display: grid;
    place-items: center;
    width: clamp(32px, 3vw, 40px);
    height: clamp(32px, 3vw, 40px);
    border: 1px dashed rgba(255,255,255,.35);
    border-radius: 50%;
    color: rgba(255,255,255,.65);
    font-size: 10px;
    font-weight: 700;
}

.c-training-token {
    width: 54px;
    text-align: center;
    color: #fff;
}

.c-training-token-avatar,
.c-training-list-avatar {
    display: grid;
    place-items: center;
    border-radius: 999px;
    object-fit: cover;
    color: #fff;
    font-weight: 800;
    background: linear-gradient(135deg, #34d399, #2563eb);
}

.c-training-token-avatar {
    width: clamp(30px, 2.5vw, 36px);
    height: clamp(30px, 2.5vw, 36px);
    margin: 0 auto 2px;
    border: 2px solid #38bdf8;
}

.c-training-token--time_1 .c-training-token-avatar {
    border-color: #22c55e;
}

.c-training-token--time_2 .c-training-token-avatar {
    border-color: #38bdf8;
}

.c-training-token strong {
    display: block;
    font-size: 9px;
    line-height: 1.1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.c-training-token span {
    display: block;
    font-size: 9px;
}

.c-training-token form {
    position: absolute;
    top: -4px;
    right: 4px;
}

.c-training-token button {
    width: 17px;
    height: 17px;
    border: 1px solid rgba(248, 113, 113, .6);
    border-radius: 3px;
    background: rgba(127, 29, 29, .85);
    color: #fff;
    font-size: 10px;
    line-height: 1;
    cursor: pointer;
}

.c-training-panel {
    border: 0;
    padding: 0;
}

.c-training-reserve-panel {
    border: 0;
    padding: 0;
}

.c-training-player-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(92px, max-content));
    gap: 4px;
}

.c-training-player-card,
.c-training-reserve-item {
    display: grid;
    grid-template-columns: 26px minmax(0, 1fr);
    gap: 4px;
    align-items: center;
    border: 1px solid rgba(148, 163, 184, .22);
    padding: 4px;
    background: rgba(15, 23, 42, .28);
}

.c-training-reserve-item {
    grid-template-columns: 24px minmax(0, 1fr);
    min-height: 0;
}

.c-training-player-card {
    grid-template-columns: 26px 26px 42px;
    grid-template-areas:
        "avatar name name"
        "btn1 btn2 position";
    column-gap: 5px;
    width: 112px;
    max-width: 100%;
}

.c-training-player-card .c-training-list-avatar {
    grid-area: avatar;
}

.c-training-player-card strong {
    grid-area: name;
    align-self: center;
}

.c-training-player-card span {
    grid-area: position;
    justify-self: start;
}

.c-training-player-card form:first-of-type {
    grid-area: btn1;
}

.c-training-player-card form:last-of-type {
    grid-area: btn2;
    margin-left: 0;
}

.c-training-list-avatar {
    width: 24px;
    height: 24px;
    border: 1px solid #f59e0b;
    font-size: 10px;
}

.c-training-player-card strong,
.c-training-reserve-item strong {
    display: block;
    font-size: 9px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.c-training-player-card span,
.c-training-reserve-item span {
    display: grid;
    place-items: center;
    width: 28px;
    max-width: 28px;
    min-height: 22px;
    margin-top: 2px;
    padding: 0 3px;
    border: 1px solid rgba(148, 163, 184, .2);
    border-radius: 4px;
    background: rgba(15, 23, 42, .58);
    color: #cbd5e1;
    font-size: 8px;
    line-height: 1.1;
}

.c-training-player-card form,
.c-training-reserve-item form {
    margin: 0;
}

.c-training-player-card form:first-of-type,
.c-training-reserve-item form:first-of-type {
    grid-column: 1;
}

.c-training-player-card form:last-of-type,
.c-training-reserve-item form:last-of-type {
    grid-column: 2;
}

.c-training-player-card form:first-of-type {
    grid-column: auto;
}

.c-training-player-card form:last-of-type {
    grid-column: auto;
}

.c-training-player-card form:first-of-type {
    grid-area: btn1;
}

.c-training-player-card form:last-of-type {
    grid-area: btn2;
}

.c-training-player-card button,
.c-training-reserve-item button {
    width: 100%;
    min-height: 22px;
    padding: 3px 6px;
    font-size: 9px;
    white-space: nowrap;
}

.c-training-player-card button {
    display: grid;
    place-items: center;
    width: 26px;
    min-width: 26px;
    height: 22px;
    min-height: 22px;
    padding: 0;
    font-size: 12px;
    font-weight: 800;
}

.c-training-player-card form:first-of-type button {
    border-color: rgba(34, 197, 94, .55);
    background: rgba(20, 83, 45, .58);
    color: #86efac;
}

.c-training-player-card form:last-of-type button {
    border-color: rgba(56, 189, 248, .55);
    background: rgba(14, 116, 144, .46);
    color: #bae6fd;
}

.c-training-reserve-item .c-training-mini-action {
    display: grid;
    place-items: center;
    width: 100%;
    min-width: 0;
    height: 22px;
    min-height: 22px;
    padding: 0;
    font-size: 12px;
    line-height: 1;
    font-weight: 800;
}

.c-training-reserve-item form:last-of-type .c-training-mini-action {
    width: 100%;
}

.c-training-reserve-item form:first-of-type .c-training-mini-action {
    border-color: rgba(34, 197, 94, .45);
    background: rgba(20, 83, 45, .58);
    color: #86efac;
}

.c-training-reserve-item form:last-of-type .c-training-mini-action {
    border-color: rgba(248, 113, 113, .5);
    background: rgba(127, 29, 29, .65);
    color: #fecaca;
}

.c-training-reserve-list {
    display: grid;
    grid-template-columns: repeat(2, 112px);
    gap: 4px;
}

.c-training-reserve-panel--time_2 .c-training-reserve-list {
    direction: rtl;
    justify-content: end;
}

.c-training-reserve-panel--time_2 .c-training-reserve-item {
    direction: ltr;
}

.c-training-reserve-item {
    grid-template-columns: 26px 26px 42px;
    grid-template-areas:
        "avatar name name"
        "btn1 btn2 position";
    width: 112px;
    max-width: 100%;
}

.c-training-reserve-panel--time_1 .c-training-reserve-item {
    border-color: rgba(34, 197, 94, .58);
}

.c-training-reserve-panel--time_2 .c-training-reserve-item {
    border-color: rgba(56, 189, 248, .58);
}

.c-training-reserve-item .c-training-list-avatar {
    grid-area: avatar;
}

.c-training-reserve-item strong {
    grid-area: name;
    align-self: center;
}

.c-training-reserve-item span {
    grid-area: position;
}

.c-training-reserve-item form:first-of-type {
    grid-area: btn1;
}

.c-training-reserve-item form:last-of-type {
    grid-area: btn2;
}

.c-training-empty {
    margin: 0;
    color: #b8c7da;
}

@media (max-width: 1180px) {
    .c-training-layout {
        grid-template-columns: 1fr;
        grid-template-areas:
            "available"
            "time1"
            "reserve1"
            "reserve2"
            "time2";
    }

    .c-training-team--time_1,
    .c-training-team--time_2,
    .c-training-reserve-panel--time_1,
    .c-training-reserve-panel--time_2 {
        grid-column: auto;
        grid-row: auto;
    }

    .c-training-field {
        margin: 0 auto;
    }
}

@media (max-width: 680px) {
    body:has(.c-training-page) .c-content {
        padding-left: 6px;
        padding-right: 6px;
    }

    .c-training-page .c-page-header {
        margin-bottom: 14px;
    }

    .c-training-layout {
        gap: 10px;
    }

    .c-training-team {
        min-width: 0;
    }

    .c-training-field {
        width: 100%;
        max-width: none;
        min-height: auto;
    }

    .c-training-player-list {
        grid-template-columns: repeat(auto-fill, minmax(92px, max-content));
    }

    .c-training-available .c-training-player-list,
    .c-training-inactive .c-training-player-list {
        grid-template-columns: repeat(auto-fill, minmax(92px, max-content));
    }

    .c-training-player-card,
    .c-training-reserve-item {
        grid-template-columns: 28px minmax(0, 1fr);
        gap: 4px;
    }

    .c-training-player-card {
        grid-template-columns: 26px 26px 42px;
        width: 112px;
    }

    .c-training-reserve-list {
        grid-template-columns: repeat(auto-fill, minmax(92px, max-content));
    }

    .c-training-reserve-panel--time_2 .c-training-section-title {
        justify-content: space-between;
        text-align: left;
    }

    .c-training-reserve-panel--time_2 .c-training-reserve-list {
        direction: ltr;
        justify-content: start;
    }

    .c-training-token {
        width: 64px;
    }

    .c-training-token-avatar {
        width: 48px;
        height: 48px;
        font-size: 12px;
    }

    .c-training-token strong,
    .c-training-token span {
        font-size: 10px;
    }

    .c-training-token button {
        width: 19px;
        height: 19px;
    }

    .c-training-player-card form,
    .c-training-reserve-item form {
        display: inline-flex;
    }

    .c-training-player-card button,
    .c-training-reserve-item button {
        min-height: 22px;
        padding: 3px 6px;
        font-size: 9px;
    }

    .c-training-reserve-item .c-training-mini-action {
        padding: 0;
        font-size: 12px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const page = document.querySelector('.c-training-page');
    const toggles = Array.from(document.querySelectorAll('[data-training-hide-x]'));
    const storageKey = 'treino.hideFieldActions:' + window.location.pathname.split('/web/admin/')[0];
    if (!page || toggles.length === 0) {
        return;
    }

    function setHidden(checked) {
        page.classList.toggle('is-hiding-field-actions', checked);
        toggles.forEach(function (toggle) {
            toggle.checked = checked;
        });
    }

    setHidden(localStorage.getItem(storageKey) === '1');

    toggles.forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            setHidden(toggle.checked);
            localStorage.setItem(storageKey, toggle.checked ? '1' : '0');
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../app/views/layout_admin.php';
