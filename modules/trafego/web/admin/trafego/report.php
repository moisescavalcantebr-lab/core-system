<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/project_bootstrap.php';
require __DIR__.'/helpers.php';
requireProjectAdmin();
if ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); exit; }
try {
    [$core,$projectId]=trafficContext();
    $report=TrafficDeliveryService::report($core,(int)($_GET['id'] ?? 0),$projectId);
} catch (Throwable $e) { http_response_code(404); exit('Relatorio indisponivel.'); }
require __DIR__.'/report_view.php';
