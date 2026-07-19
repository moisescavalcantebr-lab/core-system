<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAuth();

$scoreboardId = (int)($_GET['id'] ?? 0);

if ($scoreboardId <= 0) {
    http_response_code(422);
    exit('Placar invalido.');
}

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/live_data.php';
