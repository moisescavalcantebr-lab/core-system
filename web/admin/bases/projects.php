<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

/* =========================
CAPTURAR BASE
========================= */

$baseId = (int)($_GET['id'] ?? 0);

if (!$baseId) {
    die('Base inválida.');
}

/* =========================
BUSCAR BASE
========================= */

$stmt = $pdo->prepare("
SELECT *
FROM bases
WHERE id = ?
LIMIT 1
");

$stmt->execute([$baseId]);
$base = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    die('Base não encontrada.');
}

/* =========================
BUSCAR PROJETOS
========================= */

$stmt = $pdo->prepare("
SELECT
id,
name,
slug,
status,
created_at
FROM projects
WHERE base_id = ?
ORDER BY created_at DESC
");

$stmt->execute([$baseId]);

$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalProjects = count($projects);

/* =========================
PAGE
========================= */

ob_start();
?>

<h1 class="page-title">
Projetos da Base: <?= htmlspecialchars($base['name']) ?>
</h1>
<div>

<a class="c-btn-secondary"
href="/public/admin/bases/index.php">
+ Voltar Para Bases
</a>
</div><br>

<div class="c-card">
<p>
Esta base possui <strong><?= $totalProjects ?></strong> projeto(s) criado(s).
</p>
</div>
<br>
<div class="c-table-wrapper">
<table class="c-table">

<thead>
<tr>
<th>ID</th>
<th>Projeto</th>
<th>Slug</th>
<th>Status</th>
<th>Data</th>
<th style="text-align:right;">Ações</th>
</tr>
</thead>

<tbody>

<?php if (empty($projects)): ?>

<tr>
<td colspan="6" class="c-text-center">
Nenhum projeto utiliza esta base ainda.
</td>
</tr>

<?php else: ?>

<?php foreach ($projects as $project): ?>

<tr>

<td><?= $project['id'] ?></td>

<td>
<strong><?= htmlspecialchars($project['name']) ?></strong>
</td>

<td><?= htmlspecialchars($project['slug']) ?></td>

<td>
<span class="c-badge c-badge--danger">
<?= htmlspecialchars($project['status']) ?>
</span>
</td>

<td><?= $project['created_at'] ?></td>

<td style="text-align:right">

<a class="c-btn-secondary"
   href="/public/admin/projects/view.php?id=<?= $project['id'] ?>">
Ver
</a>

<?php if ($project['status'] === 'deleted'): ?>

<form method="post"
      action="/app/actions/projects/delete_full.php"
      style="display:inline-block"
      onsubmit="return confirm('Excluir completamente este projeto? Esta ação remove arquivos e banco de dados.');">

    <?= csrf_field() ?>

    <input type="hidden" name="id" value="<?= $project['id'] ?>">

    <button class="c-btn-danger">
        Excluir
    </button>

</form>

<?php endif; ?>

</td>
</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>

</table>
</div>

<?php

$content = ob_get_clean();

/* =========================
SIDEBAR
========================= */

$rightSidebarEnabled = true;

$rightSidebarContent = '

<div class="c-card c-sidebar-card">

<h3>Base</h3>

<p>
<strong>'.htmlspecialchars($base['slug']).'</strong>
</p>

<p>
Projetos: <strong>'.$totalProjects.'</strong>
</p>

</div>

<br>


';

$title = 'Projetos da Base';

require APP_PATH . '/views/layout_admin.php';