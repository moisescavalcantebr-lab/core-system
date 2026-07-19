<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/lineup_helpers.php';

requireProjectAdmin();

if (!function_exists('projectModuleProvides') || !projectModuleProvides('advanced_live')) {
    flash('error', 'Modulo ao vivo ainda nao esta liberado para este projeto.');
    redirect(PROJECT_URL . '/admin/partidas/index.php');
}

matchLineupEnsureSchema($pdo);

function liveScoreEnsureSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS live_match_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            match_id INT NOT NULL,
            player_id INT NOT NULL,
            event_type ENUM('goal') NOT NULL DEFAULT 'goal',
            team_key ENUM('team_1','team_2') NOT NULL DEFAULT 'team_1',
            event_minute INT NULL,
            notes TEXT NULL,
            created_by_user_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_live_match_events_match (match_id),
            INDEX idx_live_match_events_player (player_id),
            INDEX idx_live_match_events_type (event_type),
            INDEX idx_live_match_events_team (team_key),
            INDEX idx_live_match_events_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function liveScoreEventLabel(string $type): string
{
    return match ($type) {
        'goal' => 'Gol',
        default => ucfirst($type),
    };
}

function liveScoreRenderField(array $slots, array $fieldPlayers, int $selectedPlayerId): void
{
    ?>
    <div class="c-live-field">
        <span class="c-live-field-line c-live-field-mid"></span>
        <span class="c-live-field-circle"></span>
        <span class="c-live-field-spot c-live-field-spot-center"></span>
        <span class="c-live-field-spot c-live-field-spot-top"></span>
        <span class="c-live-field-spot c-live-field-spot-bottom"></span>
        <span class="c-live-field-box c-live-field-box-top"></span>
        <span class="c-live-field-box c-live-field-box-bottom"></span>
        <span class="c-live-field-goal c-live-field-goal-top"></span>
        <span class="c-live-field-goal c-live-field-goal-bottom"></span>

        <?php foreach ($slots as $positionKey => $positionSlots): ?>
            <?php foreach ($positionSlots as $slotIndex => $slot): ?>
                <span class="c-live-field-slot" style="left:<?= (int)$slot['x'] ?>%;top:<?= (int)$slot['y'] ?>%;">
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
            <?php $isSelected = (int)$player['player_id'] === $selectedPlayerId; ?>
            <a
                class="c-live-field-player <?= $isSelected ? 'is-selected' : '' ?>"
                href="<?= PROJECT_URL ?>/admin/partidas/ao_vivo.php?id=<?= (int)$player['match_id'] ?>&player_id=<?= (int)$player['player_id'] ?>"
                style="left:<?= (int)$player['x'] ?>%;top:<?= (int)$player['y'] ?>%;"
            >
                <?= matchLineupAvatar($player['avatar'] ?? null, $player['player_name'] ?? '-', 'c-live-field-avatar') ?>
                <strong><?= htmlspecialchars((string)($player['player_name'] ?? '-')) ?></strong>
            </a>
        <?php endforeach; ?>

        <?php if (empty($fieldPlayers)): ?>
            <div class="c-live-field-empty">Escale jogadores nesta partida para usar o ao vivo.</div>
        <?php endif; ?>
    </div>
    <?php
}

liveScoreEnsureSchema($pdo);

$id = (int)($_GET['id'] ?? 0);
$selectedPlayerId = (int)($_GET['player_id'] ?? 0);
$saved = (string)($_GET['saved'] ?? '');

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
    flash('error', 'Partida nao encontrada.');
    redirect(PROJECT_URL . '/admin/partidas/index.php');
}

if (($match['competition_context'] ?? '') === 'internal') {
    flash('error', 'Ao vivo sera liberado primeiro para partidas externas.');
    redirect(PROJECT_URL . '/admin/partidas/show.php?id=' . (int)$id);
}

$fieldSetup = matchLineupFieldSetupForMatch($pdo, $match);
$slots = $fieldSetup['slots'];

