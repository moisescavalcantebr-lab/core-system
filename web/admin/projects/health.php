<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

/* =====================================================
   BUSCAR PROJETOS
===================================================== */

$stmt = $pdo->query("
SELECT p.*, b.name AS base_name
FROM projects p
LEFT JOIN bases b ON b.id = p.base_id
ORDER BY p.id DESC
");

$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$health = [];

foreach($projects as $project){

    $path = ROOT_PATH.'/'.ltrim($project['path'],'/');

    $filesOK = is_dir($path);

    $dbOK = false;
    $clientOK = false;

    $configPath = $path.'/app/config/database.php';

    if(file_exists($configPath)){

        try{

            $dbConf = require $configPath;

            $dsn="mysql:host={$dbConf['host']};dbname={$dbConf['name']};charset={$dbConf['charset']}";

            $p = new PDO($dsn,$dbConf['user'],$dbConf['pass']);

            $dbOK = true;

            $count = $p->query("
            SELECT COUNT(*)
            FROM project_users
            WHERE role='ADMIN'
            ")->fetchColumn();

            if($count>0){
                $clientOK=true;
            }

        }catch(Throwable $e){
            $dbOK=false;
        }

    }

    $health[]=[
        'project'=>$project,
        'files'=>$filesOK,
        'db'=>$dbOK,
        'client'=>$clientOK
    ];

}

ob_start();
?>

<h1 class="page-title">Saúde dos Projetos</h1>

<div>

<a class="c-btn-secondary"
href="/public/admin/projects/index.php">
Voltar para Projetos
</a>

</div>
<br>

<div class="c-table-wrapper">

<table class="c-table">

<thead>
<tr>

<th>Projeto</th>
<th>Pasta</th>
<th>Base</th>
<th>Arquivos</th>
<th>Banco</th>
<th>Cliente</th>
<th>Status</th>
<th>Ações</th>

</tr>
</thead>

<tbody>

<?php foreach($health as $row):

$project = $row['project'];

?>

<tr>

<td>
<strong><?= htmlspecialchars($project['name']) ?></strong><br>
</td>

<td>
<small><?= htmlspecialchars($project['slug']) ?></small>
</td>
	
	
<td>
<?= htmlspecialchars($project['base_name']) ?>
</td>

<td>

<span class="badge <?= $row['files']?'success':'danger' ?>">
<?= $row['files']?'OK':'Erro' ?>
</span>

</td>

<td>

<span class="c-badge <?= $row['db']?'success':'warning' ?>">
<?= $row['db']?'OK':'Pendente' ?>
</span>

</td>

<td>

<span class="c-badge <?= $row['client']?'success':'warning' ?>">
<?= $row['client']?'Conectado':'Aguardando' ?>
</span>

</td>

<td>

<span class="c-badge <?= $project['status']=='active'?'success':'warning' ?>">
<?= ucfirst($project['status']) ?>
</span>

</td>

<td>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php

$content = ob_get_clean();

require APP_PATH.'/views/layout_admin.php';