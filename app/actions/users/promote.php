<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap/bootstrap.php';

require APP_PATH . '/helpers/auth.php';

requireAdmin();

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
| NÃO PERMITIR AUTO-PROMOÇÃO INDEVIDA (segurança básica)
|--------------------------------------------------------------------------
*/

if ($_SESSION['core_user']['id'] == $id) {
    flash('error', 'Ação não permitida.');
    redirect('/public/admin/users/index.php');
}
/*
|--------------------------------------------------------------------------
| PERMISSÃO
|--------------------------------------------------------------------------
*/

if (($_SESSION['core_user']['role'] ?? '') !== 'SUPER_ADMIN') {
    flash('error', 'Apenas SUPER ADMIN pode promover usuários.');
    redirect('/public/admin/users/index.php');
}/*
|--------------------------------------------------------------------------
| PROMOVER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    UPDATE core_users
    SET role = 'ADMIN'
    WHERE id = :id
");

$stmt->execute(['id' => $id]);

flash('success', 'Usuário promovido para ADMIN.');

redirect('/public/admin/users/index.php');