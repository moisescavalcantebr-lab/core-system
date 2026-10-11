<?php
require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require APP_PATH . '/helpers/password.php';

$token = $_GET['token'] ?? '';
$reset = validatePasswordToken($token);

if (!$reset) {
    die('Token inválido ou expirado.');
}

$success = false;
$error = null;

/* =========================
PROCESSAMENTO
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm) {
        $error = 'As senhas não conferem.';
    } elseif (strlen($password) < 6) {
        $error = 'A senha deve ter no mínimo 6 caracteres.';
    } else {

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            UPDATE project_users
            SET password = :password
            WHERE id = :id
        ");

        $stmt->execute([
            'password' => $hash,
            'id'       => $reset['user_id']
        ]);

        markTokenUsed($reset['id']);

        $success = true;
    }
}

$title = 'Redefinir Senha';

ob_start();
?>

<div class="c-auth-layout">
<div class="c-auth-card">

    <h1 class="c-auth-title">Nova Senha</h1>

    <p class="c-auth-subtitle">
        Defina uma nova senha para sua conta.
    </p>

    <?php if ($error): ?>
        <div class="c-alert c-alert--error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="c-alert c-alert--success">
            Senha alterada com sucesso.
        </div>

        <div class="c-auth-link">
            <a href="<?= PROJECT_URL ?>/admin/login.php">
                Ir para login
            </a>
        </div>

    <?php else: ?>

    <form method="post" class="c-auth-form">

        <?= csrf_field(); ?>

        <div class="c-auth-input">
            <input type="password"
                   name="password"
                   placeholder="Nova senha"
                   required>
        </div>

        <div class="c-auth-input">
            <input type="password"
                   name="confirm_password"
                   placeholder="Confirmar senha"
                   required>
        </div>

        <button class="c-auth-btn c-btn-block">
            Alterar Senha
        </button>

    </form>

    <?php endif; ?>

</div>
</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_auth.php';