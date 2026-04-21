<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$projectId = (int)($_GET['id'] ?? 0);

/* =========================
PROJETO
========================= */

$stmt = $pdo->prepare("
SELECT p.*, b.name AS base_name, pl.name AS plan_name, pl.billing_cycle
FROM projects p
LEFT JOIN bases b ON b.id = p.base_id
LEFT JOIN plans pl ON pl.id = p.plan_id
WHERE p.id = :id
");

$stmt->execute(['id'=>$projectId]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$project){
    die('Projeto não encontrado.');
}

/* =========================
STATUS
========================= */

$projectInstalled = $project['status'] === 'active';

/* =========================
CLIENTE
========================= */

$clientConnected = false;

$projectConfigPath = ROOT_PATH.'/'.ltrim($project['path'],'/').'/app/config/database.php';

if(file_exists($projectConfigPath)){

    try{
        $dbConf = require $projectConfigPath;

        $dsn="mysql:host={$dbConf['host']};dbname={$dbConf['name']};charset={$dbConf['charset']}";
        $p = new PDO($dsn,$dbConf['user'],$dbConf['pass']);

        $count = $p->query("
            SELECT COUNT(*)
            FROM project_users
            WHERE role='ADMIN'
        ")->fetchColumn();

        if($count>0){
            $clientConnected=true;
        }

    }catch(Throwable $e){}
}

/* =========================
LOGS
========================= */

$logs=$pdo->prepare("
SELECT *
FROM project_logs
WHERE project_id=:id
ORDER BY id DESC
LIMIT 10
");

$logs->execute(['id'=>$projectId]);
$logs=$logs->fetchAll(PDO::FETCH_ASSOC);

/* =========================
STEPS
========================= */

$steps = [
'created'=>true,
'cloned'=>is_dir(ROOT_PATH.'/'.ltrim($project['path'],'/')),
'database'=>$projectInstalled,
'client'=>$clientConnected
];

$title = 'Projeto';

ob_start();
?>

<div class="c-page">

<div class="c-page-header">

<div>
<h1 class="c-page-title"><?= htmlspecialchars($project['name']) ?></h1>
<p class="c-page-subtitle">Gerenciamento do projeto</p>
</div>

<div class="c-page-actions">

<a class="c-btn-secondary" href="/public/admin/projects/index.php">
Voltar
</a>

<a class="c-btn-secondary"
href="/app/actions/projects/sync_preview.php?id=<?= $project['id'] ?>">
Sincronizar
</a>

<a class="c-btn-secondary"
href="<?= htmlspecialchars($project['path']) ?>/public/"
target="_blank">
Abrir
</a>

</div>

</div>

<div class="c-page-content">

<!-- INFO -->
<div class="c-card">

<div class="c-settings-grid">

<div class="c-form-group">
<label>Slug</label>
<div><?= htmlspecialchars($project['slug']) ?></div>
</div>

<div class="c-form-group">
<label>Base</label>
<div><?= htmlspecialchars($project['base_name']) ?></div>
</div>

<div class="c-form-group">
<label>Plano</label>
<div><?= htmlspecialchars($project['plan_name']) ?></div>
</div>

<div class="c-form-group">
<label>Status</label>
<span class="c-badge <?= $project['status']=='active'?'c-badge--success':'c-badge--warning' ?>">
<?= ucfirst($project['status']) ?>
</span>
</div>

<div class="c-form-group">
<label>Billing</label>
<span class="c-badge <?= $project['billing_status']=='active'?'c-badge--success':'c-badge--danger' ?>">
<?= ucfirst($project['billing_status']) ?>
</span>
</div>

<div class="c-form-group">
<label>Expiração</label>
<div>
<?=
$project['billing_cycle']==='free'
? 'Plano Gratuito'
: ($project['expires_at'] ?? '—')
?>
</div>
</div>

</div>

</div>

<!-- GRID 3 COLUNAS -->
<div class="c-settings-grid">

<!-- CLIENTE -->
<div class="c-card">

<h3>Cliente</h3>

<?php if(!$projectInstalled): ?>

<p class="c-text-warning">Projeto não instalado.</p>

<?php elseif($clientConnected): ?>

<p><strong><?= htmlspecialchars($project['owner_name']) ?></strong></p>
<p><?= htmlspecialchars($project['owner_email']) ?></p>
<span class="c-badge c-badge--success">Conectado</span>

<?php else: ?>

<p><strong><?= htmlspecialchars($project['owner_name']) ?></strong></p>
<p><?= htmlspecialchars($project['owner_email']) ?></p>

<form method="post" action="/app/actions/projects/send_access.php">
<?= csrf_field() ?>
<input type="hidden" name="id" value="<?= $project['id'] ?>">
<button class="c-btn-secondary">Enviar acesso</button>
</form>

<?php endif; ?>

</div>

<!-- STATUS -->
<div class="c-card">

<h3>Status</h3>

<div class="c-progress">

<div class="<?= $steps['created']?'done':'' ?>">Criado</div>
<div class="<?= $steps['cloned']?'done':'' ?>">Clonado</div>
<div class="<?= $steps['database']?'done':'' ?>">Banco</div>
<div class="<?= $steps['client']?'done':'' ?>">Cliente</div>

</div>

</div>

<!-- INSTALAÇÃO -->
<?php if($project['status']==='pending'): ?>

<div class="c-card">

<h3>Instalar</h3>

<form method="post" action="/app/actions/projects/install.php">

<?= csrf_field() ?>

<input type="hidden" name="id" value="<?= $project['id'] ?>">

<div class="c-form-group">
<label>DB Name</label>
<input class="c-input" name="db_name" required>
</div>

<div class="c-form-group">
<label>DB User</label>
<input class="c-input" name="db_user" required>
</div>

<div class="c-form-group">
<label>DB Pass</label>
<input class="c-input" type="password" name="db_pass" required>
</div>

<button class="c-btn-secondary">Instalar</button>

</form>

</div>

<?php endif; ?>

</div>

<!-- LOGS -->
<div class="c-card">

<h3>Logs Recentes</h3>

<?php if(empty($logs)): ?>

<p>Nenhum log encontrado.</p>

<?php else: ?>

<table class="c-table">

<thead>
<tr>
<th>Ação</th>
<th>Mensagem</th>
<th>Nível</th>
<th>Data</th>
</tr>
</thead>

<tbody>

<?php foreach($logs as $log): ?>

<tr>

<td><?= htmlspecialchars($log['action']) ?></td>
<td><?= htmlspecialchars($log['message']) ?></td>

<td>
<span class="c-badge
<?= $log['level']=='error'?'c-badge--danger':($log['level']=='warning'?'c-badge--warning':'c-badge--success') ?>">
<?= $log['level'] ?>
</span>
</td>

<td><?= $log['created_at'] ?></td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<?php endif; ?>

</div>

</div>

</div>

<?php
$content = ob_get_clean();

/* =========================
SIDEBAR
========================= */

$rightSidebarEnabled = true;

$rightSidebarContent = '

<div class="c-card">

<form method="post" action="/app/actions/projects/toggle_status.php">
'.csrf_field().'
<input type="hidden" name="id" value="'.$project['id'].'">
<button class="c-btn-secondary c-btn-block">
'.($project['status']=='active'?'Bloquear':'Ativar').'
</button>
</form>

<br>

<form method="post" action="/app/actions/projects/toggle_billing.php">
'.csrf_field().'
<input type="hidden" name="id" value="'.$project['id'].'">
<button class="c-btn-secondary c-btn-block">
Alterar Billing
</button>
</form>

<br>

<form method="post" action="/app/actions/projects/delete.php"
onsubmit="return confirm(\'Tem certeza?\');">
'.csrf_field().'
<input type="hidden" name="id" value="'.$project['id'].'">
<button class="c-btn-danger c-btn-block">
Excluir
</button>
</form>

</div>

';

require APP_PATH.'/views/layout_admin.php';