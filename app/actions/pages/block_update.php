<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

global $pdo;

/* =========================
INPUT
========================= */

$pageId = (int)($_POST['page_id'] ?? 0);
$index  = (int)($_POST['index'] ?? 0);
$type   = $_POST['type'] ?? '';

if (!$pageId || trim($type) === '') {
    exit('Dados inválidos');
}

/* sanitizar */
$type = preg_replace('/[^a-z0-9_\-]/i', '', $type);

if ($type === '') {
    exit('Tipo inválido');
}

/* =========================
VALIDAR SCHEMA
========================= */

$schema = require APP_PATH . '/config/blocks.php';

if (!isset($schema[$type])) {
    exit('Tipo de bloco inválido');
}

/* =========================
BUSCAR PÁGINA
========================= */

$stmt = $pdo->prepare("
SELECT content_path
FROM core_page_contents
WHERE id = :id
");

$stmt->execute(['id' => $pageId]);

$contentPath = $stmt->fetchColumn();

if (!$contentPath) {
    exit('Página não encontrada');
}

/* =========================
BASE URL
========================= */

$baseUrl = '';

if (defined('PROJECT_PATH')) {
    $baseUrl = '/projects/' . basename(PROJECT_PATH);
}

/* =========================
CAMINHO JSON
========================= */

$jsonPath = STORAGE_PATH . '/paginas/pages/' . $contentPath;

/* garantir pasta */
$dir = dirname($jsonPath);

if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

/* =========================
CARREGAR JSON
========================= */

$data = [];

if (file_exists($jsonPath)) {
    $decoded = json_decode(file_get_contents($jsonPath), true);
    $data = is_array($decoded) ? $decoded : [];
}

$data['blocks'] = $data['blocks'] ?? [];
$blocks = $data['blocks'];

/* =========================
VALIDAR INDEX
========================= */

if (!isset($blocks[$index])) {
    exit('Bloco inválido');
}

$oldBlock = $blocks[$index];

/* =========================
MONTAR BLOCO NOVO
========================= */

$newBlock = [
    'type'    => $type,
    'enabled' => $oldBlock['enabled'] ?? true
];

$fields = $schema[$type]['fields'] ?? [];

foreach ($fields as $fieldName => $fieldConfig) {

    $editable  = $fieldConfig['editable'] ?? true;
    $typeField = $fieldConfig['type'] ?? 'text';

    /* =========================
    NÃO EDITÁVEL
    ========================= */

    if (!$editable) {
        $newBlock[$fieldName] =
            $oldBlock[$fieldName]
            ?? $fieldConfig['default']
            ?? null;
        continue;
    }

    /* =========================
    GROUP (REPEATER)
    ========================= */

    if ($typeField === 'group') {

        $items = $_POST[$fieldName] ?? [];

        if (!is_array($items)) {
            $items = [];
        }

        $groupFields = $fieldConfig['fields'] ?? [];
        $cleanItems = [];

        foreach ($items as $i => $item) {

            if (!is_array($item)) continue;

            $cleanItem = [];

            foreach ($groupFields as $subField => $subConfig) {

                $val = $item[$subField] ?? null;

                if ($val === null && array_key_exists('default', $subConfig)) {
                    $val = $subConfig['default'];
                }

                if (is_string($val)) {
                    $val = trim($val);
                }

                $cleanItem[$subField] = $val;
            }

            // evita salvar item vazio
            if (!empty(array_filter($cleanItem))) {
                $cleanItems[] = $cleanItem;
            }
        }

        $newBlock[$fieldName] = $cleanItems;

        continue;
    }

    /* =========================
    VALOR NORMAL
    ========================= */

    $value = $_POST[$fieldName] ?? $oldBlock[$fieldName] ?? null;

    if ($value === null && array_key_exists('default', $fieldConfig)) {
        $value = $fieldConfig['default'];
    }

    /* =========================
    ARRAY SIMPLES
    ========================= */

    if (is_array($value)) {

        $value = array_map(function ($v) {
            return is_string($v) ? trim($v) : $v;
        }, $value);

    }

    /* =========================
    STRING
    ========================= */

    elseif (is_string($value)) {

        $value = trim($value);

        // JSON auto decode
        if (
            (str_starts_with($value, '{') && str_ends_with($value, '}')) ||
            (str_starts_with($value, '[') && str_ends_with($value, ']'))
        ) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }
    }

    $newBlock[$fieldName] = $value;
}
/* =========================
ATUALIZAR
========================= */

$blocks[$index] = $newBlock;

/* ?? CRÍTICO: normalizar índices */
$blocks = array_values($blocks);

$data['blocks'] = $blocks;

/* =========================
SALVAR
========================= */

$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if ($json === false) {
    error_log('Erro ao gerar JSON: ' . json_last_error_msg());
    exit('Erro ao salvar dados');
}

file_put_contents($jsonPath, $json);

/* =========================
REDIRECT
========================= */

header("Location: {$baseUrl}/public/admin/pages/edit.php?id={$pageId}");
exit;