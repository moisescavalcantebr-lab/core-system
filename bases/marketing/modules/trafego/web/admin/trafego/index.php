<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/project_bootstrap.php';
require __DIR__.'/helpers.php';
requireProjectAdmin();
$title = 'Trafego'; $error = ''; $rows = [];
try { [$core,$projectId] = trafficContext(); $rows = TrafficRequestService::list($core,$projectId); }
catch (Throwable $e) { $error = $e instanceof RuntimeException && !$e instanceof PDOException ? $e->getMessage() : 'Nao foi possivel consultar as solicitacoes.'; }
ob_start();
?>
<div class="c-page-header"><h1>Trafego</h1><div class="traffic-actions"><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/trafego/request.php">Solicitar divulgacao</a></div></div>
<?php flash_show(); ?>
<?php if ($error): ?><p role="alert"><?= trafficEscape($error) ?></p><?php endif; ?>
<?php if (!$rows && !$error): ?><p>Nenhuma solicitacao criada.</p><?php endif; ?>
<?php if ($rows): ?><div class="traffic-table"><table class="c-table"><thead><tr><th>Solicitacao</th><th>Destino</th><th>Plataforma</th><th>Status</th><th>Atualizada</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= trafficEscape($row['title']) ?></td><td><?= trafficEscape($row['page_title'] ?: $row['destination_type']) ?></td><td><?= trafficEscape(TrafficRequestService::PLATFORMS[$row['platform']] ?? $row['platform']) ?></td><td><?= trafficEscape(TrafficRequestService::STATUSES[$row['status']] ?? $row['status']) ?></td><td><?= trafficEscape($row['updated_at']) ?></td><td><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/trafego/request.php?id=<?= (int)$row['id'] ?>">Abrir</a></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
<?php require __DIR__.'/style.php'; $content = ob_get_clean(); require APP_PATH.'/views/layout_admin.php'; ?>
