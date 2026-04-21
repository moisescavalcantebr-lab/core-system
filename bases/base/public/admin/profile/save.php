<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

csrf_verify();

$user = projectUser();

$uploadDir = STORAGE_PATH . '/uploads/avatars/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$allowed = ['jpg','jpeg','png','webp'];

/* =========================
VALIDAÇÃO
========================= */

$name = trim($_POST['name'] ?? '');
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if ($name === '') {
    flash('error', 'Nome obrigatório.');
    redirect(PROJECT_URL . '/admin/profile/index.php');
}

/* =========================
AVATAR
========================= */

if (!empty($_FILES['avatar']['name'])) {

    $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        flash('error', 'Formato de avatar inválido.');
        redirect(PROJECT_URL . '/admin/profile/index.php');
    }

    $fileName = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
    $dest = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {

        if (!empty($user['avatar']) && file_exists(PUBLIC_PATH . '/' . $user['avatar'])) {
            @unlink(PUBLIC_PATH . '/' . $user['avatar']);
        }

        $avatarPath = 'storage/uploads/avatars/' . $fileName;

        $pdo->prepare("
            UPDATE project_users
            SET avatar = :avatar
            WHERE id = :id
        ")->execute([
            'avatar' => $avatarPath,
            'id' => $user['id']
        ]);
    }
}

if (!empty($_POST['remove_avatar'])) {
    $stmt = $pdo->prepare("
        UPDATE project_users
        SET avatar = NULL
        WHERE id = :id
    ");
    $stmt->execute(['id' => $_SESSION['project_user_id']]);
}

/* =========================
ATUALIZAR
========================= */

$pdo->prepare("
    UPDATE project_users
    SET name = :name
    WHERE id = :id
")->execute([
    'name' => $name,
    'id' => $user['id']
]);

/* SENHA */

if ($password !== '') {

    if ($password !== $confirm) {
        flash('error', 'Senhas não conferem.');
        redirect(PROJECT_URL . '/admin/profile/index.php');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->prepare("
        UPDATE project_users
        SET password = :password
        WHERE id = :id
    ")->execute([
        'password' => $hash,
        'id' => $user['id']
    ]);
}

flash('success', 'Perfil atualizado com sucesso.');

redirect(PROJECT_URL . '/admin/profile/index.php');