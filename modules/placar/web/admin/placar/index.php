<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

$title = 'Placar';

$scoreboard = $pdo->query("
    SELECT *
    FROM scoreboards
    ORDER BY status = 'live' DESC, id DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$scoreboard) {
    $pdo->prepare("
        INSERT INTO scoreboards (title, mode, status, started_at)
        VALUES ('Placar Principal', 'match', 'live', NOW())
    ")->execute();

    $scoreboardId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO scoreboard_items (scoreboard_id, label, score, sort_order)
        VALUES (?, ?, 0, ?)
    ");
    $stmt->execute([$scoreboardId, 'Casa', 1]);
    $stmt->execute([$scoreboardId, 'Visitante', 2]);

    $scoreboard = $pdo->query("SELECT * FROM scoreboards WHERE id = {$scoreboardId}")->fetch(PDO::FETCH_ASSOC);
}

$scoreboardId = (int)$scoreboard['id'];

$stmt = $pdo->prepare("
    SELECT *
    FROM scoreboard_items
    WHERE scoreboard_id = ?
    ORDER BY sort_order ASC, id ASC
    LIMIT 2
");
$stmt->execute([$scoreboardId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

while (count($items) < 2) {
    $label = count($items) === 0 ? 'Casa' : 'Visitante';
    $order = count($items) + 1;

    $stmt = $pdo->prepare("
        INSERT INTO scoreboard_items (scoreboard_id, label, score, sort_order)
        VALUES (?, ?, 0, ?)
    ");
    $stmt->execute([$scoreboardId, $label, $order]);

    $stmt = $pdo->prepare("
        SELECT *
        FROM scoreboard_items
        WHERE scoreboard_id = ?
        ORDER BY sort_order ASC, id ASC
        LIMIT 2
    ");
    $stmt->execute([$scoreboardId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$home = $items[0];
$away = $items[1];

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Placar</h1>
            <p class="c-page-subtitle">Placar simples e ajustavel em tempo real</p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/placar/config.php?id=<?= $scoreboardId ?>" class="c-btn-secondary">
            Configurar
        </a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-card">
            <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:18px;align-items:center;text-align:center;">
                <div>
                    <h3 id="homeLabel" style="margin-bottom:12px;"><?= htmlspecialchars($home['label']) ?></h3>
                    <div id="homeScore" style="font-size:72px;font-weight:800;line-height:1;">
                        <?= (int)$home['score'] ?>
                    </div>
                    <div style="margin-top:16px;">
                        <button class="c-btn-secondary js-score" data-item="<?= (int)$home['id'] ?>" data-delta="1">+1</button>
                        <button class="c-btn-secondary js-score" data-item="<?= (int)$home['id'] ?>" data-delta="-1">-1</button>
                    </div>
                </div>

                <div style="font-size:42px;font-weight:800;">x</div>

                <div>
                    <h3 id="awayLabel" style="margin-bottom:12px;"><?= htmlspecialchars($away['label']) ?></h3>
                    <div id="awayScore" style="font-size:72px;font-weight:800;line-height:1;">
                        <?= (int)$away['score'] ?>
                    </div>
                    <div style="margin-top:16px;">
                        <button class="c-btn-secondary js-score" data-item="<?= (int)$away['id'] ?>" data-delta="1">+1</button>
                        <button class="c-btn-secondary js-score" data-item="<?= (int)$away['id'] ?>" data-delta="-1">-1</button>
                    </div>
                </div>
            </div>

            <div style="margin-top:18px;text-align:center;">
                <span id="scoreStatus" class="c-badge <?= $scoreboard['status'] === 'live' ? 'c-badge--success' : 'c-badge--neutral' ?>">
                    <?= htmlspecialchars($scoreboard['status']) ?>
                </span>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const token = '<?= csrf_token() ?>';
    const liveUrl = '<?= PROJECT_URL ?>/admin/placar/live.php?id=<?= $scoreboardId ?>';
    const updateUrl = '<?= PROJECT_URL ?>/admin/placar/update_score.php';

    function applyScore(data) {
        if (!data || !data.items || data.items.length < 2) return;

        document.getElementById('homeLabel').textContent = data.items[0].label;
        document.getElementById('homeScore').textContent = parseInt(data.items[0].score, 10);
        document.getElementById('awayLabel').textContent = data.items[1].label;
        document.getElementById('awayScore').textContent = parseInt(data.items[1].score, 10);
        document.getElementById('scoreStatus').textContent = data.status;
    }

    async function refreshScore() {
        const response = await fetch(liveUrl, { headers: { 'Accept': 'application/json' } });
        if (!response.ok) return;
        applyScore(await response.json());
    }

    document.querySelectorAll('.js-score').forEach((button) => {
        button.addEventListener('click', async () => {
            const form = new FormData();
            form.append('_csrf', token);
            form.append('item_id', button.dataset.item);
            form.append('delta', button.dataset.delta);

            const response = await fetch(updateUrl, { method: 'POST', body: form });
            if (response.ok) {
                applyScore(await response.json());
            }
        });
    });

    setInterval(refreshScore, 2000);
});
</script>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
