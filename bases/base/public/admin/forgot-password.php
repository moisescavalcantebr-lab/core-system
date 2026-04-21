<?php
require __DIR__ . '/../../app/bootstrap/project_bootstrap.php';

/* =========================
PROCESSAMENTO
========================= */

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $email = strtolower(trim($_POST['email'] ?? ''));

    if ($email) {

        $stmt = $pdo->prepare("
            SELECT id FROM project_users
            WHERE email = :email
            LIMIT 1
        ");

        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user) {

            $payload = [
                'email'      => $email,
                'project_id' => $project['id']
            ];

            $ch = curl_init('https://lojasmarim.com/public/api/create-token.php');

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-API-KEY: ' . $project['core_api_key']
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
            ]);

            curl_exec($ch);
            curl_close($ch);
        }

        $success = true;
    }
}

$title = 'Recuperar Senha';

ob_start();
?>

<div class="c-auth-layout">
<div class="c-auth-card">

    <h1 class="c-auth-title">Recuperar Senha</h1>

    <p class="c-auth-subtitle">
        Informe seu email para receber as instruções.
    </p>

    <?php if ($success): ?>
        <div class="c-alert c-alert--success">
            Se o email existir, enviamos instruções.
        </div>
    <?php endif; ?>

    <form method="post" class="c-auth-form">

        <?= csrf_field(); ?>

        <div class="c-auth-input">
            <input type="email"
                   name="email"
                   placeholder="Seu email"
                   required>
        </div>

        <button class="c-auth-btn c-btn-block">
            Enviar
        </button>

    </form>

    <div class="c-auth-link">
        <a href="<?= PROJECT_URL ?>/admin/login.php">
            Voltar para login
        </a>
    </div>

</div>
</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_auth.php';