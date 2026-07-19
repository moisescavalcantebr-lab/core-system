<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectAdmin();

$title = 'Novo ' . participantLabel();
$participantSingular = participantLabel();
$participant = ['status' => 'active'];

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title"><?= htmlspecialchars($title) ?></h1>
            <p class="c-page-subtitle">Cadastre o <?= htmlspecialchars(strtolower($participantSingular)) ?> e o acesso vinculado.</p>
        </div>
        <a href="<?= participantAdminUrl() ?>" class="c-btn-secondary">Voltar</a>
    </div>

    <form action="<?= participantAdminUrl('store.php') ?>" method="POST">
        <?= csrf_field(); ?>
        <?php require __DIR__ . '/form.php'; ?>
    </form>
</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
