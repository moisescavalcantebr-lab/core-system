<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

/* =========================
LER JSON (FETCH)
========================= */

$raw = file_get_contents('php://input');

$input = json_decode($raw, true);

if (!is_array($input)) {
    http_response_code(400);
    echo 'Payload inválido';
    exit;
}

$type = $input['type'] ?? null;
$data = $input['data'] ?? [];

/* =========================
VALIDAÇÃO
========================= */

if (!$type) {
    http_response_code(400);
    echo 'Tipo inválido';
    exit;
}

/* =========================
SANITIZAR TYPE
========================= */

$type = preg_replace('/[^a-z0-9_\-]/i', '', $type);

if ($type === '') {
    http_response_code(400);
    echo 'Tipo inválido';
    exit;
}

/* =========================
VALIDAR NO SCHEMA (CORE)
========================= */

$schema = require APP_PATH . '/config/blocks.php';

if (!isset($schema[$type])) {
    http_response_code(403);
    echo 'Bloco não permitido';
    exit;
}

/* =========================
GARANTIR ARRAY
========================= */

if (!is_array($data)) {
    $data = [];
}

/* =========================
LOCALIZAR BLOCO
========================= */

$blockFile = STORAGE_PATH . '/paginas/blocks/' . $type . '.php';

if (!file_exists($blockFile)) {
    http_response_code(404);
    echo "Bloco não encontrado: " . htmlspecialchars($type);
    exit;
}

/* =========================
RENDER
========================= */

ob_start();

try {

    // padrão interno do sistema
    $config = $data;

    include $blockFile;

} catch (Throwable $e) {

    http_response_code(500);

    echo "<div style='padding:10px;border:1px solid #f00;color:#f00'>
            Erro ao renderizar bloco:<br>
            <small>" . htmlspecialchars($e->getMessage()) . "</small>
          </div>";
}

echo ob_get_clean();