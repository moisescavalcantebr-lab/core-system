<?php
declare(strict_types=1);
global $pdo;
$user = function_exists('projectUser') ? projectUser() : null;
if (!$pdo instanceof PDO || !$user || ($user['role'] ?? '') !== 'ADMIN' || ($user['status'] ?? '') !== 'active') {
    return [];
}
require_once PROJECT_PATH . '/web/admin/meu_holerite/helpers.php';
holeriteEnsureSchema($pdo);
$stmt = $pdo->prepare('SELECT employer, reference_month, gross_amount, deductions_amount, net_amount, received FROM holerite_receipts WHERE owner_user_id = ? ORDER BY reference_month DESC, id DESC');
$stmt->execute([(int)$user['id']]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
$benefits = [];
$hasBenefits = is_file(PROJECT_PATH . '/modules/beneficios/module.json') && is_file(PROJECT_PATH . '/web/admin/beneficios/helpers.php');
if ($hasBenefits) {
    require_once PROJECT_PATH . '/web/admin/beneficios/helpers.php';
    benefitEnsureSchema($pdo);
    $benefits = benefitRecords($pdo, (int)$user['id']);
}
$years = array_unique([date('Y'), ...array_map(static fn($row) => substr($row['reference_month'],0,4), [...$records,...$benefits])]);
rsort($years);
$latestYear = substr($records[0]['reference_month'] ?? $benefits[0]['reference_month'] ?? date('Y-m-d'),0,4);
if ($benefits) {
    $latestYear = max($latestYear,substr($benefits[0]['reference_month'],0,4));
}
$year = is_string($_GET['personal_year'] ?? null) && in_array($_GET['personal_year'],$years,true) ? $_GET['personal_year'] : $latestYear;
$companies = array_unique(array_column([...$records,...$benefits],'employer'));
sort($companies);
$company = is_string($_GET['personal_employer'] ?? null) && in_array($_GET['personal_employer'],$companies,true) ? $_GET['personal_employer'] : '';
$scope = static fn($row) => substr($row['reference_month'],0,4) === $year && ($company === '' || $row['employer'] === $company);
$selected = array_values(array_filter($records,$scope));
$months = holeriteMonths($selected);
$benefitMonths = $hasBenefits ? benefitMonths(array_values(array_filter($benefits,$scope))) : [];
$gross = array_sum(array_column($months,'gross_amount'));
$deductions = array_sum(array_column($months,'deductions_amount'));
$net = array_sum(array_column($months,'net_amount'));
$chartMonths = [];
for ($m=1;$m<=12;$m++) {
    $key=sprintf('%s-%02d',$year,$m);
    $chartMonths[$key]=['gross'=>$months[$key]['gross_amount']??0,'deductions'=>$months[$key]['deductions_amount']??0,'transport'=>$benefitMonths[$key]['transport']??0,'meal'=>$benefitMonths[$key]['meal']??0];
}
function holeriteDashboardChart(array $months, array $series, string $heading): void
{
    $max=1;
    foreach($months as $values) {
        foreach($series as $field=>$label) {
            $max=max($max,$values[$field]);
        }
    }
    ?>
    <section class="pd-section"><h3><?= holeriteEscape($heading) ?></h3><div class="pd-legend"><?php foreach($series as $field=>$label): ?><span class="pd-<?= $field ?>"><?= $label ?></span><?php endforeach; ?></div>
    <div class="pd-scroll"><div class="pd-chart" role="img" aria-label="<?= holeriteEscape($heading) ?>; valores detalhados na tabela por competencia."><div class="pd-plot">
    <?php foreach($months as $month=>$values): ?><div class="pd-bars"><?php foreach($series as $field=>$label): ?><div class="pd-bar pd-<?= $field ?>" style="height:<?= number_format($values[$field]/$max*100,3,'.','') ?>%" title="<?= holeriteEscape(date('m/Y',strtotime($month.'-01')).' / '.$label.': '.holeriteMoney(holeriteDecimal($values[$field]))) ?>"></div><?php endforeach; ?></div><?php endforeach; ?>
    </div><div class="pd-labels"><?php foreach($months as $month=>$values): ?><span><?= date('m/y',strtotime($month.'-01')) ?></span><?php endforeach; ?></div></div></div></section>
    <?php
}
ob_start();
?>
<section class="dash-module pd-panel">
    <div class="dash-module-head"><div><h3>Holerites e beneficios</h3><p><?= holeriteEscape($year) ?></p></div><div class="pd-actions"><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/meu_holerite/index.php">Holerites</a><?php if($hasBenefits): ?><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/beneficios/index.php">Beneficios</a><?php endif; ?></div></div>
    <form method="get" class="pd-filters">
        <?php if(is_string($_GET['dashboard_group']??null)): ?><input type="hidden" name="dashboard_group" value="<?= holeriteEscape($_GET['dashboard_group']) ?>"><?php endif; ?>
        <label>Competencias (ano)<select class="c-input" name="personal_year"><?php foreach($years as $option): ?><option value="<?= holeriteEscape($option) ?>" <?= $option===$year?'selected':'' ?>><?= holeriteEscape($option) ?></option><?php endforeach; ?></select></label>
        <label>Empresa<select class="c-input" name="personal_employer"><option value="">Todas</option><?php foreach($companies as $option): ?><option value="<?= holeriteEscape($option) ?>" <?= $option===$company?'selected':'' ?>><?= holeriteEscape($option) ?></option><?php endforeach; ?></select></label><button class="c-btn-secondary">Filtrar</button>
    </form>
    <div class="pd-metrics">
        <div><span>Proventos no ano</span><strong><?= holeriteMoney(holeriteDecimal($gross)) ?></strong></div>
        <div><span>Descontos no ano</span><strong><?= holeriteMoney(holeriteDecimal($deductions)) ?></strong></div>
        <div><span>Liquido nos holerites do ano</span><strong><?= holeriteMoney(holeriteDecimal($net)) ?></strong></div>
        <div><span>Media liquida por competencia</span><strong><?= holeriteMoney(holeriteDecimal(count($months)?(int)round($net/count($months)):0)) ?></strong></div>
        <?php if($hasBenefits): ?><div><span>VT disponibilizado no ano</span><strong><?= holeriteMoney(holeriteDecimal(array_sum(array_column($benefitMonths,'transport')))) ?></strong></div><div><span>VA disponibilizado no ano</span><strong><?= holeriteMoney(holeriteDecimal(array_sum(array_column($benefitMonths,'meal')))) ?></strong></div><?php endif; ?>
    </div>
    <?php if(!$months): ?><p>Nenhum holerite nesta selecao.</p><?php else: holeriteDashboardChart($chartMonths,['gross'=>'Proventos','deductions'=>'Descontos'],'Remuneracao por competencia'); endif; ?>
    <?php if($hasBenefits): if(!$benefitMonths): ?><p>Nenhum beneficio nesta selecao.</p><?php else: holeriteDashboardChart($chartMonths,['transport'=>'Vale-transporte','meal'=>'Vale-alimentacao'],'Beneficios disponibilizados por competencia'); endif; endif; ?>
    <details class="pd-details"><summary>Valores por competencia</summary><div class="pd-scroll"><table class="c-table"><thead><tr><th>Competencia</th><th>Proventos</th><th>Descontos</th><th>Liquido</th><th>Variacao liquida</th><?php if($hasBenefits): ?><th>VT</th><th>VA</th><?php endif; ?></tr></thead><tbody>
        <?php foreach($chartMonths as $month=>$values): $salary=$months[$month]??null; $previous=date('Y-m',strtotime($month.'-01 -1 month')); $prior=$months[$previous]??null; ?><tr><td><?= date('m/Y',strtotime($month.'-01')) ?></td><td><?= $salary?holeriteMoney(holeriteDecimal($salary['gross_amount'])):'-' ?></td><td><?= $salary?holeriteMoney(holeriteDecimal($salary['deductions_amount'])):'-' ?></td><td><?= $salary?holeriteMoney(holeriteDecimal($salary['net_amount'])):'-' ?></td><td><?= $salary&&$prior?holeriteMoney(holeriteDecimal($salary['net_amount']-$prior['net_amount'])):'-' ?></td><?php if($hasBenefits): ?><td><?= benefitMonthValue($benefitMonths[$month]??null,'transport') ?></td><td><?= benefitMonthValue($benefitMonths[$month]??null,'meal') ?></td><?php endif; ?></tr><?php endforeach; ?>
    </tbody></table></div></details>
</section>
<style>
.pd-panel { min-width:0; grid-column:1/-1; }.pd-panel h3 { font-size:17px; margin:0 0 14px; }.pd-actions { display:flex; gap:8px; flex-wrap:wrap; }
.pd-filters { display:grid; grid-template-columns:160px minmax(160px,1fr) auto; gap:12px; align-items:end; margin:18px 0; }.pd-filters label { display:flex; flex-direction:column; gap:6px; font-size:13px; min-width:0; }.pd-filters select { min-width:0; width:100%; }
.pd-metrics { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; padding:18px 0; border-block:1px solid var(--border-color,#d4d4d8); }.pd-metrics div { min-width:0; }.pd-metrics span { display:block; font-size:12px; margin-bottom:7px; color:var(--text-secondary,#52525b); }.pd-metrics strong { font-size:18px; display:block; overflow-wrap:anywhere; }
.pd-section { padding:22px 0; border-bottom:1px solid var(--border-color,#d4d4d8); }.pd-scroll { overflow-x:auto; max-width:100%; }.pd-chart { min-width:580px; padding:10px 4px; }
.pd-legend { display:flex; gap:20px; flex-wrap:wrap; font-size:12px; margin-bottom:16px; }.pd-legend span::before { content:''; width:9px; height:9px; display:inline-block; margin-right:7px; background:var(--series); }
.pd-gross { --series:#14b8a6; }.pd-deductions { --series:#e87967; }.pd-transport { --series:#a3a33d; }.pd-meal { --series:#3b82f6; }
.pd-plot { display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); height:170px; gap:8px; border-bottom:1px solid var(--border-color,#d4d4d8); background:repeating-linear-gradient(to top,transparent 0,transparent 41px,var(--border-color,#d4d4d8) 42px); }
.pd-bars { height:100%; min-width:0; display:flex; align-items:flex-end; justify-content:center; gap:4px; }.pd-bar { width:20px; max-width:35%; background:var(--series); border-radius:2px 2px 0 0; }
.pd-labels { display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:8px; text-align:center; font-size:11px; margin-top:9px; }.pd-details { margin-top:18px; font-size:13px; }.pd-details summary { padding:8px 0; cursor:pointer; }.pd-panel td { white-space:nowrap; font-size:13px; }
@media(max-width:650px) { .pd-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); }.pd-filters { grid-template-columns:repeat(2,minmax(0,1fr)); }.pd-filters button { grid-column:1/-1; }.pd-metrics strong { font-size:16px; } }
</style>
<script>requestAnimationFrame(() => document.querySelectorAll('.pd-chart').forEach(chart => { const scroll = chart.parentElement; const active = [...chart.querySelectorAll('.pd-bar')].filter(bar => bar.getBoundingClientRect().height > 0).pop(); if (active && scroll.scrollWidth > scroll.clientWidth) { const target = active.getBoundingClientRect(); const bounds = scroll.getBoundingClientRect(); scroll.scrollLeft += target.left - bounds.left - bounds.width / 2 + target.width / 2; } }));</script>
<?php
return ['type'=>'panel','module'=>'meu_holerite','group'=>'meu_holerite','group_label'=>'Holerites e beneficios','order'=>54,'size'=>'wide','html'=>ob_get_clean()];
