<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../app/bootstrap/project_bootstrap.php';

/* =========================
CHECK ADMIN EXISTE
========================= */

$adminExists = false;

try {
    $adminExists = $pdo->query("
        SELECT COUNT(*) 
        FROM project_users 
        WHERE role = 'ADMIN'
    ")->fetchColumn() > 0;
} catch (Throwable $e) {}

/* =========================
FLUXO
========================= */

// PRIMEIRO ACESSO
if (!$adminExists) {
    header('Location: ' . PROJECT_URL . '/create-password.php');
    exit;
}

// JÁ LOGADO
if (!empty($_SESSION['project_user_id'])) {
    header('Location: ' . PROJECT_URL . '/admin/dashboard.php');
    exit;
}

/* =========================
VIEW
========================= */

$title = $project['name'] ?? 'Projeto';

ob_start();
?>

<div class="c-auth-layout">

    <div class="c-auth-card">

        <h1 class="c-auth-title">
            <?= htmlspecialchars($title) ?>
        </h1>

        <p class="c-auth-subtitle">
            Bem-vindo ao seu projeto.<br>
            Gerencie conteúdos e administre com facilidade.
        </p>

        <div class="c-auth-actions">
            <a href="<?= PROJECT_URL ?>/admin/login.php" class="c-btn-secondary c-btn-block">
                Acessar Painel
            </a>
        </div>

    </div>

</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_auth.php';