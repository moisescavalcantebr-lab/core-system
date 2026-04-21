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

/* normalizar */
$qNormalized = mb_strtolower($q);

/* =========================
QUERY
========================= */

global $pdo;

$stmt = $pdo->prepare("
    SELECT DISTINCT category 
    FROM core_page_contents
    WHERE category IS NOT NULL
    AND category != ''
    AND LOWER(category) LIKE :term
    ORDER BY category
    LIMIT 10
");

$stmt->execute([
    'term' => $qNormalized . '%'
]);

$result = $stmt->fetchAll(PDO::FETCH_COLUMN);

/* =========================
RESPONSE
========================= */

echo json_encode(is_array($result) ? $result : []);