$lineupStmt = $pdo->prepare("
    SELECT ml.*, COALESCE(NULLIF(p.nickname, ''), LEFT(SUBSTRING_INDEX(p.name, ' ', 1), 14)) AS player_name,
           COALESCE(opp.name, pp.name) AS position_name,
           COALESCE(opp.code, pp.code) AS position_code,
           COALESCE(opp.group_key, pp.group_key) AS group_key,
           u.avatar
    FROM match_lineup ml
    INNER JOIN players p ON p.id = ml.player_id
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    LEFT JOIN player_positions opp ON opp.id = ml.override_position_id
    LEFT JOIN project_users u ON u.id = p.user_id
    WHERE ml.match_id = ?
      AND ml.status = 'starter'
      AND COALESCE(ml.lineup_team, 'team_1') = 'team_1'
    ORDER BY ml.slot_group ASC, ml.slot_index ASC, p.name ASC
");
$lineupStmt->execute([$id]);
$lineup = $lineupStmt->fetchAll(PDO::FETCH_ASSOC);

$fieldPlayers = [];
foreach ($lineup as $member) {
    if (isset($slots[$member['slot_group']][(int)$member['slot_index']])) {
        $slot = $slots[$member['slot_group']][(int)$member['slot_index']];
        $fieldPlayers[] = $member + ['x' => $slot['x'], 'y' => $slot['y']];
    }
}

$selectedPlayer = null;
if ($selectedPlayerId > 0) {
    foreach ($fieldPlayers as $player) {
        if ((int)$player['player_id'] === $selectedPlayerId) {
            $selectedPlayer = $player;
            break;
        }
    }
}

$eventsStmt = $pdo->prepare("
    SELECT e.*, COALESCE(NULLIF(p.nickname, ''), LEFT(SUBSTRING_INDEX(p.name, ' ', 1), 14)) AS player_name
    FROM live_match_events e
    INNER JOIN players p ON p.id = e.player_id
    WHERE e.match_id = ?
    ORDER BY e.created_at DESC, e.id DESC
    LIMIT 8
");
$eventsStmt->execute([$id]);
$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

$teamA = (string)($match['participant_a'] ?? 'Meu Time');
$teamB = (string)($match['participant_b'] ?? 'Adversario');
$scoreA = $match['score_a'] !== null ? (int)$match['score_a'] : 0;
$scoreB = $match['score_b'] !== null ? (int)$match['score_b'] : 0;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ao Vivo - <?= htmlspecialchars($teamA . ' x ' . $teamB) ?></title>
    <style>
        :root { color-scheme: dark; --bg:#050817; --panel:#111827; --panel2:#172033; --border:#334155; --text:#f8fafc; --muted:#a7b0c3; --blue:#3b82f6; --green:#22c55e; --field:#106235; }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; background:radial-gradient(circle at top left, rgba(59,130,246,.12), transparent 30%), var(--bg); color:var(--text); font-family:Arial, Helvetica, sans-serif; }
        a { color:inherit; text-decoration:none; }
        button, .c-live-btn { border:1px solid var(--border); border-radius:8px; background:#1f2937; color:var(--text); font-weight:700; padding:10px 14px; cursor:pointer; }
        button:hover, .c-live-btn:hover { border-color:var(--blue); }
        .c-live-shell { width:min(1320px, calc(100vw - 32px)); margin:0 auto; padding:18px 0 24px; }
        .c-live-header { display:grid; grid-template-columns:1fr auto 1fr; gap:16px; align-items:center; margin-bottom:18px; }
        .c-live-title small { display:block; color:var(--muted); margin-top:4px; }
        .c-live-scoreboard { display:grid; grid-template-columns:minmax(120px,1fr) auto minmax(120px,1fr); gap:16px; align-items:center; min-width:min(560px,100%); padding:14px; border:1px solid var(--border); border-radius:10px; background:rgba(17,24,39,.9); text-align:center; }
        .c-live-team { color:var(--muted); font-size:13px; font-weight:700; }
        .c-live-score { display:inline-grid; grid-template-columns:54px 18px 54px; align-items:center; gap:6px; font-size:30px; font-weight:900; }
        .c-live-score span:not(.c-live-separator) { padding:8px 0; border:1px solid #29548d; background:#17315a; border-radius:6px; }
        .c-live-separator { color:var(--muted); font-size:15px; }
        .c-live-actions-top { display:flex; justify-content:flex-end; gap:8px; }
        .c-live-notice { margin-bottom:14px; padding:12px 14px; border-left:3px solid var(--green); background:rgba(34,197,94,.09); }
        .c-live-layout { display:grid; grid-template-columns:minmax(420px,1.45fr) minmax(280px,.75fr); gap:18px; align-items:start; }
        .c-live-panel { border:1px solid var(--border); background:rgba(17,24,39,.82); padding:16px; border-radius:10px; }
        .c-live-panel h2, .c-live-panel h3 { margin:0 0 12px; font-size:17px; }
        .c-live-field { position:relative; width:min(100%,650px); aspect-ratio:9/11.5; margin:0 auto; overflow:hidden; border:1px solid rgba(255,255,255,.24); background:linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px),radial-gradient(circle at 50% 50%,rgba(255,255,255,.05),transparent 28%),var(--field); background-size:12.5% 12.5%,12.5% 12.5%,auto,auto; }
        .c-live-field-line, .c-live-field-circle, .c-live-field-spot, .c-live-field-box, .c-live-field-goal { position:absolute; pointer-events:none; opacity:.58; }
        .c-live-field-mid { left:0; top:50%; width:100%; border-top:1px solid rgba(255,255,255,.7); }
        .c-live-field-circle { left:50%; top:50%; width:28%; aspect-ratio:1; border:1px solid rgba(255,255,255,.7); border-radius:50%; transform:translate(-50%,-50%); }
        .c-live-field-spot { width:6px; height:6px; margin:-3px 0 0 -3px; border-radius:999px; background:rgba(255,255,255,.8); left:50%; }
        .c-live-field-spot-center { top:50%; } .c-live-field-spot-top { top:18%; } .c-live-field-spot-bottom { top:82%; }
        .c-live-field-box { left:28%; width:44%; height:18%; border:1px solid rgba(255,255,255,.7); }
        .c-live-field-box-top { top:0; border-top:0; } .c-live-field-box-bottom { bottom:0; border-bottom:0; }
        .c-live-field-goal { left:42%; width:16%; height:4%; border:1px solid rgba(255,255,255,.7); }
        .c-live-field-goal-top { top:0; border-top:0; } .c-live-field-goal-bottom { bottom:0; border-bottom:0; }
        .c-live-field-slot { position:absolute; transform:translate(-50%,-50%); width:52px; height:52px; display:grid; place-items:center; border:1px dashed rgba(255,255,255,.38); border-radius:999px; color:rgba(255,255,255,.62); font-weight:800; font-size:12px; pointer-events:none; }
        .c-live-field-player { position:absolute; transform:translate(-50%,-50%); display:grid; justify-items:center; width:86px; gap:3px; text-align:center; z-index:2; }
        .c-live-field-player.is-selected .c-live-field-avatar { outline:3px solid var(--blue); box-shadow:0 0 0 7px rgba(59,130,246,.18); }
        .c-live-field-avatar { width:50px; height:50px; border-radius:999px; display:grid; place-items:center; border:2px solid rgba(255,255,255,.85); background:linear-gradient(135deg,#fde3d2 0 38%,#2d8a4c 39%); color:#fff; font-weight:900; object-fit:cover; }
        .c-live-field-player strong { max-width:80px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:11px; text-shadow:0 1px 2px #000; }
        .c-live-field-empty { position:absolute; inset:auto 16px 16px; text-align:center; color:rgba(255,255,255,.7); font-weight:700; }
        .c-live-selected { display:grid; grid-template-columns:auto 1fr; gap:12px; align-items:center; padding:12px; border:1px solid var(--border); background:var(--panel2); margin-bottom:12px; }
        .c-live-selected strong, .c-live-selected span { display:block; } .c-live-selected span { color:var(--muted); margin-top:2px; }
        .c-live-goal-btn { width:100%; border-color:rgba(34,197,94,.7); background:rgba(34,197,94,.14); color:#dcfce7; font-size:16px; }
        .c-live-muted { color:var(--muted); line-height:1.5; }
        .c-live-events { margin-top:14px; display:grid; gap:8px; }
        .c-live-event { display:flex; justify-content:space-between; gap:10px; padding:10px; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); }
        .c-live-event small { color:var(--muted); }
        @media (max-width:900px) {
            .c-live-shell { width:min(100vw - 20px,640px); padding-top:12px; }
            .c-live-header, .c-live-layout, .c-live-scoreboard { grid-template-columns:1fr; }
            .c-live-actions-top { justify-content:flex-start; flex-wrap:wrap; }
            .c-live-score { margin:0 auto; }
            .c-live-field { width:min(100%,430px); }
        }
    </style>
</head>
<body>
    <main class="c-live-shell">
        <header class="c-live-header">
            <div class="c-live-title">
                <strong>Ao vivo</strong>
                <small><?= htmlspecialchars((string)($match['competition_name'] ?? 'Partida')) ?></small>
            </div>

            <section class="c-live-scoreboard" aria-label="Placar">
                <div class="c-live-team"><?= htmlspecialchars($teamA) ?></div>
                <div class="c-live-score">
                    <span><?= $scoreA ?></span>
                    <small class="c-live-separator">x</small>
                    <span><?= $scoreB ?></span>
                </div>
                <div class="c-live-team"><?= htmlspecialchars($teamB) ?></div>
            </section>

            <div class="c-live-actions-top">
                <a class="c-live-btn" href="<?= PROJECT_URL ?>/admin/partidas/show.php?id=<?= (int)$id ?>">Ver partida</a>
                <a class="c-live-btn" href="<?= PROJECT_URL ?>/admin/partidas/index.php">Voltar</a>
            </div>
        </header>

        <?php if ($saved === 'goal'): ?>
            <div class="c-live-notice">Gol registrado e placar atualizado.</div>
        <?php endif; ?>

        <div class="c-live-layout">
            <section class="c-live-panel">
                <h2>Campo</h2>
                <?php liveScoreRenderField($slots, $fieldPlayers, $selectedPlayerId); ?>
            </section>

            <aside class="c-live-panel">
                <h3>Acoes rapidas</h3>

                <?php if ($selectedPlayer): ?>
                    <div class="c-live-selected">
                        <?= matchLineupAvatar($selectedPlayer['avatar'] ?? null, $selectedPlayer['player_name'] ?? '-', 'c-live-field-avatar') ?>
                        <div>
                            <strong><?= htmlspecialchars((string)$selectedPlayer['player_name']) ?></strong>
                            <span><?= htmlspecialchars(matchLineupPositionDisplay((string)($selectedPlayer['position_code'] ?? '-'))) ?></span>
                        </div>
                    </div>

                    <form action="<?= PROJECT_URL ?>/admin/partidas/ao_vivo_goal.php" method="POST">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="match_id" value="<?= (int)$id ?>">
                        <input type="hidden" name="player_id" value="<?= (int)$selectedPlayer['player_id'] ?>">
                        <button type="submit" class="c-live-goal-btn">Registrar gol</button>
                    </form>
                <?php else: ?>
                    <p class="c-live-muted">Selecione um jogador no campo para liberar a acao de gol.</p>
                <?php endif; ?>

                <div class="c-live-events">
                    <h3>Ultimos registros</h3>
                    <?php if (empty($events)): ?>
                        <p class="c-live-muted">Nenhum gol registrado ainda.</p>
                    <?php else: ?>
                        <?php foreach ($events as $event): ?>
                            <div class="c-live-event">
                                <strong><?= htmlspecialchars(liveScoreEventLabel((string)$event['event_type'])) ?> - <?= htmlspecialchars((string)$event['player_name']) ?></strong>
                                <small><?= htmlspecialchars((string)$event['created_at']) ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </main>
</body>
</html>
