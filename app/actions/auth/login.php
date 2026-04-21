<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap/bootstrap.php';

csrf_verify();

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    flash('error', 'Informe email e senha.');
    redirect('/public/admin/login.php');
}

$authService = new AuthService($pdo);

try {

    $user = $authService->login($email, $password);

    if (!$user) {
        flash('error', 'Credenciais inválidas.');
        redirect('/public/admin/login.php');
    }

    $authService->createSession($user);

    redirect('/public/admin/dashboard.php');

} catch (Exception $e) {

    flash('error', $e->getMessage());
    redirect('/public/admin/login.php');
}