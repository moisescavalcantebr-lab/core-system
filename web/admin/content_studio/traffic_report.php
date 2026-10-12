<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/bootstrap.php';
require APP_PATH.'/helpers/auth.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); exit; }
ContentStudioService::ensureSchema($pdo);
TrafficRequestService::ensureSchema($pdo);
try {
    $report=TrafficDeliveryService::report($pdo,(int)($_GET['id'] ?? 0));
} catch (Throwable $e) { http_response_code(404); exit('Relatorio indisponivel.'); }
function trafficEscape(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
require dirname(__DIR__,3).'/modules/trafego/web/admin/trafego/report_view.php';
