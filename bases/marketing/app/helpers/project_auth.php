<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

function projectUser(): ?array
{
    global $pdo;

    if (empty($_SESSION['project_user_id'])) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT id, name, email, username, role, status, avatar, must_change_password
        FROM project_users
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $_SESSION['project_user_id']
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}
function requireProjectAuth(): void
{
    if (empty($_SESSION['project_user_id'])) {
        header('Location: ' . PROJECT_URL . '/admin/login.php');
        exit;
    }

    $user = projectUser();
    $currentFile = basename($_SERVER['PHP_SELF']);

    if ($user && (int)($user['must_change_password'] ?? 0) === 1 && !in_array($currentFile, ['change-password.php', 'change-password-save.php', 'logout.php'], true)) {
        header('Location: ' . PROJECT_URL . '/admin/change-password.php');
        exit;
    }
}
function requireProjectRole(array $roles): void
{
    requireProjectAuth();

    $user = projectUser();

    if (!$user || !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        die('Acesso negado.');
    }
}
function requireProjectAdmin(): void
{
    requireProjectRole(['ADMIN']);
}

function projectUserRole(): string
{
    $user = projectUser();

    return (string)($user['role'] ?? '');
}

function projectDashboardUrl(): string
{
    return projectUserRole() === 'ADMIN'
        ? PROJECT_URL . '/admin/dashboard.php'
        : PROJECT_URL . '/admin/profile/index.php';
}

function projectLogout(): void
{
    session_unset();
    session_destroy();
}
