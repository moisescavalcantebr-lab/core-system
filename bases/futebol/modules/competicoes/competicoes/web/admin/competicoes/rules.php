<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

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

        <div>
            <a href="<?= PROJECT_URL ?>/admin/competicoes/rule_create.php?id=<?= (int)$competition['id'] ?>" class="c-btn-secondary">
                Adicionar Regra
            </a>
            <a href="<?= PROJECT_URL ?>/admin/competicoes/view.php?id=<?= (int)$competition['id'] ?>" class="c-btn-secondary">
                Voltar
            </a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-card">
            <h3>Topicos</h3>

            <?php if (empty($rules)): ?>
                <p>Nenhuma regra cadastrada.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Ordem</th>
                                <th>Regra</th>
                                <th>Descricao</th>
                                <th>Status</th>
                                <th>Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rules as $rule): ?>
                                <tr>
                                    <td><?= (int)$rule['sort_order'] ?></td>
                                    <td><strong><?= htmlspecialchars($rule['title']) ?></strong></td>
                                    <td><?= htmlspecialchars($rule['description'] ?? '-') ?></td>
                                    <td>
                                        <span class="c-badge <?= $rule['status'] === 'active' ? 'c-badge--success' : 'c-badge--neutral' ?>">
                                            <?= $rule['status'] === 'active' ? 'Ativa' : 'Inativa' ?>
                                        </span>
                                    </td>
                                    <td class="c-rule-actions-cell">
                                        <div class="c-rule-actions">
                                            <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/competicoes/rule_edit.php?id=<?= (int)$rule['id'] ?>">
                                                Editar
                                            </a>
                                            <form action="<?= PROJECT_URL ?>/admin/competicoes/rule_delete.php?id=<?= (int)$rule['id'] ?>&competition_id=<?= (int)$competition['id'] ?>" method="POST" onsubmit="return confirm('Excluir esta regra?');">
                                                <?= csrf_field(); ?>
                                                <button class="c-btn-secondary">Excluir</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.c-rule-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: flex-start;
    min-width: 148px;
}

.c-rule-actions-cell {
    width: 160px;
    vertical-align: top;
}

.c-rule-actions form {
    margin: 0;
    display: inline-flex;
}

.c-rule-actions .c-btn-secondary {
    white-space: nowrap;
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
