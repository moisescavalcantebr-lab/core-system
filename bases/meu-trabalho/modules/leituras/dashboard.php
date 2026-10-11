<?php
declare(strict_types=1);
global $pdo;
$user = function_exists('projectUser') ? projectUser() : null;
if (!$pdo instanceof PDO || !$user || ($user['role'] ?? '') !== 'ADMIN' || ($user['status'] ?? '') !== 'active') return [];
require_once PROJECT_PATH.'/web/admin/leituras/helpers.php';
require_once PROJECT_PATH.'/web/admin/leituras/report.php';
eqEnsureSchema($pdo);
$eqMonth = eqWorkspace($pdo,(int)$user['id']);
$eqAnchor = new DateTimeImmutable($eqMonth.'-01');
$eqData = eqAnalytics(eqReadings($pdo,(int)$user['id'],$eqAnchor->modify('-11 months')->format('Y-m-d'),$eqAnchor->format('Y-m-t')),$eqMonth);
ob_start();
?>
<section class="dash-module eq-panel"><div class="dash-module-head"><div><h3>Leituras</h3><p><?= $eqAnchor->format('m/Y') ?></p></div><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/leituras/index.php">Abrir</a></div>
<?php eqReport($eqData); ?></section>
<?php require PROJECT_PATH.'/web/admin/leituras/ui.php'; return ['type'=>'panel','module'=>'leituras','group'=>'leituras','group_label'=>'Leituras','order'=>30,'size'=>'wide','html'=>ob_get_clean()]; ?>
