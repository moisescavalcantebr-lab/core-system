<?php
declare(strict_types=1);
require dirname(__DIR__) . '/web/admin/beneficios/helpers.php';

function benefitCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$input = ['employer' => 'Empresa teste', 'registration' => '123', 'reference_month' => '2026-09', 'benefit_type' => 'transport', 'credited_amount' => '220,50', 'expected_amount' => '230,00', 'credited_date' => '2026-09-01'];
$record = benefitNormalize($input);
benefitCheck(benefitTypes()['meal'] === 'Vale-alimentacao', 'Nome do beneficio e compatibilidade dos registros.');
benefitCheck($record['credited_amount'] === '220.50' && $record['expected_amount'] === '230.00', 'Valores em centavos.');
benefitCheck(benefitNormalize(array_replace($input, ['expected_amount' => '', 'credited_date' => '']))['expected_amount'] === null, 'Previsto e data opcionais.');
benefitCheck(benefitNormalize(array_replace($input, ['credited_amount' => '0']))['credited_amount'] === '0.00', 'Credito zero explicito.');
foreach ([['benefit_type' => 'invalid'], ['reference_month' => '2026-13'], ['credited_amount' => '-1'], ['credited_amount' => ''], ['credited_amount' => '10000000000'], ['expected_amount' => []], ['employer' => ''], ['credited_date' => '2026-02-30']] as $invalid) {
    try {
        benefitNormalize(array_replace($input, $invalid));
        throw new LogicException('Entrada invalida aceita.');
    } catch (InvalidArgumentException $e) {
    }
}
$meal = benefitNormalize(array_replace($input, ['benefit_type' => 'meal', 'credited_amount' => '600', 'expected_amount' => '']));
$months = benefitMonths([$record, $meal]);
benefitCheck($months['2026-09']['transport'] === 22050 && $months['2026-09']['meal'] === 60000, 'Totais separados.');
benefitCheck(benefitMonthValue(null, 'transport') === '-' && benefitMonthChange($months['2026-09'], null, 'transport') === '-', 'Ausencia nao significa credito zero.');
$onlyTransport = benefitMonths([$record]);
benefitCheck(benefitMonthValue($onlyTransport['2026-09'], 'meal') === '-', 'Tipo nao cadastrado.');
echo "Beneficios: validacao, opcionais e agrupamento OK.\n";

if (getenv('BENEFITS_MYSQL_TEST') === '1') {
    $database = 'benefits_verify_' . bin2hex(random_bytes(5));
    $server = new PDO('mysql:host=app_db;charset=utf8mb4', 'root', 'root', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $server->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4');
    try {
        $pdo = new PDO('mysql:host=app_db;dbname=' . $database . ';charset=utf8mb4', 'root', 'root', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        benefitEnsureSchema($pdo);
        benefitEnsureSchema($pdo);
        $pdf = holeritePdfValidate('credito.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
        $id = benefitSave($pdo, 10, 0, $record, $pdf);
        benefitCheck(benefitFind($pdo, 10, $id)['credited_amount'] === '220.50', 'Persistencia sem holerite.');
        benefitCheck(benefitFind($pdo, 11, $id) === null && benefitAttachment($pdo, 11, $id) === null && benefitRecords($pdo, 11) === [], 'Isolamento de leitura.');
        foreach (['update', 'delete'] as $operation) {
            try {
                if ($operation === 'update') {
                    benefitSave($pdo, 11, $id, $record);
                } else {
                    benefitDelete($pdo, 11, $id);
                }
                throw new LogicException('Operacao por outro usuario aceita.');
            } catch (RuntimeException $e) {
                benefitCheck($e->getMessage() === 'Beneficio nao encontrado.', 'Isolamento de escrita.');
            }
        }
        try {
            benefitSave($pdo, 10, 0, $record);
            throw new LogicException('Duplicidade aceita.');
        } catch (PDOException $e) {
            benefitCheck((int)$e->errorInfo[1] === 1062, 'Duplicidade bloqueada.');
        }
        benefitSave($pdo, 10, $id, array_replace($record, ['credited_amount' => '250.00']));
        benefitCheck(benefitAttachment($pdo, 10, $id)['pdf'] === $pdf['pdf'], 'Preservacao do comprovante.');
        $replacement = holeritePdfValidate('novo.pdf', $pdf['pdf'] . '\n');
        benefitSave($pdo, 10, $id, $record, $replacement);
        benefitCheck(benefitFind($pdo, 10, $id)['attachment_name'] === 'novo.pdf', 'Substituicao do comprovante.');
        benefitSave($pdo, 10, $id, $record, null, true);
        benefitCheck(benefitAttachment($pdo, 10, $id) === null, 'Remocao do comprovante.');
        benefitSave($pdo, 10, 0, $meal);
        benefitSave($pdo, 11, 0, $record);
        benefitSave($pdo, 10, 0, array_replace($record, ['registration' => 'outro vinculo']));
        benefitCheck(count(benefitRecords($pdo, 10)) === 3, 'Tipos e vinculos independentes.');
        benefitDelete($pdo, 10, $id);
        benefitCheck(count(benefitRecords($pdo, 10)) === 2 && count(benefitRecords($pdo, 11)) === 1, 'Exclusao isolada.');
        echo "Beneficios: MySQL, isolamento, duplicidade, edicao e PDF OK.\n";
    } finally {
        $server->exec('DROP DATABASE `' . $database . '`');
    }
}
