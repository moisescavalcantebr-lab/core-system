<?php
require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

requireAdmin();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM bases WHERE id = :id");
$stmt->execute(['id' => $id]);
$root = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$root) {
    die('Base não encontrada.');
}

$stmt = $pdo->query("
    SELECT id, name, slug, cloned_from_id
    FROM bases
    ORDER BY id ASC
");
$bases = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==============================
   Montar árvore
============================== */

function buildTree(array $elements, $parentId) {
    $branch = [];

    foreach ($elements as $element) {
        if ($element['cloned_from_id'] == $parentId) {
            $children = buildTree($elements, $element['id']);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[] = $element;
        }
    }

    return $branch;
}

$tree = buildTree($bases, $root['id']);

/* ==============================
   Renderizar
============================== */

function renderTree($nodes) {
    echo '<ul class="base-tree">';
    foreach ($nodes as $node) {
        echo '<li>';
        echo '<span class="tree-node">';
        echo '<strong>' . htmlspecialchars($node['slug']) . '</strong>';
        echo '</span>';

        if (!empty($node['children'])) {
            renderTree($node['children']);
        }

        echo '</li>';
    }
    echo '</ul>';
}

ob_start();
?>

<div class="flex-between mb-20">
    <h1>Herança da Base: <?= htmlspecialchars($root['name']) ?></h1>
    <a href="/public/admin/bases/index.php" class="btn-secondary">
        ← Voltar
    </a>
</div>

<div class="card">

    <h3>Base Master</h3>
    <p><strong><?= htmlspecialchars($root['slug']) ?></strong></p>

    <hr>

    <h3>Derivadas</h3>

    <?php if ($tree): ?>
        <?php renderTree($tree); ?>
    <?php else: ?>
        <p>Nenhuma base derivada.</p>
    <?php endif; ?>

</div>

<?php
$content = ob_get_clean();
$title = 'Herança de Base';
require APP_PATH . '/views/layout_admin.php';