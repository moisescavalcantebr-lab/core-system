<?php
declare(strict_types=1);

function holeriteEscape($value): string
{
    return htmlspecialchars(is_scalar($value) ? (string)$value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function holeriteTypes(): array
{
    return ['monthly' => 'Mensal', 'vacation' => 'Ferias', 'thirteenth' => '13o salario', 'bonus' => 'Bonus', 'adjustment' => 'Complementar'];
}

function holeriteText($value, int $limit, string $label, bool $required = false): string
{
    if (!is_scalar($value) && $value !== null) {
        throw new InvalidArgumentException($label . ' invalido.');
    }
    $text = trim((string)$value);
    if (($required && $text === '') || mb_strlen($text) > $limit) {
        throw new InvalidArgumentException('Confira o campo ' . $label . '.');
    }
    return $text;
}

function holeriteCents($value): int
{
    $value = holeriteText($value, 30, 'valor');
    if (str_contains($value, ',')) {
        if (!preg_match('/^(?:\d+|\d{1,3}(?:\.\d{3})+),\d{1,2}$/D', $value)) {
            throw new InvalidArgumentException('Valor monetario invalido.');
        }
        $value = str_replace(['.', ','], ['', '.'], $value);
    }
    if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', $value, $matches)) {
        throw new InvalidArgumentException('Valor monetario invalido.');
    }
    return ((int)$matches[1] * 100) + (int)str_pad($matches[2] ?? '', 2, '0');
}

function holeriteDecimal(int $cents): string
{
    return ($cents < 0 ? '-' : '') . intdiv(abs($cents), 100) . '.' . str_pad((string)(abs($cents) % 100), 2, '0', STR_PAD_LEFT);
}

function holeriteMoney($decimal): string
{
    return 'R$ ' . number_format((float)$decimal, 2, ',', '.');
}

function holeriteDate($value): ?string
{
    $value = holeriteText($value, 10, 'data');
    if ($value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value || $value < '1900-01-01' || $value > '2200-12-31') {
        throw new InvalidArgumentException('Data invalida.');
    }
    return $value;
}

function holeriteNormalize(array $input): array
{
    $month = holeriteText($input['reference_month'] ?? '', 7, 'competencia', true);
    if (!preg_match('/^\d{4}-\d{2}$/D', $month) || !holeriteDate($month . '-01')) {
        throw new InvalidArgumentException('Competencia invalida.');
    }
    $type = holeriteText($input['receipt_type'] ?? '', 20, 'tipo');
    if (!isset(holeriteTypes()[$type])) {
        throw new InvalidArgumentException('Tipo de holerite invalido.');
    }
    $sequence = filter_var($input['sequence_number'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
    if ($sequence === false) {
        throw new InvalidArgumentException('Sequencia deve ser de 1 a 99.');
    }
    $record = ['reference_month' => $month . '-01', 'receipt_type' => $type, 'sequence_number' => $sequence];
    foreach (['employer' => ['Empresa', 150], 'employee_name' => ['Nome', 150], 'registration' => ['Matricula', 60], 'job_title' => ['Cargo', 150], 'department' => ['Departamento', 150], 'notes' => ['Observacoes', 4000]] as $field => [$label, $limit]) {
        $record[$field] = holeriteText($input[$field] ?? '', $limit, $label, in_array($field, ['employer', 'employee_name'], true));
    }
    foreach (['admission_date', 'issued_date', 'payment_date'] as $field) {
        $record[$field] = holeriteDate($input[$field] ?? '');
    }
    $record['received'] = ($input['received'] ?? '') === '1' ? 1 : 0;
    $record['base_salary'] = holeriteDecimal(holeriteCents($input['base_salary'] ?? '0'));
    foreach (['inss_base', 'irrf_base', 'fgts_base', 'irrf_recomp_base'] as $field) {
        $record[$field] = ($input[$field] ?? '') === '' ? null : holeriteDecimal(holeriteCents($input[$field]));
    }
    $items = $input['items'] ?? [];
    if (!is_array($items) || count($items) > 100) {
        throw new InvalidArgumentException('Lista de itens invalida (limite de 100).');
    }
    $clean = [];
    $gross = $deductions = 0;
    foreach ($items as $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Item invalido.');
        }
        $description = holeriteText($item['description'] ?? '', 150, 'descricao');
        $amount = holeriteText($item['amount'] ?? '', 30, 'valor');
        if ($description === '' && $amount === '') {
            continue;
        }
        $kind = $item['item_type'] ?? '';
        if ($description === '' || !in_array($kind, ['income', 'deduction'], true)) {
            throw new InvalidArgumentException('Preencha a descricao e o tipo de cada item.');
        }
        $cents = holeriteCents($amount);
        $gross += $kind === 'income' ? $cents : 0;
        $deductions += $kind === 'deduction' ? $cents : 0;
        $clean[] = ['event_code' => holeriteText($item['event_code'] ?? '', 30, 'codigo'), 'description' => $description,
            'reference_value' => holeriteText($item['reference_value'] ?? '', 40, 'referencia'), 'item_type' => $kind, 'amount' => holeriteDecimal($cents)];
    }
    if (!$clean || max($gross, $deductions) > 999999999999) {
        throw new InvalidArgumentException('Inclua itens validos e confira os totais.');
    }
    return ['record' => $record + ['gross_amount' => holeriteDecimal($gross), 'deductions_amount' => holeriteDecimal($deductions), 'net_amount' => holeriteDecimal($gross - $deductions)], 'items' => $clean];
}

function holeriteOwner(): int
{
    requireProjectRole(['ADMIN']);
    $user = projectUser();
    if (!$user || ($user['status'] ?? '') !== 'active') {
        http_response_code(403);
        exit('Acesso negado.');
    }
    return (int)$user['id'];
}

function holeriteEnsureSchema(PDO $pdo): void
{
    $schema = defined('PROJECT_PATH') ? PROJECT_PATH . '/modules/meu_holerite/database/schema.sql' : '';
    if (!is_file($schema)) {
        $schema = dirname(__DIR__, 3) . '/database/schema.sql';
    }
    if (!is_file($schema)) {
        throw new RuntimeException('Esquema do Meu Holerite nao encontrado.');
    }
    $pdo->exec((string)file_get_contents($schema));
}

function holeriteFind(PDO $pdo, int $owner, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT r.*, (SELECT original_name FROM holerite_attachments WHERE receipt_id = r.id) AS attachment_name FROM holerite_receipts r WHERE id = ? AND owner_user_id = ?');
    $stmt->execute([$id, $owner]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function holeritePdfValidate(string $name, string $pdf): array
{
    if (strlen($pdf) === 0 || strlen($pdf) > 2 * 1024 * 1024) {
        throw new InvalidArgumentException('O PDF deve ter no maximo 2 MB.');
    }
    $name = basename(str_replace('\\', '/', $name));
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf'
        || !str_starts_with($pdf, '%PDF-')
        || (new finfo(FILEINFO_MIME_TYPE))->buffer($pdf) !== 'application/pdf') {
        throw new InvalidArgumentException('Anexe um arquivo PDF valido.');
    }
    $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);
    return ['name' => mb_substr($name, 0, 200), 'pdf' => $pdf];
}

function holeritePdfUpload($file): ?array
{
    if ($file === null) {
        return null;
    }
    if (!is_array($file) || !is_int($file['error'] ?? null)) {
        throw new InvalidArgumentException('Envio do PDF invalido.');
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Nao foi possivel receber o PDF. Confira o limite de 2 MB e tente novamente.');
    }
    if (!is_string($file['tmp_name'] ?? null) || !is_string($file['name'] ?? null)
        || !is_uploaded_file($file['tmp_name']) || filesize($file['tmp_name']) > 2 * 1024 * 1024) {
        throw new InvalidArgumentException('Envio do PDF invalido ou maior que 2 MB.');
    }
    $pdf = file_get_contents($file['tmp_name']);
    if ($pdf === false) {
        throw new RuntimeException('Nao foi possivel ler o PDF.');
    }
    return holeritePdfValidate($file['name'], $pdf);
}

function holeriteAttachment(PDO $pdo, int $owner, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT a.original_name, a.pdf FROM holerite_attachments a INNER JOIN holerite_receipts r ON r.id = a.receipt_id WHERE r.id = ? AND r.owner_user_id = ?');
    $stmt->execute([$id, $owner]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function holeriteSave(PDO $pdo, int $owner, int $id, array $data, ?array $attachment = null, bool $removeAttachment = false): int
{
    $record = $data['record'];
    $fields = array_keys($record);
    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            $lock = $pdo->prepare('SELECT id FROM holerite_receipts WHERE id = ? AND owner_user_id = ? FOR UPDATE');
            $lock->execute([$id, $owner]);
            if (!$lock->fetchColumn()) {
                throw new RuntimeException('Holerite nao encontrado.');
            }
            $stmt = $pdo->prepare('UPDATE holerite_receipts SET ' . implode(', ', array_map(static fn($field) => $field . ' = ?', $fields)) . ' WHERE id = ? AND owner_user_id = ?');
            $stmt->execute([...array_values($record), $id, $owner]);
            $pdo->prepare('DELETE FROM holerite_items WHERE receipt_id = ?')->execute([$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO holerite_receipts (owner_user_id, ' . implode(', ', $fields) . ') VALUES (' . implode(', ', array_fill(0, count($fields) + 1, '?')) . ')');
            $stmt->execute([$owner, ...array_values($record)]);
            $id = (int)$pdo->lastInsertId();
        }
        $stmt = $pdo->prepare('INSERT INTO holerite_items (receipt_id, event_code, description, reference_value, item_type, amount, display_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($data['items'] as $order => $item) {
            $stmt->execute([$id, $item['event_code'], $item['description'], $item['reference_value'], $item['item_type'], $item['amount'], $order]);
        }
        if ($attachment !== null) {
            $stmt = $pdo->prepare('INSERT INTO holerite_attachments (receipt_id, original_name, pdf) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE original_name = VALUES(original_name), pdf = VALUES(pdf)');
            $stmt->execute([$id, $attachment['name'], $attachment['pdf']]);
        } elseif ($removeAttachment) {
            $pdo->prepare('DELETE FROM holerite_attachments WHERE receipt_id = ?')->execute([$id]);
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function holeriteMonths(array $records): array
{
    $months = [];
    foreach ($records as $record) {
        $key = substr($record['reference_month'], 0, 7);
        $months[$key] ??= ['count' => 0, 'gross_amount' => 0, 'deductions_amount' => 0, 'net_amount' => 0];
        $months[$key]['count']++;
        foreach (['gross_amount', 'deductions_amount', 'net_amount'] as $field) {
            $value = (string)$record[$field];
            $months[$key][$field] += str_starts_with($value, '-') ? -holeriteCents(substr($value, 1)) : holeriteCents($value);
        }
    }
    ksort($months);
    return $months;
}
