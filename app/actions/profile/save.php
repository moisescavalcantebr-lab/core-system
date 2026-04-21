<?php
require __DIR__ . '/../../bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

$userId = $_SESSION['core_user']['id'];

/* =========================
DADOS
========================= */

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');

$pdo->prepare("
    UPDATE core_users 
    SET name = ?, email = ?
    WHERE id = ?
")->execute([$name, $email, $userId]);

/* =========================
SENHA
========================= */

if (!empty($_POST['new_password'])) {

    $stmt = $pdo->prepare("SELECT password FROM core_users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!password_verify($_POST['current_password'], $user['password'])) {
        flash('error', 'Senha atual incorreta');
        redirect('/public/admin/profile/index.php');
        exit;
    }

    if ($_POST['new_password'] !== $_POST['confirm_password']) {
        flash('error', 'As senhas não coincidem');
        redirect('/public/admin/profile/index.php');
        exit;
    }

    $pdo->prepare("
        UPDATE core_users SET password = ?
        WHERE id = ?
    ")->execute([
        password_hash($_POST['new_password'], PASSWORD_DEFAULT),
        $userId
    ]);
}

/* =========================
AVATAR
========================= */

$stmt = $pdo->prepare("SELECT avatar FROM core_users WHERE id = ?");
$stmt->execute([$userId]);
$current = $stmt->fetchColumn();

/* REMOVER */
if (!empty($_POST['remove_avatar'])) {

    if ($current) {
        $path = STORAGE_PATH . '/storage/uploads/' . $current;
        if (file_exists($path)) unlink($path);
    }

    $pdo->prepare("UPDATE core_users SET avatar = NULL WHERE id = ?")
        ->execute([$userId]);
}

/* UPLOAD */
if (!empty($_FILES['avatar']['name'])) {

    $allowed = ['image/png','image/jpeg','image/webp'];

    if (!in_array($_FILES['avatar']['type'], $allowed)) {
        flash('error', 'Formato inválido.');
        redirect('/public/admin/profile/index.php');
        exit;
    }

    $uploadPath = STORAGE_PATH . '/uploads/avatars/';

    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }

    if ($current) {
        $old = $uploadPath . $current;
        if (file_exists($old)) unlink($old);
    }

    $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
    $fileName = 'avatar_' . $userId . '_' . time() . '.' . $ext;

    move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath . $fileName);

    $pdo->prepare("UPDATE core_users SET avatar = ? WHERE id = ?")
        ->execute([$fileName, $userId]);
	$stmt = $pdo->prepare("SELECT * FROM core_users WHERE id = ?");
$stmt->execute([$userId]);

$_SESSION['core_user'] = $stmt->fetch(PDO::FETCH_ASSOC);
}
flash('success', 'Perfil atualizado');
redirect('/public/admin/profile/index.php');