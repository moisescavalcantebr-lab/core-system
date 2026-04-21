<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$baseId = (int)($_GET['base_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM bases WHERE id = :id");
$stmt->execute(['id' => $baseId]);
$base = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    die('Base inválida.');
}

ob_start();
?>

<h1>Clonar Base: <?= htmlspecialchars($base['name']) ?></h1>
<div>

<a class="c-btn-secondary"
href="/public/admin/bases/index.php">
+ Voltar Para Bases
</a>
</div><br>

<div class="c-card">

<form method="post" action="/app/actions/bases/base_clone_store.php">

<input type="hidden" name="base_id" value="<?= $base['id'] ?>">

<label>Nome da Nova Base</label>
<input class="c-input-filter" name="name" required>

<label>Slug da Pasta</label>
<input class="c-input-filter" name="slug" required>

<small style="color:#666;"><br>

Use apenas letras minúsculas, números e hífen.<br>
Exemplo: <strong>loja-v2</strong><br>
Evite nomes longos ou com espaços.
</small>

<br><br>

<button class="c-btn-secondary">Clonar Base</button>

</form>

</div>

<?php
$content = ob_get_clean();
$title = 'Clonar Base';

$rightSidebarContent = '

<div class="c-card sidebar-card">

<h3>Informações</h3>

Informações Aqui

</div>

';

$page = [

'title' => 'Editar Bloco',
'content' => $content,
'rightSidebar' => $rightSidebarContent

];
	
	
require APP_PATH . '/views/layout_admin.php';
