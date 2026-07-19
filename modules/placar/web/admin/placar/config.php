<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

$scoreboardId = (int)($_GET['id'] ?? 0);

if ($scoreboardId <= 0) {
    $scoreboard = $pdo->query("
        SELECT *
        FROM scoreboards
        ORDER BY status = 'live' DESC, id DESC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT * FROM scoreboards WHERE id = ? LIMIT 1");
    $stmt->execute([$scoreboardId]);
    $scoreboard = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$scoreboard) {
    header('Location: ' . PROJECT_URL . '/admin/placar/index.php');
    exit;
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

$home = $items[0] ?? ['label' => 'Casa'];
$away = $items[1] ?? ['label' => 'Visitante'];

$title = 'Configurar Placar';

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Configurar Placar</h1>
            <p class="c-page-subtitle">Nomes, status e reset do placar</p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/placar/index.php" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <form action="<?= PROJECT_URL ?>/admin/placar/save.php" method="POST" class="c-card">
            <?= csrf_field(); ?>
            <input type="hidden" name="scoreboard_id" value="<?= $scoreboardId ?>">

            <div class="c-form-group">
                <label>Titulo</label>
                <input type="text" name="title" class="c-input" value="<?= htmlspecialchars($scoreboard['title']) ?>">
            </div>

            <div class="c-form-grid">
                <div class="c-form-group">
                    <label>Time 1</label>
                    <input type="text" name="home_label" class="c-input" value="<?= htmlspecialchars($home['label']) ?>">
                </div>

                <div class="c-form-group">
                    <label>Time 2</label>
                    <input type="text" name="away_label" class="c-input" value="<?= htmlspecialchars($away['label']) ?>">
                </div>
            </div>

            <div class="c-form-grid">
                <div class="c-form-group">
                    <label>Status</label>
                    <select name="status" class="c-input">
                        <option value="draft" <?= $scoreboard['status'] === 'draft' ? 'selected' : '' ?>>Rascunho</option>
                        <option value="live" <?= $scoreboard['status'] === 'live' ? 'selected' : '' ?>>Ao vivo</option>
                        <option value="finished" <?= $scoreboard['status'] === 'finished' ? 'selected' : '' ?>>Finalizado</option>
                        <option value="canceled" <?= $scoreboard['status'] === 'canceled' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>

                <div class="c-form-group">
                    <label>Resetar gols</label>
                    <select name="reset_scores" class="c-input">
                        <option value="0">Nao</option>
                        <option value="1">Sim, voltar para 0 x 0</option>
                    </select>
                </div>
            </div>

            <button class="c-btn-secondary">Salvar Placar</button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
