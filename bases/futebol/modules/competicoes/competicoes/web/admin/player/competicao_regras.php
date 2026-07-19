<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAuth();

$user = projectUser();
$stmt = $pdo->prepare("SELECT id FROM players WHERE user_id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$currentPlayerId = (int)($stmt->fetchColumn() ?: 0);

if ($currentPlayerId <= 0) {
    http_response_code(403);
    exit('Seu usuario ainda nao esta vinculado a um jogador.');
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM competitions WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$competition = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$competition) {
    http_response_code(404);
    exit('Competicao nao encontrada.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM competition_rules
    WHERE competition_id = ?
      AND status = 'active'
    ORDER BY sort_order ASC, id ASC
");
$stmt->execute([$id]);
$rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = 'Regras da Competicao';
ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Regras</h1>
            <p class="c-page-subtitle"><?= htmlspecialchars((string)$competition['name']) ?></p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/player/competicao.php?id=<?= (int)$competition['id'] ?>" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">
        <div class="c-card">
            <h3>Topicos</h3>

            <?php if (empty($rules)): ?>
                <p>Nenhuma regra publicada.</p>
            <?php else: ?>
                <div class="c-player-rules-list">
                    <?php foreach ($rules as $rule): ?>
                        <div class="c-player-rule-topic">
                            <div>
                                <span><?= (int)$rule['sort_order'] ?></span>
                                <strong><?= htmlspecialchars($rule['title']) ?></strong>
                            </div>
                            <?php if (!empty($rule['description'])): ?>
                                <p><?= nl2br(htmlspecialchars((string)$rule['description'])) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.c-player-rules-list {
    display: grid;
    gap: 10px;
}

.c-player-rule-topic {
    border: 1px solid rgba(148, 163, 184, .24);
    background: rgba(15, 23, 42, .34);
    padding: 12px;
}

.c-player-rule-topic > div {
    display: flex;
    gap: 10px;
    align-items: center;
}

.c-player-rule-topic span {
    display: inline-grid;
    place-items: center;
    min-width: 26px;
    height: 26px;
    border: 1px solid rgba(148, 163, 184, .32);
    background: rgba(30, 41, 59, .66);
    color: rgba(226, 232, 240, .78);
    font-size: 12px;
    font-weight: 700;
}

.c-player-rule-topic p {
    margin: 10px 0 0;
    color: rgba(226, 232, 240, .76);
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
