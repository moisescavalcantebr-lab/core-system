<?php
declare(strict_types=1);

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../app/bootstrap/project_bootstrap.php';

/* =========================
   VALIDAR TOKEN
========================= */

$token = $_GET['token'] ?? '';

if (!$token) {
    http_response_code(400);
    die('Token inválido.');
}

/* =========================
   CHAMAR API DO CORE
========================= */

$coreConfigPath = dirname(__DIR__, 3) . '/env/env.production.php';
$coreConfig = file_exists($coreConfigPath) ? require $coreConfigPath : [];
$coreUrl = rtrim($coreConfig['app_url'] ?? 'https://lojasmarim.com', '/');
$coreApiUrl = str_replace('http://localhost:8000', 'http://127.0.0.1', $coreUrl);

$response = file_get_contents(
    $coreApiUrl . "/web/api/validate-token.php",
    false,
    stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-type: application/x-www-form-urlencoded",
            'content' => http_build_query(['token' => $token])
        ]
    ])
);

if ($response === false) {
    http_response_code(502);
    die('Não foi possível validar o token no core.');
}

$data = json_decode($response, true);

if (!$data || empty($data['valid'])) {
    http_response_code(403);
    die('Token inválido ou expirado.');
}

/* =========================
   VALIDAR SE TOKEN É DO PROJETO
========================= */

$projectConfig = json_decode(
    file_get_contents(dirname(__DIR__) . '/project.json'),
    true
);

if ((int)$data['project_id'] !== (int)$projectConfig['id']) {
    http_response_code(403);
    die('Token não pertence a este projeto.');
}

$email = $data['email'];

/* =========================
   PROCESSAR FORMULÁRIO
========================= */

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $passwordRaw = $_POST['password'] ?? '';

    if (strlen($passwordRaw) < 6) {
        $error = "A senha deve ter pelo menos 6 caracteres.";
    } else {

        $password = password_hash($passwordRaw, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            SELECT id FROM project_users
            WHERE email = :email
        ");
        $stmt->execute(['email' => $email]);
        $exists = $stmt->fetchColumn();

        if ($exists) {

            $pdo->prepare("
                UPDATE project_users
                SET password = :password
                WHERE email = :email
            ")->execute([
                'password' => $password,
                'email' => $email
            ]);

        } else {

            $pdo->prepare("
                INSERT INTO project_users
                (name, email, password, role)
                VALUES
                ('Administrador', :email, :password, 'ADMIN')
            ")->execute([
                'email' => $email,
                'password' => $password
            ]);
        }

        /* Marcar token como usado */

        file_get_contents(
            $coreApiUrl . "/web/api/mark-token-used.php",
            false,
            stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-type: application/x-www-form-urlencoded",
                    'content' => http_build_query(['token' => $token])
                ]
            ])
        );

        header('Location: index.php');
        exit;
    }
}

/* =========================
   VIEW
========================= */

$title = 'Criar Senha';

ob_start();
?>

<div class="c-auth-layout">
<div class="c-auth-card">

    <h1 class="c-auth-title">
        <?= htmlspecialchars($project['name']) ?>
    </h1>

    <p class="c-auth-subtitle">
        Defina sua senha para acessar o painel administrativo.
    </p>

    <?php if ($error): ?>
        <div class="c-alert c-alert--error mb-10">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="c-auth-form">

        <div class="c-auth-input">
            <input type="password"
                   name="password"
                   placeholder="Nova senha"
                   required>
        </div>

        <button class="c-auth-btn c-btn-block">
            Criar Senha
        </button>

    </form>

</div>
</div>
<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_auth.php';
