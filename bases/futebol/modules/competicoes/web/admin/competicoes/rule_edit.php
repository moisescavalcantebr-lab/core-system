<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT r.*, c.name AS competition_name
    FROM competition_rules r
    INNER JOIN competitions c ON c.id = r.competition_id
    WHERE r.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$rule = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rule) {
    http_response_code(404);
    exit('Regra nao encontrada.');
}

$title = 'Editar Regra';

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Editar Regra</h1>
            <p class="c-page-subtitle"><?= htmlspecialchars((string)$rule['competition_name']) ?></p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/competicoes/rules.php?id=<?= (int)$rule['competition_id'] ?>" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <form action="<?= PROJECT_URL ?>/admin/competicoes/rule_update.php?id=<?= (int)$rule['id'] ?>" method="POST" class="c-card">
            <?= csrf_field(); ?>

            <div class="c-form-grid">
                <div class="c-form-group">
                    <label>Titulo</label>
                    <input type="text" name="title" class="c-input" value="<?= htmlspecialchars((string)$rule['title']) ?>" required>
                </div>

                <div class="c-form-group">
                    <label>Ordem</label>
                    <input type="number" name="sort_order" class="c-input" min="0" value="<?= (int)$rule['sort_order'] ?>">
                </div>

                <div class="c-form-group">
                    <label>Status</label>
                    <select name="status" class="c-input">
                        <option value="active" <?= $rule['status'] === 'active' ? 'selected' : '' ?>>Ativa</option>
                        <option value="inactive" <?= $rule['status'] === 'inactive' ? 'selected' : '' ?>>Inativa</option>
                    </select>
                </div>
            </div>

            <div class="c-form-group">
                <label>Descricao</label>
                <textarea name="description" class="c-input" rows="4"><?= htmlspecialchars((string)($rule['description'] ?? '')) ?></textarea>
            </div>

            <button class="c-btn-secondary">Salvar Regra</button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
