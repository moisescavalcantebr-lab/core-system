<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';

require APP_PATH . '/helpers/auth.php';

requireAdmin();

/* =========================
HEADERS
========================= */

header('Content-Type: application/json; charset=utf-8');

global $pdo;

/* =========================
INPUT
========================= */

$q = trim($_GET['q'] ?? '');

if ($q === '') {
    echo json_encode([]);
    exit;
}

/* =========================
SEGURANÇA
========================= */

$q = substr($q, 0, 50);
$qNormalized = mb_strtolower($q);

/* =========================
QUERY
========================= */

$stmt = $pdo->prepare("
    SELECT slug 
    FROM core_page_contents
    WHERE slug IS NOT NULL
    AND slug != ''
    AND type IN ('page','blog')
    AND LOWER(slug) LIKE :term
    ORDER BY slug
    LIMIT 10
");

$stmt->execute([
    'term' => $qNormalized . '%'
]);

$result = $stmt->fetchAll(PDO::FETCH_COLUMN);

/* =========================
RESPONSE
========================= */

echo json_encode(is_array($result) ? $result : [], JSON_UNESCAPED_UNICODE);
exit;