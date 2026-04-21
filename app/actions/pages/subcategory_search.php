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
    SELECT DISTINCT sub_category 
    FROM core_page_contents
    WHERE sub_category IS NOT NULL
    AND sub_category != ''
    AND type IN ('page','blog')
    AND LOWER(sub_category) LIKE :term
    ORDER BY sub_category
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