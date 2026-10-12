<?php
declare(strict_types=1);

require_once APP_PATH.'/helpers/core_bridge.php';
require_once dirname(PROJECT_PATH,2).'/app/services/TrafficRequestService.php';

function trafficContext(): array
{
    global $project;
    requireProjectAdmin();
    $user = projectUser();
    if (!$user || (int)($user['status'] ?? 0) !== 1) {
        http_response_code(403); exit('Acesso negado.');
    }
    if (!is_file(PROJECT_PATH.'/modules/trafego/module.json')) {
        http_response_code(403); exit('Modulo nao instalado.');
    }
    $core = projectCorePdo();
    if (!$core) throw new RuntimeException('O Core esta indisponivel. Tente novamente mais tarde.');
    $id = TrafficRequestService::projectId($core,$project,basename(PROJECT_PATH));
    TrafficRequestService::ensureSchema($core);
    return [$core,$id];
}

function trafficPages(PDO $pdo): array
{
    if (!is_file(PROJECT_PATH.'/modules/landing_page/module.json')) return [];
    $helper = PROJECT_PATH.'/web/admin/landing_page/helpers.php';
    if (!is_file($helper)) return [];
    require_once $helper;
    landingPageEnsureSchema($pdo);
    return $pdo->query("SELECT id,title,slug,status FROM landing_page_pages WHERE status = 'published' ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
}

function trafficPageUrl(array $page): string
{
    // Prefer the server's canonical configuration over a request-controlled Host header.
    $config = require dirname(PROJECT_PATH,2).'/env/env.production.php';
    $origin = rtrim((string)($config['app']['url'] ?? $config['app_url'] ?? ''),'/');
    if ($origin === '' || !filter_var($origin,FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Configure a URL publica do Core antes de selecionar uma pagina.');
    }
    $parts = parse_url($origin);
    $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '')
        .rtrim((string)($parts['path'] ?? ''),'/');
    return $origin.PROJECT_URL.'/landing_page.php?slug='.rawurlencode($page['slug']);
}

function trafficEscape(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
