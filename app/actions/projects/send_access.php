<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/services/projects/MailService.php';

requireAdmin();
csrf_verify();

$projectId = (int)($_POST['id'] ?? 0);

if(!$projectId){
die('Projeto inválido.');
}

/* =========================
   Buscar projeto
========================= */

$stmt=$pdo->prepare("
SELECT id,name,path,owner_email
FROM projects
WHERE id=:id
");

$stmt->execute(['id'=>$projectId]);
$project=$stmt->fetch(PDO::FETCH_ASSOC);

if(!$project){
die('Projeto não encontrado.');
}

$email=$project['owner_email'];

if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
die('Email do cliente inválido.');
}

/* =========================
   Gerar token
========================= */

$token=bin2hex(random_bytes(32));

$expires=date(
'Y-m-d H:i:s',
strtotime('+24 hours')
);

/* =========================
   Invalidar tokens antigos
========================= */

$pdo->prepare("
UPDATE project_access_tokens
SET used=1
WHERE project_id=:id
")->execute([
'id'=>$projectId
]);

/* =========================
   Inserir novo token
========================= */

$pdo->prepare("
INSERT INTO project_access_tokens
(project_id,email,token,expires_at)
VALUES
(:project,:email,:token,:expires)
")->execute([
'project'=>$projectId,
'email'=>$email,
'token'=>$token,
'expires'=>$expires
]);

/* =========================
   Montar link
========================= */

$baseUrl = rtrim($config['app_url'], '/');

$link = $baseUrl .
        '/' .
        ltrim($project['path'], '/') .
        "/public/create-password.php?token=" . $token;
/* =========================
   Email
========================= */
MailService::send(
$email,
'Acesso ao seu projeto',
"
<h2>Bem-vindo ao seu projeto</h2>

<p>Clique no botão abaixo para criar sua senha:</p>

<p>
<a href='$link'
style='
display:inline-block;
padding:12px 20px;
background:#2563eb;
color:#fff;
text-decoration:none;
border-radius:6px;
'>
Criar senha
</a>
</p>

<p>Ou copie o link abaixo:</p>

<p>$link</p>

<p>Este link expira em 24 horas.</p>
"
);
/* =========================
   Log
========================= */

$pdo->prepare("
INSERT INTO project_logs
(project_id,action,message)
VALUES
(:id,'access_sent',:msg)
")->execute([
'id'=>$projectId,
'msg'=>"Email de acesso enviado para $email"
]);

header("Location: /public/admin/projects/view.php?id=$projectId&email_sent=1");
exit;