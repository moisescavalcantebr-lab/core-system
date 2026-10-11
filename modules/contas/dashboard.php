<?php
declare(strict_types=1);
global $pdo;
$user = function_exists('projectUser') ? projectUser() : null;
if (!$pdo instanceof PDO || !$user || ($user['role'] ?? '') !== 'ADMIN' || ($user['status'] ?? '') !== 'active') {
    return [];
}
require_once PROJECT_PATH.'/web/admin/contas/helpers.php';
require_once PROJECT_PATH.'/web/admin/contas/charts.php';
contasEnsureSchema($pdo);
$today = contasToday();
$data = contasAnalytics(contasRecords($pdo,(int)$user['id']),contasPayments($pdo,(int)$user['id']),$today);
$summary = $data['summary'];
ob_start();
?>
<section class="dash-module a-panel"><div class="dash-module-head"><div><h3>Contas a Pagar e Receber</h3><p><?= date('d/m/Y',strtotime($today)) ?></p></div><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/contas/index.php">Abrir</a></div>
<div class="a-metrics"><?php foreach(['pending_payable'=>'A pagar em aberto','pending_receivable'=>'A receber em aberto','overdue_payable'=>'A pagar vencido','overdue_receivable'=>'A receber vencido','next7_payable'=>'A pagar nos proximos 7 dias','received_month'=>'Recebido neste mes','paid_month'=>'Pago neste mes'] as $key=>$label): ?><div><span><?= $label ?></span><strong><?= contasMoney($summary[$key]) ?></strong></div><?php endforeach; ?><div><span>Resultado das movimentacoes no mes</span><strong><?= contasMoney($summary['received_month']-$summary['paid_month']) ?></strong></div></div>
<?php contasChart($data['actual'],'Recebimentos e pagamentos - ultimos 12 meses'); contasChart($data['forecast'],'Valores em aberto por vencimento - proximos 6 meses',true); ?>
<section class="a-section"><h3>Principais categorias pagas - ultimos 12 meses</h3><?php if(!$data['categories']): ?><p>Nenhum pagamento registrado no periodo.</p><?php else: $maxCategory = max($data['categories']); ?><div class="a-categories"><?php foreach($data['categories'] as $category=>$amount): ?><div class="a-category"><span><?= contasEscape($category) ?></span><div class="a-category-track"><div class="a-category-fill" style="width:<?= number_format($amount/max(1,$maxCategory)*100,3,'.','') ?>%"></div></div><strong><?= contasMoney($amount) ?></strong></div><?php endforeach; ?></div><?php endif; ?></section>
<section class="a-section"><h3>Vencidas e proximos vencimentos</h3><div class="a-scroll"><table class="c-table"><thead><tr><th>Vencimento</th><th>Conta</th><th>Tipo</th><th>Restante</th><th>Situacao</th></tr></thead><tbody><?php foreach($data['upcoming'] as $account): $status=contasStatus($account,$today); ?><tr><td><?= date('d/m/Y',strtotime($account['due_date'])) ?></td><td><a href="<?= PROJECT_URL ?>/admin/contas/index.php?view=movement&id=<?= (int)$account['id'] ?>"><?= contasEscape($account['title']) ?></a></td><td><?= contasKinds()[$account['kind']] ?></td><td><?= contasMoney(contasRemaining($account)) ?></td><td><span class="a-status a-status-<?= $status ?>"><?= contasStatusLabels()[$status] ?></span></td></tr><?php endforeach; ?><?php if(!$data['upcoming']): ?><tr><td colspan="5">Nenhuma conta em aberto.</td></tr><?php endif; ?></tbody></table></div></section>
</section>
<?php require PROJECT_PATH.'/web/admin/contas/ui.php'; return ['type'=>'panel','module'=>'contas','group'=>'contas','group_label'=>'Contas','order'=>20,'size'=>'wide','html'=>ob_get_clean()]; ?>
