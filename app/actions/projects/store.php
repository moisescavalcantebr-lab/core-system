<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/services/projects/ProjectInstaller.php';

requireAdmin();
csrf_verify();

/* ================================
   CAPTURA
================================ */

$name       = trim($_POST['name'] ?? '');
$slug       = strtolower(trim($_POST['slug'] ?? ''));
$ownerName  = trim($_POST['owner_name'] ?? '');
$ownerEmail = trim($_POST['owner_email'] ?? '');
$baseId     = (int)($_POST['base_id'] ?? 0);

/* ================================
   VALIDAÇÕES
================================ */

if (!$name || !$slug || !$ownerName || !$ownerEmail) {
    die('Campos obrigatórios não preenchidos.');
}

if (!preg_match('/^[a-z0-9-]{3,30}$/', $slug)) {
    die('Slug inválido.');
}

if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
    die('Email inválido.');
}

/* ================================
   SLUG DUPLICADO
================================ */

$stmt = $pdo->prepare("
    SELECT id FROM projects WHERE slug = :slug
");
$stmt->execute(['slug' => $slug]);

if ($stmt->fetch()) {
    die('Slug já existe.');
}

/* ================================
   BUSCAR BASE
================================ */

$stmt = $pdo->prepare("
    SELECT * FROM bases
    WHERE id = :id
    AND status = 1
");

$stmt->execute(['id' => $baseId]);
$base = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    die('Base inválida.');
}

/* ================================
   BUSCAR PLANO FREE
================================ */

$stmt = $pdo->query("
    SELECT *
    FROM plans
    WHERE billing_cycle = 'free'
    AND status = 1
    LIMIT 1
");

$plan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plan) {
    die('Plano FREE não configurado.');
}

$planId = (int)$plan['id'];

/* ================================
   BILLING
================================ */

$expiresAt = null;
$billingStatus = 'active';

if ($plan['billing_cycle'] === 'monthly') {
    $expiresAt = date('Y-m-d', strtotime('+30 days'));
}

if ($plan['billing_cycle'] === 'annual') {
    $expiresAt = date('Y-m-d', strtotime('+365 days'));
}

/* ================================
   PATHS
================================ */

$relativePath = '/projects/' . $slug;
$physicalPath = PROJECTS_PATH . '/' . $slug;

/* ================================
   FUNÇÕES
================================ */

function recursiveCopy($src, $dst)
{
    if (!is_dir($src)) {
        throw new Exception("Base não encontrada: {$src}");
    }

    @mkdir($dst, 0755, true);

    $files = scandir($src);

    if ($files === false) {
        throw new Exception("Erro ao ler diretório: {$src}");
    }

    foreach ($files as $file) {

        if ($file === '.' || $file === '..') continue;

        $source = $src . '/' . $file;
        $dest   = $dst . '/' . $file;

        if (is_dir($source)) {
            recursiveCopy($source, $dest);
        } else {
            copy($source, $dest);
        }
    }
}

function deleteRecursive($dir)
{
    if (!is_dir($dir)) return;

    $files = scandir($dir);

    if ($files === false) return;

    foreach ($files as $file) {

        if ($file === '.' || $file === '..') continue;

        $path = $dir . '/' . $file;

        if (is_dir($path)) {
            deleteRecursive($path);
        } else {
            unlink($path);
        }
    }

    rmdir($dir);
}

/* ================================
   PROCESSO
================================ */

try {

    $pdo->beginTransaction();

    /* CRIAR PROJETO */

    $stmt = $pdo->prepare("
        INSERT INTO projects
        (name,slug,owner_name,owner_email,base_id,plan_id,path,status,billing_status,expires_at)
        VALUES
        (:name,:slug,:owner,:email,:base,:plan,:path,'pending',:billing,:expires)
    ");

    $stmt->execute([
        'name'    => $name,
        'slug'    => $slug,
        'owner'   => $ownerName,
        'email'   => $ownerEmail,
        'base'    => $baseId,
        'plan'    => $planId,
        'path'    => $relativePath,
        'billing' => $billingStatus,
        'expires' => $expiresAt
    ]);

    $projectId = (int)$pdo->lastInsertId();

    /* CLONAR BASE */

    $sourcePath = BASES_PATH . '/' . $base['slug'];

    recursiveCopy($sourcePath, $physicalPath);

    /* GERAR CONFIG */

    ProjectInstaller::generateConfig(
        $physicalPath,
        [
            'id'             => $projectId,
            'name'           => $name,
            'slug'           => $slug,
            'owner_name'     => $ownerName,
            'owner_email'    => $ownerEmail,
            'status'         => 'pending',
            'billing_status' => $billingStatus,
            'expires_at'     => $expiresAt
        ]
    );

    /* LOG */

    $pdo->prepare("
        INSERT INTO project_logs
        (project_id,action,message)
        VALUES
        (?, 'created', 'Projeto criado com sucesso.')
    ")->execute([$projectId]);

    $pdo->commit();

} catch (Throwable $e) {

    $pdo->rollBack();

    if (isset($physicalPath)) {
        deleteRecursive($physicalPath);
    }

    die('Erro ao criar projeto: ' . $e->getMessage());
}

header("Location: /public/admin/projects/index.php");
exit;