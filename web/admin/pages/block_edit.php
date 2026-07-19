<?php
declare(strict_types=1);

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

global $pdo;

/* =========================
INPUT
========================= */

$pageId = (int)($_GET['page_id'] ?? 0);
$index  = (int)($_GET['index'] ?? 0);

if (!$pageId) {
    exit('Página inválida');
}

/* =========================
BUSCAR PÁGINA
========================= */

$stmt = $pdo->prepare("SELECT * FROM core_page_contents WHERE id=:id");
$stmt->execute(['id'=>$pageId]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    exit('Página não encontrada');
}

/* =========================
JSON
========================= */

$jsonPath = STORAGE_PATH . '/paginas/pages/' . $page['content_path'];

$data = file_exists($jsonPath)
    ? json_decode(file_get_contents($jsonPath), true)
    : [];

$blocks = $data['blocks'] ?? [];

$block = $blocks[$index] ?? null;

if (!$block) {
    exit('Bloco inválido');
}

$type = $block['type'];

/* =========================
VIEW DO BLOCO
========================= */

$viewPath = APP_PATH . "/views/blocks/edit/{$type}.php";

if (!file_exists($viewPath)) {
    exit("Editor não encontrado para: {$type}");
}

/* =========================
RENDER
========================= */

ob_start();

require $viewPath;

$content = ob_get_clean();

/* =========================
SIDEBAR (OPCIONAL)
========================= */

$rightSidebarEnabled = true;

$rightSidebarContent = '
<div class="c-card">
    <h3>Dica</h3>
    <p>Edite o bloco e salve.<br>Você voltará para a página.</p>
</div>
';

require APP_PATH . '/views/layout_admin.php';