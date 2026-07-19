<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/services/projects/ProjectProvisioner.php';

requireAdmin();

$projectId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT p.*, b.name AS base_name
    FROM projects p
    LEFT JOIN bases b ON b.id = p.base_id
    WHERE p.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $projectId]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    http_response_code(404);
    exit('Projeto não encontrado.');
}

$databaseName = ProjectProvisioner::projectDatabaseName((string)$project['slug']);
$configPath = ROOT_PATH . '/' . ltrim((string)$project['path'], '/') . '/app/config/database.php';

if (is_file($configPath)) {
    try {
        $dbConfig = require $configPath;
        $databaseName = (string)($dbConfig['name'] ?? $databaseName);
    } catch (Throwable $e) {
    }
}

$title = 'Excluir Projeto';
$backUrl = '/web/admin/bases/projects.php?id=' . (int)$project['base_id'];

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Excluir por completo</h1>
            <p class="c-page-subtitle">Ação manual para limpeza administrativa</p>
        </div>

        <div class="c-page-actions">
            <a href="<?= $backUrl ?>" class="c-btn-secondary">Voltar</a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-card c-project-force-delete">
            <div>
                <h3><?= htmlspecialchars((string)$project['name']) ?></h3>
                <p>Use apenas para testes, projetos abandonados ou correção de exclusão que não concluiu.</p>
            </div>

            <div class="c-project-force-delete-grid">
                <div><span>Slug</span><strong><?= htmlspecialchars((string)$project['slug']) ?></strong></div>
                <div><span>Base</span><strong><?= htmlspecialchars((string)($project['base_name'] ?? '-')) ?></strong></div>
                <div><span>Status</span><strong><?= htmlspecialchars((string)$project['status']) ?></strong></div>
                <div><span>Banco</span><strong><?= htmlspecialchars($databaseName) ?></strong></div>
                <div><span>Arquivos</span><strong><?= htmlspecialchars((string)$project['path']) ?></strong></div>
                <div><span>Cliente</span><strong><?= htmlspecialchars((string)$project['owner_email']) ?></strong></div>
            </div>

            <div class="c-project-force-delete-alert">
                Esta ação remove arquivos, banco e registro do projeto. Ela não pode ser desfeita.
            </div>

            <form method="post" action="/app/actions/projects/force_delete.php" class="c-project-force-delete-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$project['id'] ?>">

                <div class="c-form-group">
                    <label>Digite o slug para confirmar</label>
                    <input class="c-input" name="confirm_slug" required autocomplete="off"
                           placeholder="<?= htmlspecialchars((string)$project['slug']) ?>">
                </div>

                <button class="c-btn-danger">Excluir projeto e banco</button>
            </form>
        </div>
    </div>
</div>

<style>
.c-project-force-delete {
    display: grid;
    gap: 16px;
    max-width: 920px;
}

.c-project-force-delete p {
    margin-bottom: 0;
}

.c-project-force-delete-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.c-project-force-delete-grid div {
    min-width: 0;
    padding: 11px;
    border: 1px solid var(--border-color);
    background: color-mix(in srgb, var(--bg-card) 88%, var(--bg-hover));
}

.c-project-force-delete-grid span,
.c-project-force-delete-grid strong {
    display: block;
}

.c-project-force-delete-grid span {
    margin-bottom: 5px;
    color: var(--text-secondary);
    font-size: 11px;
}

.c-project-force-delete-grid strong {
    overflow: hidden;
    text-overflow: ellipsis;
}

.c-project-force-delete-alert {
    padding: 12px;
    border: 1px solid color-mix(in srgb, var(--danger-color, #ef4444) 40%, var(--border-color));
    background: color-mix(in srgb, var(--danger-color, #ef4444) 12%, transparent);
    color: var(--danger-color, #ef4444);
    font-weight: 700;
}

.c-project-force-delete-form {
    display: grid;
    gap: 10px;
    max-width: 440px;
}

@media (max-width: 760px) {
    .c-project-force-delete-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
$content = ob_get_clean();
$rightSidebarEnabled = false;
require APP_PATH . '/views/layout_admin.php';
