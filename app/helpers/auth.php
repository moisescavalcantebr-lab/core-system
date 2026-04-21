<?php
declare(strict_types=1);

function requireLogin(): void
{
    if (!isset($_SESSION['core_user'])) {
        header('Location: /public/admin/login.php');
        exit;
    }
}

function requireAdmin(): void
{
    if (empty($_SESSION['core_user'])) {
        redirect('/public/admin/login.php');
    }

    $role = $_SESSION['core_user']['role'] ?? '';

    if (!in_array($role, ['ADMIN', 'SUPER_ADMIN'])) {
        die('Acesso restrito');
    }
}