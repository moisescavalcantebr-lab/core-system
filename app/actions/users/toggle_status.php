<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

/*
|--------------------------------------------------------------------------
| PERMISSÃO (SÓ SUPER ADMIN)
|--------------------------------------------------------------------------
*/

if (($_SESSION['core_user']['role'] ?? '') !== 'SUPER_ADMIN') {
    flash('error', 'Apenas SUPER ADMIN pode alterar status.');
    redirect('/public/admin/users/index.php');
}

/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    redirect('/public/admin/users/index.php');
}

/*
|--------------------------------------------------------------------------
| NÃO PERMITIR AUTO-BLOQUEIO
|--------------------------------------------------------------------------
*/

if ($_SESSION['core_user']['id'] == $id) {
    flash('error', 'Você não pode alterar seu próprio status.');
    redirect('/public/admin/users/index.php');
}

/*
|--------------------------------------------------------------------------
| TOGGLE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    UPDATE core_users
    SET status = IF(status = 1, 0, 1)
    WHERE id = :id
");

$stmt->execute(['id' => $id]);

flash('success', 'Status do usuário atualizado.');

redirect('/public/admin/users/index.php');