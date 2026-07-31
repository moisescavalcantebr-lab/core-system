<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require dirname(__DIR__, 3) . '/app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/helpers/base_manifest.php';

requireAdmin();

function baseDeleteBlocked(string $message, int $id): void
{
    flash('error', $message);
    redirect('/app/actions/bases/delete.php?id=' . $id);
}

function baseDeleteRedirect(string $status = ''): void
{
    $url = '/web/admin/bases/index.php';

    if ($status !== '') {
        $url .= '?' . $status . '=1';
    }

    header('Location: ' . $url);
    exit;
}

function baseDeleteRecursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    @chmod($dir, 0775);

    $files = array_diff(scandir($dir) ?: [], ['.', '..']);

    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;

        if (is_dir($path) && !is_link($path)) {
            baseDeleteRecursive($path);
            continue;
        }

        @chmod($path, 0664);

        if (!@unlink($path)) {
            throw new RuntimeException('Não foi possível remover o arquivo: ' . $path);
        }
    }

    @chmod($dir, 0775);

    if (!@rmdir($dir)) {
        throw new RuntimeException('Não foi possível remover a pasta: ' . $dir);
    }
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM bases WHERE id = :id");
$stmt->execute(['id' => $id]);
$base = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    die('Base não encontrada.');
}

if ((string)$base['slug'] === 'base') {
    die('A base principal não pode ser excluída.');
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
    csrf_verify();

    if (!coreIsProduction() && base_is_locked($base)) {
        baseDeleteBlocked('Esta base esta publicada/travada. Reabra no laboratorio antes de excluir.', $id);
    }

    if ($totalProjects > 0) {
        baseDeleteBlocked("Não é possível excluir esta base. Existem {$totalProjects} projeto(s) vinculado(s).", $id);
    }

    if ($totalClones > 0) {
        baseDeleteBlocked("Não é possível excluir esta base. Existem {$totalClones} base(s) derivada(s) vinculada(s).", $id);
    }

    /* Remover pasta física */
    $folder = BASES_PATH . '/' . $base['slug'];

    if (is_dir($folder)) {
        try {
            baseDeleteRecursive($folder);
        } catch (Throwable $e) {
            flash('error', 'Não foi possível remover os arquivos da base. Verifique permissões no servidor. Detalhe: ' . $e->getMessage());
            redirect('/app/actions/bases/delete.php?id=' . $id);
        }
    }

    $pdo->prepare("DELETE FROM bases WHERE id = :id")
        ->execute(['id' => $id]);

    baseDeleteRedirect('deleted');
}

ob_start();
?>

<h1>Excluir Base</h1>

<?php flash_show(); ?>

<div class="c-card">

    <p><strong>Base:</strong> <?= htmlspecialchars($base['name']) ?></p>
    <p><strong>Slug:</strong> <?= htmlspecialchars($base['slug']) ?></p>

    <?php if (!coreIsProduction() && base_is_locked($base)): ?>

        <div style="color:#b91c1c; margin-top:15px;">
            Esta base esta publicada/travada. Reabra no laboratorio antes de excluir.
        </div>

        <br>
        <a href="/web/admin/bases/index.php" class="c-btn-secondary">Voltar</a>

    <?php elseif ($totalProjects > 0): ?>

        <div style="color:#b91c1c; margin-top:15px;">
            ⚠ Esta base possui <?= $totalProjects ?> projeto(s) vinculado(s).
            <br>
            Não é possível excluir.
        </div>

        <br>
        <a href="/web/admin/bases/index.php" class="c-btn-secondary">Voltar</a>

    <?php elseif ($totalClones > 0): ?>

        <div style="color:#b91c1c; margin-top:15px;">
            ⚠ Esta base possui <?= $totalClones ?> base(s) derivada(s).
            <br>
            Não é possível excluir enquanto houver heranças.
        </div>

        <br>
        <a href="/web/admin/bases/index.php" class="c-btn-secondary">Voltar</a>

    <?php else: ?>

        <div style="margin-top:15px; color:#c97a00;">
            ⚠ Esta ação é irreversível.
        </div>

        <form method="post" style="margin-top:20px;">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <button class="c-btn-danger">
                Confirmar Exclusão
            </button>
            <a href="/web/admin/bases/index.php" class="c-btn-secondary">Cancelar</a>
        </form>

    <?php endif; ?>

</div>

<?php
$content = ob_get_clean();
$title = 'Excluir Base';
require APP_PATH . '/views/layout_admin.php';
