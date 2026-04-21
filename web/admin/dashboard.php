<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

/*
|--------------------------------------------------------------------------
| MÉTRICAS
|--------------------------------------------------------------------------
*/

$total = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();

$active = $pdo->query("
    SELECT COUNT(*) FROM projects
    WHERE status = 'active'
    AND billing_status = 'active'
")->fetchColumn();

$pending = $pdo->query("
    SELECT COUNT(*) FROM projects
    WHERE status = 'pending'
")->fetchColumn();

$suspended = $pdo->query("
    SELECT COUNT(*) FROM projects
    WHERE billing_status = 'suspended'
")->fetchColumn();

$freeProjects = $pdo->query("
SELECT COUNT(*)
FROM projects p
JOIN plans pl ON pl.id = p.plan_id
WHERE pl.billing_cycle = 'free'
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| FINANCEIRO
|--------------------------------------------------------------------------
*/

$mrr = (float)$pdo->query("
SELECT COALESCE(SUM(pl.price),0)
FROM projects p
JOIN plans pl ON pl.id = p.plan_id
WHERE p.status='active'
AND p.billing_status='active'
AND pl.billing_cycle='monthly'
")->fetchColumn();

$arr = (float)$pdo->query("
SELECT COALESCE(SUM(pl.price),0)
FROM projects p
JOIN plans pl ON pl.id = p.plan_id
WHERE p.status='active'
AND p.billing_status='active'
AND pl.billing_cycle='annual'
")->fetchColumn();

$annual = ($mrr * 12) + $arr;

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$title = 'Dashboard';

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Dashboard</h1>
            <p class="c-page-subtitle">Visão geral do sistema</p>
        </div>
    </div>

    <div class="c-page-content">
		
		<h3 class="c-section-title">Projetos</h3>

<div class="c-dashboard-grid">
            <div class="c-dashboard-card c-card--neutral">
                <h4>Total de Projetos</h4>
                <div class="c-metric"><?= $total ?></div>
            </div>

            <div class="c-dashboard-card c-card--success">
                <h4>Ativos</h4>
                <div class="c-metric"><?= $active ?></div>
            </div>

            <div class="c-dashboard-card c-card--warning">
                <h4>Pendentes</h4>
                <div class="c-metric"><?= $pending ?></div>
            </div>

            <div class="c-dashboard-card c-card--danger">
                <h4>Suspensos</h4>
                <div class="c-metric"><?= $suspended ?></div>
            </div>

            <div class="c-dashboard-card c-card--neutral">
                <h4>Planos Free</h4>
                <div class="c-metric"><?= $freeProjects ?></div>
            </div>
</div>

<h3 class="c-section-title">Financeiro</h3>

<div class="c-dashboard-grid">
            <div class="c-dashboard-card c-card--finance c-dashboard-card--wide">
                <h4>MRR</h4>
                <div class="c-metric">R$ <?= number_format($mrr, 2, ',', '.') ?></div>
            </div>

            <div class="c-dashboard-card c-card--finance">
                <h4>ARR</h4>
                <div class="c-metric">R$ <?= number_format($arr, 2, ',', '.') ?></div>
            </div>

            <div class="c-dashboard-card c-card--finance">
                <h4>Receita Anual</h4>
                <div class="c-metric">R$ <?= number_format($annual, 2, ',', '.') ?></div>
            </div>
</div>
    </div>

</div>

<?php
$content = ob_get_clean();

require APP_PATH . '/views/layout_admin.php';