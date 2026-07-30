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
                'project_id' => $project['id'],
                'purpose'    => 'reset',
            ];

            $coreConfigPath = dirname(__DIR__, 4) . '/env/env.production.php';
            $coreConfig = file_exists($coreConfigPath) ? require $coreConfigPath : [];
            $coreUrl = rtrim((string)($coreConfig['app_url'] ?? 'https://meuprojetoweb.com'), '/');
            $ch = curl_init($coreUrl . '/web/api/create-token.php');

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-API-KEY: ' . $project['core_api_key']
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
            ]);

            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($curlError || $httpCode < 200 || $httpCode >= 300) {
                error_log('Falha ao solicitar token no Core: HTTP ' . $httpCode . ' ' . $curlError);
            } else {
                $apiResult = json_decode((string)$response, true);
                if (!is_array($apiResult) || empty($apiResult['success']) || empty($apiResult['mail_sent'])) {
                    error_log('Token solicitado, mas email nao foi confirmado pelo Core: ' . substr((string)$response, 0, 500));
                }
            }
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
