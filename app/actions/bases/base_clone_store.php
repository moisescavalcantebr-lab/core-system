<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require dirname(__DIR__, 3) . '/app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/helpers/base_manifest.php';

requireAdmin();

$newName = trim($_POST['name'] ?? '');
$newSlug = strtolower(trim($_POST['slug'] ?? ''));
$baseId = (int)($_POST['base_id'] ?? 0);

if (!$newName || !$newSlug || !$baseId) {
    die('Dados incompletos.');
}

$newSlug = preg_replace('/[^a-z0-9\-]/', '-', $newSlug);
$newSlug = preg_replace('/-+/', '-', $newSlug);
$newSlug = trim($newSlug, '-');

if (strlen($newSlug) < 3) {
    die('Slug muito curto.');
}

$stmt = $pdo->prepare('SELECT * FROM bases WHERE id = :id');
$stmt->execute(['id' => $baseId]);
$base = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    die('Base nao encontrada.');
}

$source = BASES_PATH . '/' . $base['slug'];

if (!is_dir($source)) {
    die('Pasta da base nao existe.');
}

$originalSlug = $newSlug;
$counter = 1;

while (true) {
    $stmt = $pdo->prepare('SELECT id FROM bases WHERE slug = :slug');
    $stmt->execute(['slug' => $newSlug]);
    $existsInDb = $stmt->fetch();
    $existsInFolder = is_dir(BASES_PATH . '/' . $newSlug);

    if (!$existsInDb && !$existsInFolder) {
        break;
    }

    $newSlug = $originalSlug . '-' . str_pad((string)$counter, 2, '0', STR_PAD_LEFT);
    $counter++;
}

$destination = BASES_PATH . '/' . $newSlug;

function shouldSkipBaseCloneItem(string $relativePath, string $name): bool
{
    $relativePath = trim(str_replace('\\', '/', $relativePath), '/');

    if ($name === '_notes' || $name === 'dwsync.xml') {
        return true;
    }

    if (in_array($name, ['.git', 'node_modules', 'vendor'], true)) {
        return true;
    }

    $firstSegment = explode('/', $relativePath)[0] ?? '';

    // A base clonada deve nascer limpa. Módulos são adicionados depois, pela tela da própria base.
    return $firstSegment === 'modules';
}

function copyRecursive(string $src, string $dst, string $relative = ''): void
{
    if (!is_dir($dst)) {
        if (!@mkdir($dst, 0755, true) && !is_dir($dst)) {
            throw new RuntimeException('Nao foi possivel criar a pasta: ' . $dst);
        }
    }

    $items = scandir($src);
    if ($items === false) {
        throw new RuntimeException('Nao foi possivel ler a pasta: ' . $src);
    }

    foreach ($items as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $sourcePath = $src . '/' . $file;
        $targetPath = $dst . '/' . $file;
        $itemRelative = ltrim($relative . '/' . $file, '/');

        if (shouldSkipBaseCloneItem($itemRelative, $file)) {
            continue;
        }

        if (is_dir($sourcePath)) {
            copyRecursive($sourcePath, $targetPath, $itemRelative);
            continue;
        }

        if (!is_file($sourcePath)) {
            continue;
        }

        if (!@copy($sourcePath, $targetPath)) {
            throw new RuntimeException('Nao foi possivel copiar o arquivo: ' . $sourcePath);
        }
    }
}

function safeModuleSlug(string $slug): string
{
    return preg_replace('/[^a-z0-9\-_]/', '', strtolower($slug));
}

function appendModuleSchema(string $destination, string $moduleSlug, array $manifest, string $modulePath): void
{
    $schema = $manifest['database']['schema'] ?? '';

    if (!$schema) {
        return;
    }

    $schemaPath = $modulePath . '/' . ltrim((string)$schema, '/');
    $baseSchemaPath = $destination . '/app/database/schema.sql';

    if (!is_file($schemaPath) || !is_file($baseSchemaPath)) {
        return;
    }

    $content = file_get_contents($schemaPath);

    if ($content === false || trim($content) === '') {
        return;
    }

    file_put_contents(
        $baseSchemaPath,
        PHP_EOL . PHP_EOL . '-- =====================================================' .
        PHP_EOL . '-- MODULE: ' . $moduleSlug .
        PHP_EOL . '-- =====================================================' .
        PHP_EOL . $content,
        FILE_APPEND
    );
}

function copyModuleFiles(string $destination, array $manifest, string $modulePath): void
{
    $copies = $manifest['copy'] ?? [];

    if (!is_array($copies)) {
        return;
    }

    foreach ($copies as $copyItem) {
        if (!is_array($copyItem)) {
            continue;
        }

        $from = trim((string)($copyItem['from'] ?? ''), '/');
        $to = trim((string)($copyItem['to'] ?? ''), '/');

        if ($from === '' || $to === '' || str_contains($from, '..') || str_contains($to, '..')) {
            continue;
        }

        $sourcePath = $modulePath . '/' . $from;
        $targetPath = $destination . '/' . $to;

        if (is_dir($sourcePath)) {
            copyRecursive($sourcePath, $targetPath);
        } elseif (is_file($sourcePath)) {
            $targetDir = dirname($targetPath);

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            copy($sourcePath, $targetPath);
        }
    }
}

function removeDirectoryTree(string $path): void
{
    $basePath = realpath(BASES_PATH);
    $targetPath = realpath($path);

    if ($basePath === false || $targetPath === false || !str_starts_with($targetPath, $basePath . DIRECTORY_SEPARATOR)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($targetPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($targetPath);
}

try {
    if (!is_writable(BASES_PATH)) {
        throw new RuntimeException('A pasta de bases nao tem permissao de escrita: ' . BASES_PATH);
    }

    copyRecursive($source, $destination);

    $stmt = $pdo->prepare('
        INSERT INTO bases
        (cloned_from_id, name, slug, description, allows_users, max_admins, status, is_protected, created_at)
        VALUES
        (:cloned_from_id, :name, :slug, :description, :allows_users, :max_admins, 1, 0, NOW())
    ');

    $stmt->execute([
        'cloned_from_id' => $base['id'],
        'name' => $newName,
        'slug' => $newSlug,
        'description' => $base['description'] ?? '',
        'allows_users' => $base['allows_users'] ?? 1,
        'max_admins' => $base['max_admins'] ?? 1,
    ]);

    $newBase = [
        'name' => $newName,
        'slug' => $newSlug,
        'description' => $base['description'] ?? '',
        'allows_users' => $base['allows_users'] ?? 1,
        'max_admins' => $base['max_admins'] ?? 1,
        'status' => 1,
        'is_protected' => 0,
    ];
    base_write_manifest($newBase, $destination);

    flash('success', 'Base clonada sem módulos. Adicione os módulos desejados na nova base.');
    header('Location: /web/admin/bases/index.php');
    exit;
} catch (Throwable $e) {
    if (is_dir($destination)) {
        removeDirectoryTree($destination);
    }

    flash('error', $e->getMessage());
    header('Location: /web/admin/bases/clone.php?base_id=' . (int)$base['id']);
    exit;
}
