<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap/bootstrap.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

csrf_verify();

/*
|--------------------------------------------------------------------------
| INPUTS
|--------------------------------------------------------------------------
*/

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

/*
|--------------------------------------------------------------------------
| VALIDAÇÕES
|--------------------------------------------------------------------------
*/

if (!$name || !$email || !$password) {
    flash('error', 'Preencha todos os campos.');
    redirect('/public/admin/register.php');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'E-mail inválido.');
    redirect('/public/admin/register.php');
}

if ($password !== $passwordConfirm) {
    flash('error', 'As senhas não coincidem.');
    redirect('/public/admin/register.php');
}

if (strlen($password) < 6) {
    flash('error', 'Senha deve ter no mínimo 6 caracteres.');
    redirect('/public/admin/register.php');
}

/*
|-------------------------------------------------------------------------- 
| VERIFICAR EXISTENTE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("SELECT id FROM core_users WHERE email = :email");
$stmt->execute(['email' => $email]);

if ($stmt->fetch()) {
    flash('error', 'E-mail já cadastrado.');
    redirect('/public/admin/register.php');
}

/*
|-------------------------------------------------------------------------- 
| CRIAR USUÁRIO
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    INSERT INTO core_users (name, email, password, role)
    VALUES (:name, :email, :password, 'USER')
");

$stmt->execute([
    'name' => $name,
    'email' => $email,
    'password' => password_hash($password, PASSWORD_DEFAULT),
]);

/*
|-------------------------------------------------------------------------- 
| FINAL
|--------------------------------------------------------------------------
*/

flash('success', 'Conta criada. Aguarde ativação pelo administrador.');
redirect('/public/admin/login.php');