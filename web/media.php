<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
$thumb = !empty($_GET['thumb']);

if ($id <= 0) {
    http_response_code(404);
    exit;
}

$stmt = $pdo->prepare("SELECT file_name FROM core_media WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$fileName = $stmt->fetchColumn();

if (!$fileName) {
    http_response_code(404);
    exit;
}

$fileName = basename((string)$fileName);
$originalPath = STORAGE_PATH . '/media/' . $fileName;
$thumbPath = STORAGE_PATH . '/media/thumb/' . $fileName;
$path = $thumb ? $thumbPath : $originalPath;

if (!is_file($path)) {
    if ($thumb && is_file($originalPath)) {
        $path = $originalPath;
    } else {
        http_response_code(404);
        exit;
    }
}

$mime = mime_content_type($path) ?: 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=31536000');

readfile($path);
