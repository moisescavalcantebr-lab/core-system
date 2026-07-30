<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap/bootstrap.php';
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

$email     = trim((string)($input['email'] ?? ''));
$projectId = (int)($input['project_id'] ?? 0);
$purpose = strtolower(trim((string)($input['purpose'] ?? $input['mode'] ?? 'reset')));

if (!$email || !$projectId || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}

if (!in_array($purpose, ['access', 'reset'], true)) {
    $purpose = 'reset';
}

/* =========================
   GERAR TOKEN
========================= */

$token   = bin2hex(random_bytes(32));
$ttlSeconds = $purpose === 'access' ? 86400 : 3600;
$expires = date('Y-m-d H:i:s', time() + $ttlSeconds);

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

/* Buscar projeto */

$stmt = $pdo->prepare("SELECT name, path FROM projects WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $projectId]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    http_response_code(404);
    echo json_encode(['error' => 'Projeto não encontrado']);
    exit;
}

/* Montar link */

$baseUrl = rtrim((string)($config['app_url'] ?? ''), '/');
if ($baseUrl === '') {
    http_response_code(500);
    echo json_encode(['error' => 'URL do sistema não configurada']);
    exit;
}

$resetLink = $baseUrl . '/' . ltrim((string)$project['path'], '/') . '/web/create-password.php?token=' . rawurlencode($token);

/* =========================
   ENVIAR EMAIL
========================= */

$safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
$projectName = htmlspecialchars((string)($project['name'] ?? 'seu projeto'), ENT_QUOTES, 'UTF-8');
$expiresText = $purpose === 'access' ? '24 horas' : '1 hora';

if ($purpose === 'access') {
    $subject = 'Acesso ao seu projeto';
    $heading = 'Acesso ao seu projeto';
    $intro = "Seu projeto <strong>{$projectName}</strong> foi criado. Clique abaixo para criar sua senha e acessar o painel.";
    $button = 'Criar senha';
} else {
    $subject = 'Recuperação de senha';
    $heading = 'Recuperação de senha';
    $intro = "Recebemos uma solicitação para redefinir a senha do projeto <strong>{$projectName}</strong>.";
    $button = 'Redefinir senha';
}

$html = "
<h2>{$heading}</h2>
<p>{$intro}</p>
<p>
    <a href='{$safeLink}'
       style='background:#2563eb;color:#fff;padding:10px 20px;
              text-decoration:none;border-radius:6px;'>
        {$button}
    </a>
</p>
<p>Este link expira em {$expiresText}.</p>
<p>Se o botão não abrir, copie este link:</p>
<p>{$safeLink}</p>
";

$result = MailService::send(
    $email,
    $subject,
    $html
);

if (!$result) {
    error_log("Falha ao enviar email {$purpose} para {$email} no projeto {$projectId}");
}

echo json_encode([
    'success' => true,
    'mail_sent' => (bool)$result,
    'purpose' => $purpose,
]);
