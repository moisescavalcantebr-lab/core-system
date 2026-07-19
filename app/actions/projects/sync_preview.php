<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$projectId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT p.*, b.slug AS base_slug
    FROM projects p
    INNER JOIN bases b ON b.id = p.base_id
    WHERE p.id = :id
");
$stmt->execute(['id' => $projectId]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project || empty($project['path']) || empty($project['base_slug'])) {
    die('Projeto inválido.');
}

$baseSlug = trim((string)($project['base_slug'] ?? ''));
$basePath = BASES_PATH . '/' . $baseSlug;
$projectPath = ROOT_PATH . '/' . ltrim($project['path'], '/');

if (!is_dir($basePath)) {
    die('Base do projeto não encontrada.');
}

function scanDifferences($baseDir, $projectDir, &$changes = [], $relative = '')
{
    if (!is_dir($baseDir)) {
        return $changes;
    }

    $files = scandir($baseDir);

    foreach ($files as $file) {

        if ($file === '.' || $file === '..') continue;

        $baseFile = $baseDir . '/' . $file;
        $projFile = $projectDir . '/' . $file;
        $relPath  = $relative . '/' . $file;

        if (is_dir($baseFile)) {

            scanDifferences($baseFile, $projFile, $changes, $relPath);

        } else {

            if (str_contains($relPath, 'database.php')) continue;
            if (str_contains($relPath, 'project.json')) continue;

            if (!file_exists($projFile)) {
                $changes[] = ['type' => 'novo', 'file' => $relPath];
            } else {
                if (md5_file($baseFile) !== md5_file($projFile)) {
                    $changes[] = ['type' => 'alterado', 'file' => $relPath];
                }
            }
        }
    }

    return $changes;
}

$changes = [];
scanDifferences($basePath . '/app', $projectPath . '/app', $changes, '/app');
scanDifferences($basePath . '/web', $projectPath . '/web', $changes, '/web');

ob_start();
?>

<h1>Pre-visualização da Sincronização</h1>

<div class="card">
    <p>
        <strong>Base usada:</strong>
        <?= htmlspecialchars((string)$project['base_slug']) ?>
    </p>
    <p>
        <strong>Projeto:</strong>
        <?= htmlspecialchars((string)$project['slug']) ?>
    </p>

<?php if (empty($changes)): ?>

    <p>Nenhuma alteração detectada.</p>

    <form method="post" action="/app/actions/projects/sync.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $projectId ?>">
        <button class="btn-secondary">
            Sincronizar Mesmo Assim
        </button>
    </form>

<?php else: ?>

    <p><strong>Arquivos que serão atualizados:</strong></p>

    <ul>
        <?php foreach ($changes as $change): ?>
            <li>
                <?= $change['type'] === 'novo' ? 'Novo' : 'Alterado' ?>
                - <?= htmlspecialchars($change['file']) ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <br>

    <form method="post" action="/app/actions/projects/sync.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $projectId ?>">
        <button class="btn-secondary">
            Confirmar Sincronização
        </button>
    </form>
    <br>

<?php endif; ?>

</div>
<br>

<a class="btn-secondary" href="/web/admin/projects/view.php?id=<?= $project['id'] ?>">
    Voltar
</a>

<?php
$content = ob_get_clean();
$title = 'Preview Sync';
require APP_PATH . '/views/layout_admin.php';
