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
        SELECT id, name, email, role, avatar
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

function projectLogout(): void
{
    session_unset();
    session_destroy();
}