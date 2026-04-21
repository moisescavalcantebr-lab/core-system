<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$slug = $_GET['slug'] ?? '';

if (!$slug || !is_dir(BASES_PATH . '/' . $slug)) {
    die('Base inválida.');
}

$stmt = $pdo->prepare("
    INSERT INTO bases (name, slug)
    VALUES (:name, :slug)
");

$stmt->execute([
    'name' => ucfirst(str_replace('-', ' ', $slug)),
    'slug' => $slug
]);

header("Location: /public/admin/bases/index.php");
exit;
