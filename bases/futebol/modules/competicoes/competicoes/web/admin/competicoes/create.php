<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/plan_fallback.php';

requireProjectAdmin();

$title = 'Criar Competicao';
$formAction = PROJECT_URL . '/admin/competicoes/store.php';
$submitLabel = 'Criar Competicao';

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Criar Competicao</h1>
            <p class="c-page-subtitle">Defina contexto, tipo e período</p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/competicoes/index.php" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>
        <?php require __DIR__ . '/form.php'; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
