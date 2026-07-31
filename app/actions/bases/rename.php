<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require dirname(__DIR__, 3) . '/app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/helpers/base_manifest.php';

requireAdmin();
csrf_verify();
coreRequireLaboratory('/web/admin/bases/index.php', 'Renomear bases fica disponivel apenas no laboratorio.');

$baseId = (int)($_POST['base_id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));

if ($baseId <= 0) {
    flash('error', 'Base invalida.');
    redirect('/web/admin/bases/index.php');
}

$name = preg_replace('/\s+/', ' ', $name) ?? '';
$name = trim($name);

if ($name === '' || strlen($name) > 150) {
    flash('error', 'Informe um nome de base com ate 150 caracteres.');
    redirect('/web/admin/bases/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM bases WHERE id = ? LIMIT 1');
$stmt->execute([$baseId]);
$base = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    flash('error', 'Base nao encontrada.');
    redirect('/web/admin/bases/index.php');
}

if ((string)$base['slug'] === 'base') {
    flash('error', 'A base principal mantem o nome padrao da matriz local.');
    redirect('/web/admin/bases/index.php');
}

if (base_is_locked($base)) {
    flash('error', 'Reabra a base no laboratorio antes de renomear.');
    redirect('/web/admin/bases/index.php');
}

$base['name'] = $name;
$basePath = BASES_PATH . '/' . $base['slug'];

try {
    $pdo->prepare('UPDATE bases SET name = ? WHERE id = ?')->execute([$name, $baseId]);

    if (is_dir($basePath)) {
        base_write_manifest($base, $basePath);
    }

    flash('success', 'Nome da base atualizado.');
} catch (Throwable $e) {
    flash('error', 'Nao foi possivel renomear a base: ' . $e->getMessage());
}

redirect('/web/admin/bases/index.php');
