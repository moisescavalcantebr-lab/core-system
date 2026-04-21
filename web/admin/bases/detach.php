<?php
require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

requireAdmin();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM bases WHERE id = :id");
$stmt->execute(['id' => $id]);
$base = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    die('Base não encontrada.');
}

if (!$base['cloned_from_id']) {
    die('Esta base já é Master.');
}

$pdo->prepare("
    UPDATE bases
    SET cloned_from_id = NULL
    WHERE id = :id
")->execute(['id' => $id]);

header("Location: bases.php");
exit;