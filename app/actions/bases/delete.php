<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require dirname(__DIR__, 3) . '/app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM bases WHERE id = :id");
$stmt->execute(['id' => $id]);
$base = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    die('Base não encontrada.');
}

/* =====================================
   Verificar projetos vinculados
===================================== */

$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM projects 
    WHERE base_id = :id
");
$stmt->execute(['id' => $id]);
$totalProjects = $stmt->fetchColumn();

/* =====================================
   Verificar clones vinculados
===================================== */

$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM bases 
    WHERE cloned_from_id = :id
");
$stmt->execute(['id' => $id]);
$totalClones = $stmt->fetchColumn();

/* =====================================
   Se for confirmação final (POST)
===================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($base['is_protected']) {
        die('Esta base está protegida e não pode ser excluída.');
    }

    if ($totalProjects > 0) {
        die("Não é possível excluir esta base. Existem {$totalProjects} projeto(s) vinculados.");
    }

    if ($totalClones > 0) {
        die("Não é possível excluir esta base. Existem {$totalClones} base(s) derivada(s) vinculada(s).");
    }

    /* Remover pasta física */
    $folder = BASES_PATH . '/' . $base['slug'];

    if (is_dir($folder)) {

        function deleteRecursive($dir) {
            foreach (scandir($dir) as $file) {

                if ($file === '.' || $file === '..') continue;

                $path = $dir . '/' . $file;

                if (is_dir($path)) {
                    deleteRecursive($path);
                } else {
                    unlink($path);
                }
            }
            rmdir($dir);
        }

        deleteRecursive($folder);
    }

    $pdo->prepare("DELETE FROM bases WHERE id = :id")
        ->execute(['id' => $id]);

    header("Location: /public/admin/bases/index.php");
    exit;
}

ob_start();
?>

<h1>Excluir Base</h1>

<div class="c-card">

    <p><strong>Base:</strong> <?= htmlspecialchars($base['name']) ?></p>
    <p><strong>Slug:</strong> <?= htmlspecialchars($base['slug']) ?></p>

    <?php if ($base['is_protected']): ?>

        <div style="color:#b91c1c; margin-top:15px;">
            ⚠ Esta base está protegida e não pode ser excluída.
        </div>

        <br>
        <a href="/public/admin/bases/index.php" class="c-btn-secondary">Voltar</a>

    <?php elseif ($totalProjects > 0): ?>

        <div style="color:#b91c1c; margin-top:15px;">
            ⚠ Esta base possui <?= $totalProjects ?> projeto(s) vinculado(s).
            <br>
            Não é possível excluir.
        </div>

        <br>
        <a href="/public/admin/bases/index.php" class="c-btn-secondary">Voltar</a>

    <?php elseif ($totalClones > 0): ?>

        <div style="color:#b91c1c; margin-top:15px;">
            ⚠ Esta base possui <?= $totalClones ?> base(s) derivada(s).
            <br>
            Não é possível excluir enquanto houver heranças.
        </div>

        <br>
        <a href="/public/admin/bases/index.php" class="c-btn-secondary">Voltar</a>

    <?php else: ?>

        <div style="margin-top:15px; color:#c97a00;">
            ⚠ Esta ação é irreversível.
        </div>

        <form method="post" style="margin-top:20px;">
            <input type="hidden" name="id" value="<?= $id ?>">
            <button class="c-btn-danger">
                Confirmar Exclusão
            </button>
            <a href="/public/admin/bases/index.php" class="c-btn-secondary">Cancelar</a>
        </form>

    <?php endif; ?>

</div>

<?php
$content = ob_get_clean();
$title = 'Excluir Base';
require APP_PATH . '/views/layout_admin.php';