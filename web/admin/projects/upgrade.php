<?php
require __DIR__.'/../../../app/bootstrap/bootstrap.php';
require APP_PATH.'/helpers/auth.php';

requireAdmin();

$id=(int)($_GET['id'] ?? 0);

$stmt=$pdo->prepare("
SELECT p.*, pl.name AS plan_name
FROM projects p
JOIN plans pl ON pl.id=p.plan_id
WHERE p.id=:id
");

$stmt->execute(['id'=>$id]);
$project=$stmt->fetch(PDO::FETCH_ASSOC);

if(!$project){
die('Projeto não encontrado');
}

$plans=$pdo->query("
SELECT * FROM plans
WHERE billing_cycle!='free'
AND status=1
ORDER BY price ASC
")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<h1>Upgrade de Plano</h1>

<div class="card">

<p>
Projeto: <strong><?= htmlspecialchars($project['name']) ?></strong>
</p>

<p>
Plano atual: <strong><?= htmlspecialchars($project['plan_name']) ?></strong>
</p>

<hr>

<form method="post" action="../../../../old/public/admin/actions/project_upgrade.php">

<input type="hidden" name="id" value="<?= $project['id'] ?>">

<select class="input" name="plan_id" required>

<option value="">Selecionar plano</option>

<?php foreach($plans as $plan): ?>

<option value="<?= $plan['id'] ?>">

<?= htmlspecialchars($plan['name']) ?>

- R$ <?= number_format($plan['price'],2,',','.') ?>

</option>

<?php endforeach; ?>

</select>

<br><br>

<button class="btn-primary">
Aplicar Upgrade
</button>

</form>

</div>

<?php
$content=ob_get_clean();
$title='Upgrade Plano';

require APP_PATH.'/views/layout_admin.php';