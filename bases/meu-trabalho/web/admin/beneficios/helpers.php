<?php
declare(strict_types=1);
$holeriteHelpers = dirname(__DIR__) . '/meu_holerite/helpers.php';
if (!is_file($holeriteHelpers)) {
    $holeriteHelpers = dirname(__DIR__, 4) . '/meu_holerite/web/admin/meu_holerite/helpers.php';
}
require_once $holeriteHelpers;

function benefitTypes(): array
{
    return ['transport' => 'Vale-transporte', 'meal' => 'Vale-alimentacao'];
}

function benefitEnsureSchema(PDO $pdo): void
{
    $schema = defined('PROJECT_PATH') ? PROJECT_PATH . '/modules/beneficios/database/schema.sql' : '';
    if (!is_file($schema)) {
        $schema = dirname(__DIR__, 3) . '/database/schema.sql';
    }
    if (!is_file($schema)) {
        throw new RuntimeException('Esquema de Beneficios nao encontrado.');
    }
    $pdo->exec((string)file_get_contents($schema));
}

function benefitNormalize(array $input): array
{
    $month = holeriteText($input['reference_month'] ?? '', 7, 'competencia', true);
    if (!preg_match('/^\d{4}-\d{2}$/D', $month) || !holeriteDate($month . '-01')) {
        throw new InvalidArgumentException('Competencia invalida.');
    }
    $type = holeriteText($input['benefit_type'] ?? '', 20, 'beneficio', true);
    if (!isset(benefitTypes()[$type])) {
        throw new InvalidArgumentException('Beneficio invalido.');
    }
    $credited = holeriteCents($input['credited_amount'] ?? '');
    $expected = ($input['expected_amount'] ?? '') === '' ? null : holeriteCents($input['expected_amount']);
    if ($credited > 999999999999 || ($expected !== null && $expected > 999999999999)) {
        throw new InvalidArgumentException('Confira os valores do beneficio.');
    }
    return [
        'employer' => holeriteText($input['employer'] ?? '', 150, 'Empresa', true),
        'registration' => holeriteText($input['registration'] ?? '', 60, 'Matricula'),
        'reference_month' => $month . '-01', 'benefit_type' => $type,
        'expected_amount' => $expected === null ? null : holeriteDecimal($expected),
        'credited_amount' => holeriteDecimal($credited),
        'credited_date' => holeriteDate($input['credited_date'] ?? ''),
        'notes' => holeriteText($input['notes'] ?? '', 4000, 'Observacoes'),
    ];
}

function benefitRecords(PDO $pdo, int $owner): array
{
    $stmt = $pdo->prepare('SELECT id, employer, registration, reference_month, benefit_type, expected_amount, credited_amount, credited_date, notes, attachment_name FROM holerite_benefits WHERE owner_user_id = ? ORDER BY reference_month DESC, id DESC');
    $stmt->execute([$owner]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function benefitFind(PDO $pdo, int $owner, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, employer, registration, reference_month, benefit_type, expected_amount, credited_amount, credited_date, notes, attachment_name FROM holerite_benefits WHERE id = ? AND owner_user_id = ?');
    $stmt->execute([$id, $owner]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function benefitAttachment(PDO $pdo, int $owner, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT attachment_pdf AS pdf FROM holerite_benefits WHERE id = ? AND owner_user_id = ? AND attachment_pdf IS NOT NULL');
    $stmt->execute([$id, $owner]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function benefitSave(PDO $pdo, int $owner, int $id, array $record, ?array $attachment = null, bool $removeAttachment = false): int
{
    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT id FROM holerite_benefits WHERE id = ? AND owner_user_id = ? FOR UPDATE');
            $stmt->execute([$id, $owner]);
            if (!$stmt->fetchColumn()) {
                throw new RuntimeException('Beneficio nao encontrado.');
            }
        }
        if ($attachment !== null) {
            $record['attachment_name'] = $attachment['name'];
            $record['attachment_pdf'] = $attachment['pdf'];
        } elseif ($removeAttachment) {
            $record['attachment_name'] = $record['attachment_pdf'] = null;
        }
        $fields = array_keys($record);
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE holerite_benefits SET ' . implode(', ', array_map(static fn($field) => $field . ' = ?', $fields)) . ' WHERE id = ? AND owner_user_id = ?');
            $stmt->execute([...array_values($record), $id, $owner]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO holerite_benefits (owner_user_id, ' . implode(', ', $fields) . ') VALUES (' . implode(', ', array_fill(0, count($fields) + 1, '?')) . ')');
            $stmt->execute([$owner, ...array_values($record)]);
            $id = (int)$pdo->lastInsertId();
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function benefitDelete(PDO $pdo, int $owner, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM holerite_benefits WHERE id = ? AND owner_user_id = ?');
    $stmt->execute([$id, $owner]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Beneficio nao encontrado.');
    }
}

function benefitMonths(array $records): array
{
    $months = [];
    foreach ($records as $row) {
        $key = substr($row['reference_month'], 0, 7);
        $months[$key] ??= ['transport' => 0, 'meal' => 0, 'transport_count' => 0, 'meal_count' => 0];
        $months[$key][$row['benefit_type']] += holeriteCents($row['credited_amount']);
        $months[$key][$row['benefit_type'] . '_count']++;
    }
    ksort($months);
    return $months;
}

function benefitMonthValue(?array $month, string $type): string
{
    return !empty($month[$type . '_count']) ? holeriteMoney(holeriteDecimal($month[$type])) : '-';
}

function benefitMonthChange(?array $month, ?array $previous, string $type): string
{
    return !empty($month[$type . '_count']) && !empty($previous[$type . '_count'])
        ? holeriteMoney(holeriteDecimal($month[$type] - $previous[$type])) : '-';
}
