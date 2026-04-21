<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

/* =========================
BUSCAR REQUESTS
========================= */

$stmt = $pdo->query("
SELECT
l.id,
l.name,
l.email,
l.site_name,
l.slug,
l.created_at,
b.name AS base_name
FROM leads l
LEFT JOIN bases b ON b.id = l.base_id
WHERE l.implementation_status = 'ready'
ORDER BY l.id DESC
");

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
PAGE CONTENT
========================= */

ob_start();
?>

<h1 class="page-title">Solicitações de Projetos</h1>

<div>

<a class="c-btn-secondary"
href="/public/admin/projects/index.php">
Voltar para Projetos
</a>

</div>


<br>

<div class="c-card">

<?php if(empty($requests)): ?>

<p>Nenhuma solicitação aguardando criação.</p>

<?php else: ?>

<div class="table-wrapper">

<table class="core-table">

<thead>
<tr>
<th>ID</th>
<th>Projeto</th>
<th>Cliente</th>
<th>Base</th>
<th>Slug</th>
<th>Data</th>
<th>Ação</th>
</tr>
</thead>

<tbody>

<?php foreach($requests as $req): ?>

<tr>

<td><?= $req['id'] ?></td>

<td>
<strong><?= htmlspecialchars($req['site_name']) ?></strong>
<br>
<span><?= htmlspecialchars($req['email']) ?></span>
</td>

<td><?= htmlspecialchars($req['name']) ?></td>

<td><?= htmlspecialchars($req['base_name'] ?? '-') ?></td>

<td><?= htmlspecialchars($req['slug']) ?></td>

<td><?= $req['created_at'] ?? '-' ?></td>

<td>

<a
class="btn-secondary"
href="/public/admin/projects/project_create.php?lead_id=<?= $req['id'] ?>"
>
Criar Projeto
</a>

</td>

</tr>

<?php endforeach ?>

</tbody>

</table>

</div>

<?php endif ?>

</div>

<?php

$content = ob_get_clean();

/* =========================
SIDEBAR
========================= */

$rightSidebarContent = '

<div class="sidebar-card">

<h3 class="sidebar-title">
Informações
</h3>

<p>
Aqui ficam as solicitações de projetos enviadas
pelos usuários durante a implementação.
</p>

<p>
O administrador revisa os dados e cria o projeto
definitivo no sistema.
</p>

</div>

';

$page = [

'title' => 'Solicitações de Projetos',
'content' => $content,
'rightSidebar' => $rightSidebarContent

];

require APP_PATH . '/views/layout_admin.php';