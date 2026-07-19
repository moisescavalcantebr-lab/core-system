<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

$competitionId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM competitions WHERE id = ? LIMIT 1");
$stmt->execute([$competitionId]);
$competition = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$competition) {
    http_response_code(404);
    exit('Competicao nao encontrada.');
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM competition_rules WHERE competition_id = ?");
$stmt->execute([$competitionId]);
$nextOrder = (int)$stmt->fetchColumn() + 1;

$title = 'Adicionar Regra';

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Adicionar Regra</h1>
            <p class="c-page-subtitle"><?= htmlspecialchars((string)$competition['name']) ?></p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/competicoes/rules.php?id=<?= (int)$competition['id'] ?>" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <form action="<?= PROJECT_URL ?>/admin/competicoes/rule_store.php?id=<?= (int)$competition['id'] ?>" method="POST" class="c-card">
            <?= csrf_field(); ?>

            <div class="c-form-grid">
                <div class="c-form-group">
                    <label>Titulo</label>
                    <input type="text" name="title" class="c-input" required>
                </div>

                <div class="c-form-group">
                    <label>Ordem</label>
                    <input type="number" name="sort_order" class="c-input" min="0" value="<?= $nextOrder ?>">
                </div>

                <div class="c-form-group">
                    <label>Status</label>
                    <select name="status" class="c-input">
                        <option value="active">Ativa</option>
                        <option value="inactive">Inativa</option>
                    </select>
                </div>
            </div>

            <div class="c-form-group">
                <label>Descricao</label>
                <textarea name="description" class="c-input" rows="4"></textarea>
            </div>

            <button class="c-btn-secondary">Salvar Regra</button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
