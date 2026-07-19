<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectAdmin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT p.*, u.email, u.username
    FROM participants p
    LEFT JOIN project_users u ON u.id = p.user_id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$participant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$participant) {
    exit('Participante nao encontrado.');
}

$title = 'Editar ' . participantLabel();

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title"><?= htmlspecialchars($title) ?></h1>
            <p class="c-page-subtitle"><?= htmlspecialchars((string)$participant['name']) ?></p>
        </div>
        <a href="<?= participantAdminUrl() ?>" class="c-btn-secondary">Voltar</a>
    </div>

    <form action="<?= participantAdminUrl('update.php?id=' . (int)$participant['id']) ?>" method="POST">
        <?= csrf_field(); ?>
        <?php require __DIR__ . '/form.php'; ?>
    </form>
</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
