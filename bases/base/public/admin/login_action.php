<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap/project_bootstrap.php';

/* =========================
CSRF
========================= */

csrf_verify();

/* =========================
INPUT
========================= */

$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

/* =========================
BUSCAR USUÁRIO
========================= */

$stmt = $pdo->prepare("
    SELECT id, password
    FROM project_users
    WHERE email = :email AND status = 1
    LIMIT 1
");

$stmt->execute(['email' => $email]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* =========================
VALIDAÇÃO
========================= */

if (!$user || !password_verify($password, $user['password'])) {

    flash('error', 'Email ou senha inválidos.');
    redirect(PROJECT_URL . '/admin/login.php');
}

/* =========================
LOGIN
========================= */

$_SESSION['project_user_id'] = $user['id'];

/* Segurança */
session_regenerate_id(true);

/* =========================
REDIRECT
========================= */

redirect(PROJECT_URL . '/admin/dashboard.php');