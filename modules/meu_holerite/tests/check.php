<?php
declare(strict_types=1);
require dirname(__DIR__) . '/web/admin/meu_holerite/helpers.php';

function holeriteCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function holeriteReject(callable $callback): void
{
    try {
        $callback();
    } catch (InvalidArgumentException $e) {
        return;
    }
    throw new RuntimeException('Entrada invalida foi aceita.');
}

foreach (['1.234,56' => 123456, '1234.56' => 123456, '0,01' => 1, '12.5' => 1250, '0' => 0] as $value => $expected) {
    holeriteCheck(holeriteCents((string)$value) === $expected, 'Conversao monetaria.');
}
foreach (['-1', '1e3', '1,234', '1.234', 'abc', [], '10000000000'] as $value) {
    holeriteReject(static fn() => holeriteCents($value));
}
holeriteCheck(holeriteDate('2024-02-29') === '2024-02-29', 'Ano bissexto.');
holeriteReject(static fn() => holeriteDate('2025-02-29'));
holeriteCheck(holeriteEscape('<script>') === '&lt;script&gt;', 'Escape HTML.');
$input = ['employer' => 'Empresa de teste', 'employee_name' => 'Pessoa de teste', 'reference_month' => '2025-02', 'receipt_type' => 'monthly', 'base_salary' => '1000.00', 'items' => [
    ['description' => 'Salario', 'item_type' => 'income', 'amount' => '1.000,00'],
    ['description' => 'Adicional', 'item_type' => 'income', 'amount' => '50,01'],
    ['description' => 'Desconto', 'item_type' => 'deduction', 'amount' => '100,02'],
    ['description' => '', 'amount' => ''],
]];
$data = holeriteNormalize($input);
holeriteCheck($data['record']['gross_amount'] === '1050.01' && $data['record']['deductions_amount'] === '100.02' && $data['record']['net_amount'] === '949.99', 'Totais exatos.');
holeriteCheck(count($data['items']) === 3 && $data['record']['inss_base'] === null, 'Itens vazios e bases opcionais.');
foreach ([['reference_month' => '2025-13'], ['receipt_type' => 'invalid'], ['sequence_number' => 0], ['employer' => ''], ['items' => []], ['items' => array_fill(0, 101, [])]] as $invalid) {
    holeriteReject(static fn() => holeriteNormalize(array_replace($input, $invalid)));
}
$negative = holeriteNormalize(array_replace($input, ['items' => [['description' => 'Desconto', 'item_type' => 'deduction', 'amount' => '10']]]));
holeriteCheck($negative['record']['net_amount'] === '-10.00', 'Liquido negativo.');
$months = holeriteMonths([$data['record'], $negative['record']]);
holeriteCheck($months['2025-02']['net_amount'] === 93999 && $months['2025-02']['count'] === 2, 'Agrupamento da competencia.');
$pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
$attachment = holeritePdfValidate('holerite.pdf', $pdf);
holeriteCheck($attachment['pdf'] === $pdf && $attachment['name'] === 'holerite.pdf', 'PDF valido.');
holeriteReject(static fn() => holeritePdfValidate('holerite.txt', $pdf));
holeriteReject(static fn() => holeritePdfValidate('holerite.pdf', '<html>nao e PDF</html>'));
holeriteReject(static fn() => holeritePdfValidate('holerite.pdf', ''));
holeriteReject(static fn() => holeritePdfValidate('holerite.pdf', $pdf . str_repeat('x', 2 * 1024 * 1024)));
holeriteCheck(holeritePdfUpload(null) === null && holeritePdfUpload(['error' => UPLOAD_ERR_NO_FILE]) === null, 'Anexo opcional.');
holeriteReject(static fn() => holeritePdfUpload(['error' => UPLOAD_ERR_INI_SIZE]));
holeriteReject(static fn() => holeritePdfUpload(['error' => [], 'name' => []]));
holeriteReject(static fn() => holeritePdfUpload(['error' => UPLOAD_ERR_OK, 'name' => 'fake.pdf', 'tmp_name' => __FILE__]));
echo "Meu Holerite: validacao de dinheiro, datas, itens, totais, agrupamento e PDF OK.\n";

if (getenv('HOLERITE_MYSQL_TEST') === '1') {
    $database = 'holerite_verify_' . bin2hex(random_bytes(5));
    $server = new PDO('mysql:host=app_db;charset=utf8mb4', 'root', 'root', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $server->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4');
    try {
        $pdo = new PDO('mysql:host=app_db;dbname=' . $database . ';charset=utf8mb4', 'root', 'root', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        holeriteEnsureSchema($pdo);
        holeriteEnsureSchema($pdo);
        $id = holeriteSave($pdo, 10, 0, $data, $attachment);
        holeriteCheck(holeriteAttachment($pdo, 10, $id)['pdf'] === $pdf, 'Persistencia do PDF.');
        holeriteCheck(holeriteAttachment($pdo, 11, $id) === null, 'Isolamento do download.');
        holeriteCheck(holeriteFind($pdo, 10, $id)['net_amount'] === '949.99', 'Persistencia.');
        holeriteCheck(holeriteFind($pdo, 11, $id) === null, 'Isolamento de leitura.');
        try {
            holeriteSave($pdo, 11, $id, $negative);
            throw new LogicException('Alteracao por outro usuario aceita.');
        } catch (RuntimeException $e) {
            holeriteCheck($e->getMessage() === 'Holerite nao encontrado.', 'Isolamento de alteracao.');
        }
        try {
            holeriteSave($pdo, 10, 0, $data);
            throw new LogicException('Duplicidade aceita.');
        } catch (PDOException $e) {
            holeriteCheck((int)$e->errorInfo[1] === 1062, 'Duplicidade bloqueada.');
        }
        holeriteSave($pdo, 10, $id, $negative);
        holeriteCheck(holeriteAttachment($pdo, 10, $id)['pdf'] === $pdf, 'Preservacao do PDF sem novo upload.');
        $replacement = holeritePdfValidate('novo.pdf', $pdf . '\n');
        holeriteSave($pdo, 10, $id, $negative, $replacement);
        holeriteCheck(holeriteFind($pdo, 10, $id)['attachment_name'] === 'novo.pdf', 'Substituicao do PDF.');
        holeriteSave($pdo, 10, $id, $negative, null, true);
        holeriteCheck(holeriteAttachment($pdo, 10, $id) === null, 'Remocao explicita do PDF.');
        holeriteSave($pdo, 10, $id, $negative, $attachment);
        holeriteCheck((int)$pdo->query('SELECT COUNT(*) FROM holerite_items')->fetchColumn() === 1, 'Substituicao dos itens.');
        holeriteSave($pdo, 11, 0, $data);
        $pdo->prepare('DELETE FROM holerite_receipts WHERE id = ? AND owner_user_id = ?')->execute([$id, 10]);
        holeriteCheck((int)$pdo->query('SELECT COUNT(*) FROM holerite_items')->fetchColumn() === 3, 'Exclusao em cascata isolada.');
        holeriteCheck((int)$pdo->query('SELECT COUNT(*) FROM holerite_attachments')->fetchColumn() === 0, 'Exclusao do PDF em cascata.');
        echo "Meu Holerite: MySQL, duplicidade, edicao, isolamento e exclusao OK.\n";
    } finally {
        $server->exec('DROP DATABASE `' . $database . '`');
    }
}
