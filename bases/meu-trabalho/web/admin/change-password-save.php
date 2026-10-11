<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap/project_bootstrap.php';

requireProjectAuth();
csrf_verify();

$user = projectUser();
$password = (string)($_POST['password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');

if (strlen($password) < 6) {
    flash('error', 'A senha deve ter pelo menos 6 caracteres.');
    redirect(PROJECT_URL . '/admin/change-password.php');
}

if ($password === '1234') {
    flash('error', 'Escolha uma senha diferente da senha padrao.');
    redirect(PROJECT_URL . '/admin/change-password.php');
}

if ($password !== $confirm) {
    flash('error', 'Senhas nao conferem.');
    redirect(PROJECT_URL . '/admin/change-password.php');
}

$stmt = $pdo->prepare("
    UPDATE project_users
    SET password = ?, must_change_password = 0
    WHERE id = ?
");
$stmt->execute([password_hash($password, PASSWORD_DEFAULT), (int)$user['id']]);

flash('success', 'Senha alterada com sucesso.');
redirect(projectDashboardUrl());
