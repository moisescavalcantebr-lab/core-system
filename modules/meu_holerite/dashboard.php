<?php
declare(strict_types=1);

global $pdo;
$user = function_exists('projectUser') ? projectUser() : null;
if (!$pdo instanceof PDO || !$user || ($user['role'] ?? '') !== 'ADMIN' || ($user['status'] ?? '') !== 'active') {
    return [];
}
require_once PROJECT_PATH . '/web/admin/meu_holerite/helpers.php';
holeriteEnsureSchema($pdo);
$stmt = $pdo->prepare('SELECT reference_month, gross_amount, deductions_amount, net_amount FROM holerite_receipts WHERE owner_user_id = ? ORDER BY reference_month DESC, id DESC');
$stmt->execute([(int)$user['id']]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
$latestYear = substr($records[0]['reference_month'] ?? date('Y-m-d'), 0, 4);
$months = holeriteMonths(array_values(array_filter($records, static fn($row) => substr($row['reference_month'], 0, 4) === $latestYear)));
$net = array_sum(array_column($months, 'net_amount'));
$deductions = array_sum(array_column($months, 'deductions_amount'));
ob_start();
?>
<section class="dash-module">
    <div class="dash-module-head"><div><h3>Meu Holerite</h3><p><?= holeriteEscape($latestYear) ?></p></div><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/meu_holerite/index.php">Abrir</a></div>
    <?php if (!$records): ?><p>Nenhum holerite cadastrado.</p><?php else: ?>
    <div class="holerite-dashboard-metrics">
        <div><span>Ultima competencia</span><strong><?= date('m/Y', strtotime($records[0]['reference_month'])) ?></strong></div>
        <div><span>Liquido no ano</span><strong><?= holeriteMoney(holeriteDecimal($net)) ?></strong></div>
        <div><span>Descontos no ano</span><strong><?= holeriteMoney(holeriteDecimal($deductions)) ?></strong></div>
    </div>
    <?php endif; ?>
</section>
<style>
.holerite-dashboard-metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
.holerite-dashboard-metrics span { display: block; font-size: 12px; margin-bottom: 7px; }
.holerite-dashboard-metrics strong { display: block; font-size: 17px; overflow-wrap: anywhere; }
@media (max-width: 650px) { .holerite-dashboard-metrics { grid-template-columns: 1fr; } }
</style>
<?php
return ['type' => 'panel', 'module' => 'meu_holerite', 'group' => 'meu_holerite', 'group_label' => 'Meu Holerite', 'order' => 54, 'html' => ob_get_clean()];
