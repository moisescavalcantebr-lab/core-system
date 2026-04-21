<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap/bootstrap.php';
file_put_contents(__DIR__.'/api_called.txt', date('Y-m-d H:i:s'));
header('Content-Type: application/json');

/* =========================
   VALIDAR API KEY
========================= */

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

if (!isset($config['api_key']) || $apiKey !== $config['api_key']) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

/* =========================
   VALIDAR INPUT
========================= */

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

$email     = trim($input['email'] ?? '');
$projectId = (int)($input['project_id'] ?? 0);

if (!$email || !$projectId) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}
/* =========================
   GERAR TOKEN
========================= */

$token   = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', time() + 3600);

/* Invalidar tokens antigos */

$stmt = $pdo->prepare("
    UPDATE project_access_tokens
    SET used = 1
    WHERE project_id = :project_id
    AND email = :email
");

$stmt->execute([
    'project_id' => $projectId,
    'email'      => $email
]);

/* Inserir novo token */

$stmt = $pdo->prepare("
    INSERT INTO project_access_tokens
    (project_id, email, token, expires_at, used, created_at)
    VALUES
    (:project_id, :email, :token, :expires_at, 0, NOW())
");

$stmt->execute([
    'project_id' => $projectId,
    'email'      => $email,
    'token'      => $token,
    'expires_at' => $expires
]);

/* Buscar path do projeto */

$stmt = $pdo->prepare("SELECT path FROM projects WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $projectId]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    http_response_code(404);
    echo json_encode(['error' => 'Projeto não encontrado']);
    exit;
}

/* Montar link */

$resetLink = "https://lojasmarim.com{$project['path']}/public/create-password.php?token={$token}";

/* =========================
   ENVIAR EMAIL
========================= */

if (!class_exists('MailService')) {
    file_put_contents(__DIR__.'/mail_error.txt', 'Classe não carregou');
}

$html = "
<h2>Recuperação de Senha</h2>
<p>Clique abaixo para redefinir sua senha:</p>
<p>
    <a href='{$resetLink}'
       style='background:#2563eb;color:#fff;padding:10px 20px;
              text-decoration:none;border-radius:6px;'>
        Redefinir Senha
    </a>
</p>
<p>Este link expira em 1 hora.</p>
";

$result = MailService::send(
    $email,
    'Recuperação de Senha',
    $html
);

if (!$result) {
    file_put_contents(__DIR__.'/mail_failed.txt', 'Falhou envio');
}echo json_encode(['success' => true]);