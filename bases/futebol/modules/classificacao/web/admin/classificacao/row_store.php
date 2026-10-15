<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/fields.php';

requireProjectAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$tableId = (int)($_GET['id'] ?? 0);

if ($tableId <= 0) {
    exit('Dados invalidos.');
}

$stmt = $pdo->prepare("SELECT name, active_fields FROM classification_tables WHERE id = ? LIMIT 1");
$stmt->execute([$tableId]);
$table = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$table) {
    exit('Classificacao nao encontrada.');
}

$activeFields = classificationDecodeFields($table['active_fields'] ?? '[]');
$availableFields = classificationAvailableFields();
$submitted = $_POST['fields'] ?? [];
$data = [];

foreach ($activeFields as $field) {
    if (!isset($availableFields[$field])) {
        continue;
    }

    $value = $submitted[$field] ?? null;

    if ($availableFields[$field]['type'] === 'number' && $value !== '' && $value !== null) {
        $data[$field] = (float)$value;
    } else {
        $data[$field] = trim((string)$value);
    }
}

$name = trim((string)($data['name'] ?? ''));

if ($name === '') {
    $name = (string)($table['name'] ?? 'Classificacao');
}

$stmt = $pdo->prepare("INSERT INTO classification_rows (table_id, name, data_json) VALUES (?, ?, ?)");
$stmt->execute([$tableId, $name, json_encode($data)]);

header('Location: ' . PROJECT_URL . '/admin/classificacao/rows.php?id=' . $tableId);
exit;
