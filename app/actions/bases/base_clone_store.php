<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require dirname(__DIR__, 3) . '/app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

/*
|--------------------------------------------------------------------------
| Dados recebidos
|--------------------------------------------------------------------------
*/

$newName = trim($_POST['name'] ?? '');
$newSlug = strtolower(trim($_POST['slug'] ?? ''));
$baseId  = (int)($_POST['base_id'] ?? 0);

if (!$newName || !$newSlug || !$baseId) {
    die('Dados incompletos.');
}

/*
|--------------------------------------------------------------------------
| Sanitizar slug
|--------------------------------------------------------------------------
*/

$newSlug = preg_replace('/[^a-z0-9\-]/', '-', $newSlug);
$newSlug = preg_replace('/-+/', '-', $newSlug);
$newSlug = trim($newSlug, '-');

if (strlen($newSlug) < 3) {
    die('Slug muito curto.');
}

/*
|--------------------------------------------------------------------------
| Buscar base origem
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("SELECT * FROM bases WHERE id = :id");
$stmt->execute(['id' => $baseId]);
$base = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    die('Base não encontrada.');
}

$source = BASES_PATH . '/' . $base['slug'];

if (!is_dir($source)) {
    die('Pasta da base não existe.');
}

/*
|--------------------------------------------------------------------------
| Gerar slug Ãºnico automaticamente
|--------------------------------------------------------------------------
*/

$originalSlug = $newSlug;
$counter = 1;

while (true) {

    $stmt = $pdo->prepare("SELECT id FROM bases WHERE slug = :slug");
    $stmt->execute(['slug' => $newSlug]);
    $existsInDb = $stmt->fetch();

    $existsInFolder = is_dir(BASES_PATH . '/' . $newSlug);

    if (!$existsInDb && !$existsInFolder) {
        break;
    }

    $newSlug = $originalSlug . '-' . str_pad($counter, 2, '0', STR_PAD_LEFT);
    $counter++;
}

$destination = BASES_PATH . '/' . $newSlug;

/*
|--------------------------------------------------------------------------
| Clonar pasta
|--------------------------------------------------------------------------
*/

function copyRecursive($src, $dst) {
    mkdir($dst, 0755, true);

    foreach (scandir($src) as $file) {
        if ($file === '.' || $file === '..') continue;

        if (is_dir("$src/$file")) {
            copyRecursive("$src/$file", "$dst/$file");
        } else {
            copy("$src/$file", "$dst/$file");
        }
    }
}

copyRecursive($source, $destination);

/*
|--------------------------------------------------------------------------
| Registrar nova base (CORRIGIDO)
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    INSERT INTO bases
    (cloned_from_id, name, slug, description, allows_users, max_admins, status, is_protected, created_at)
    VALUES
    (:cloned_from_id, :name, :slug, :description, :allows_users, :max_admins, 1, 0, NOW())
");

$stmt->execute([
    'cloned_from_id' => $base['id'],                //  agora salva origem
    'name'           => $newName,
    'slug'           => $newSlug,
    'description'    => $base['description'] ?? '',
    'allows_users'   => $base['allows_users'] ?? 1,
    'max_admins'     => $base['max_admins'] ?? 1
]);

header("Location: /public/admin/bases/index.php");
exit;