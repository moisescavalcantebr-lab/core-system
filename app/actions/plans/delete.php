<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Plano invalido.');
    redirect('/web/admin/plans/index.php');
}

$stmt = $pdo->prepare("SELECT billing_cycle FROM plans WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$cycle = (string)($stmt->fetchColumn() ?: '');

if ($cycle === 'free') {
    flash('error', 'O plano gratis e protegido.');
    redirect('/web/admin/plans/index.php');
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE plan_id = ?");
$stmt->execute([$id]);
if ((int)$stmt->fetchColumn() > 0) {
    flash('error', 'Este plano possui projetos vinculados. Inative ou crie outro plano.');
    redirect('/web/admin/plans/index.php');
}

$pdo->prepare("DELETE FROM plan_bases WHERE plan_id = ?")->execute([$id]);
$pdo->prepare("
    DELETE bpp
    FROM base_plan_prices bpp
    INNER JOIN plan_prices pp ON pp.id = bpp.plan_price_id
    WHERE pp.plan_id = ?
")->execute([$id]);
$pdo->prepare("DELETE FROM plan_prices WHERE plan_id = ?")->execute([$id]);
$pdo->prepare("DELETE FROM plans WHERE id = ?")->execute([$id]);

flash('success', 'Plano excluido.');
redirect('/web/admin/plans/index.php');
