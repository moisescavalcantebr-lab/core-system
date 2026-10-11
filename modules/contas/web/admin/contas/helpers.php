<?php
declare(strict_types=1);

function contasEscape($value): string
{
    return htmlspecialchars(is_scalar($value) ? (string)$value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function contasText($value, int $limit, string $label, bool $required = false): string
{
    if (!is_scalar($value) && $value !== null) {
        throw new InvalidArgumentException($label . ' invalido.');
    }
    $value = trim((string)$value);
    if (($required && $value === '') || mb_strlen($value) > $limit) {
        throw new InvalidArgumentException('Confira o campo ' . $label . '.');
    }
    return $value;
}

function contasCents($value): int
{
    $value = contasText($value, 30, 'valor');
    if (str_contains($value, ',')) {
        if (!preg_match('/^(?:\d+|\d{1,3}(?:\.\d{3})+),\d{1,2}$/D', $value)) {
            throw new InvalidArgumentException('Valor monetario invalido.');
        }
        $value = str_replace(['.', ','], ['', '.'], $value);
    }
    if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', $value, $matches)) {
        throw new InvalidArgumentException('Valor monetario invalido.');
    }
    return (int)$matches[1] * 100 + (int)str_pad($matches[2] ?? '', 2, '0');
}

function contasDecimal(int $cents): string
{
    return ($cents < 0 ? '-' : '') . intdiv(abs($cents), 100) . '.' . str_pad((string)(abs($cents) % 100), 2, '0', STR_PAD_LEFT);
}

function contasMoney(int $cents): string
{
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
}

function contasToday(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
}

function contasDate($value): string
{
    $value = contasText($value, 10, 'data', true);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value || $value < '1900-01-01' || $value > '2200-12-31') {
        throw new InvalidArgumentException('Data invalida.');
    }
    return $value;
}

function contasOwner(): int
{
    requireProjectRole(['ADMIN']);
    $user = projectUser();
    if (!$user || ($user['status'] ?? '') !== 'active') {
        http_response_code(403);
        exit('Acesso negado.');
    }
    return (int)$user['id'];
}

function contasKinds(): array
{
    return ['payable' => 'A pagar', 'receivable' => 'A receber'];
}

function contasMethods(): array
{
    return ['pix' => 'Pix', 'cash' => 'Dinheiro', 'transfer' => 'Transferencia', 'card' => 'Cartao', 'other' => 'Outro'];
}

function contasEnsureSchema(PDO $pdo): void
{
    $path = defined('PROJECT_PATH') ? PROJECT_PATH . '/modules/contas/database/schema.sql' : '';
    if (!is_file($path)) {
        $path = dirname(__DIR__, 3) . '/database/schema.sql';
    }
    if (!is_file($path)) {
        throw new RuntimeException('Esquema de Contas nao encontrado.');
    }
    $pdo->exec((string)file_get_contents($path));
}

function contasNormalize(array $input): array
{
    $kind = contasText($input['kind'] ?? '', 20, 'tipo', true);
    $amount = contasCents($input['amount'] ?? '');
    if (!isset(contasKinds()[$kind]) || $amount <= 0 || $amount > 999999999999) {
        throw new InvalidArgumentException('Confira o tipo e o valor da conta.');
    }
    return ['kind' => $kind, 'title' => contasText($input['title'] ?? '', 150, 'Descricao', true),
        'counterparty' => contasText($input['counterparty'] ?? '', 150, 'Pessoa ou empresa'),
        'category' => contasText($input['category'] ?? '', 80, 'Categoria'), 'amount' => contasDecimal($amount),
        'due_date' => contasDate($input['due_date'] ?? ''), 'notes' => contasText($input['notes'] ?? '', 4000, 'Observacoes')];
}

function contasRecords(PDO $pdo, int $owner): array
{
    $stmt = $pdo->prepare('SELECT e.id, e.kind, e.title, e.counterparty, e.category, e.amount, e.due_date, e.canceled, e.notes, e.attachment_name, COALESCE((SELECT SUM(p.amount) FROM accounts_payments p WHERE p.account_id = e.id),0) AS paid_amount FROM accounts_entries e WHERE owner_user_id = ? ORDER BY due_date, id');
    $stmt->execute([$owner]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function contasFind(PDO $pdo, int $owner, int $id, bool $lock = false): ?array
{
    $stmt = $pdo->prepare('SELECT e.id, e.kind, e.title, e.counterparty, e.category, e.amount, e.due_date, e.canceled, e.notes, e.attachment_name FROM accounts_entries e WHERE id = ? AND owner_user_id = ?' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$id, $owner]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM accounts_payments WHERE account_id = ?');
    $stmt->execute([$id]);
    $row['paid_amount'] = (string)$stmt->fetchColumn();
    return $row;
}

function contasRemaining(array $row): int
{
    return contasCents($row['amount']) - contasCents($row['paid_amount']);
}

function contasStatus(array $row, string $today): string
{
    if ((int)$row['canceled'] === 1) {
        return 'canceled';
    }
    if (contasRemaining($row) === 0) {
        return 'settled';
    }
    if ($row['due_date'] < $today) {
        return 'overdue';
    }
    return contasCents($row['paid_amount']) > 0 ? 'partial' : 'open';
}

function contasStatusLabels(): array
{
    return ['open' => 'Em aberto', 'partial' => 'Parcial', 'overdue' => 'Vencida', 'settled' => 'Quitada', 'canceled' => 'Cancelada'];
}

function contasPdfValidate(string $name, string $pdf): array
{
    $name = basename(str_replace('\\', '/', $name));
    if (strlen($pdf) === 0 || strlen($pdf) > 2 * 1024 * 1024 || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf'
        || !str_starts_with($pdf, '%PDF-') || (new finfo(FILEINFO_MIME_TYPE))->buffer($pdf) !== 'application/pdf') {
        throw new InvalidArgumentException('Anexe um PDF valido de ate 2 MB.');
    }
    return ['name' => mb_substr(preg_replace('/[\x00-\x1F\x7F]/', '', $name), 0, 200), 'pdf' => $pdf];
}

function contasUpload($file): ?array
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
    if ($file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_string($file['name'] ?? null)
        || !is_uploaded_file($file['tmp_name']) || filesize($file['tmp_name']) > 2 * 1024 * 1024) {
        throw new InvalidArgumentException('Nao foi possivel receber o PDF. Confira o limite de 2 MB.');
    }
    $pdf = file_get_contents($file['tmp_name']);
    if ($pdf === false) {
        throw new RuntimeException('Nao foi possivel ler o PDF.');
    }
    return contasPdfValidate($file['name'], $pdf);
}

function contasAttachment(PDO $pdo, int $owner, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT attachment_pdf AS pdf FROM accounts_entries WHERE id = ? AND owner_user_id = ? AND attachment_pdf IS NOT NULL');
    $stmt->execute([$id, $owner]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function contasSave(PDO $pdo, int $owner, int $id, array $record, ?array $attachment = null, bool $removePdf = false): int
{
    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            $existing = contasFind($pdo, $owner, $id, true);
            if (!$existing) {
                throw new RuntimeException('Conta nao encontrada.');
            }
            if (contasCents($record['amount']) < contasCents($existing['paid_amount'])
                || (contasCents($existing['paid_amount']) > 0 && $record['kind'] !== $existing['kind'])) {
                throw new InvalidArgumentException('O valor nao pode ser menor que o ja movimentado; contas com movimentacoes nao podem trocar de tipo.');
            }
        }
        if ($attachment !== null) {
            $record['attachment_name'] = $attachment['name'];
            $record['attachment_pdf'] = $attachment['pdf'];
        } elseif ($removePdf) {
            $record['attachment_name'] = $record['attachment_pdf'] = null;
        }
        $fields = array_keys($record);
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE accounts_entries SET ' . implode(', ', array_map(static fn($field) => $field . ' = ?', $fields)) . ' WHERE id = ? AND owner_user_id = ?');
            $stmt->execute([...array_values($record), $id, $owner]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO accounts_entries (owner_user_id, ' . implode(', ', $fields) . ') VALUES (' . implode(', ', array_fill(0, count($fields) + 1, '?')) . ')');
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

function contasPayments(PDO $pdo, int $owner): array
{
    $stmt = $pdo->prepare('SELECT p.id, p.account_id, p.amount, p.paid_date, p.method, p.notes, e.kind, e.category, e.title FROM accounts_payments p INNER JOIN accounts_entries e ON e.id = p.account_id WHERE e.owner_user_id = ? ORDER BY p.paid_date DESC, p.id DESC');
    $stmt->execute([$owner]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function contasPay(PDO $pdo, int $owner, int $id, array $input, ?string $today = null): void
{
    $amount = contasCents($input['payment_amount'] ?? '');
    $date = contasDate($input['paid_date'] ?? '');
    $method = contasText($input['method'] ?? '', 30, 'Forma de pagamento', true);
    $notes = contasText($input['payment_notes'] ?? '', 500, 'Observacoes');
    $requestKey = contasText($input['payment_token'] ?? '', 32, 'Envio');
    if ($requestKey !== '' && !preg_match('/^[a-f0-9]{32}$/D', $requestKey)) {
        throw new InvalidArgumentException('Envio invalido.');
    }
    if ($amount <= 0 || $date > ($today ?? contasToday()) || !isset(contasMethods()[$method])) {
        throw new InvalidArgumentException('Confira o valor, a data (nao futura) e a forma de pagamento.');
    }
    $pdo->beginTransaction();
    try {
        $entry = contasFind($pdo, $owner, $id, true);
        if (!$entry) {
            throw new RuntimeException('Conta nao encontrada.');
        }
        if ($requestKey !== '') {
            $stmt = $pdo->prepare('SELECT account_id, amount, paid_date, method FROM accounts_payments WHERE request_key = ?');
            $stmt->execute([$requestKey]);
            $previous = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($previous) {
                if ((int)$previous['account_id'] !== $id || contasCents($previous['amount']) !== $amount || $previous['paid_date'] !== $date || $previous['method'] !== $method) {
                    throw new InvalidArgumentException('Este envio ja foi usado em outra movimentacao.');
                }
                $pdo->commit();
                return;
            }
        }
        if ((int)$entry['canceled'] === 1 || $amount > contasRemaining($entry)) {
            throw new InvalidArgumentException('Conta cancelada ou valor maior que o restante em aberto.');
        }
        $pdo->prepare('INSERT INTO accounts_payments (account_id,amount,paid_date,method,notes,request_key) VALUES (?,?,?,?,?,?)')->execute([$id,contasDecimal($amount),$date,$method,$notes,$requestKey === '' ? null : $requestKey]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function contasAction(PDO $pdo, int $owner, int $id, string $action, int $paymentId = 0): void
{
    $pdo->beginTransaction();
    try {
        $entry = contasFind($pdo, $owner, $id, true);
        if (!$entry) {
            throw new RuntimeException('Conta nao encontrada.');
        }
        if ($action === 'remove_payment') {
            $stmt = $pdo->prepare('DELETE FROM accounts_payments WHERE id = ? AND account_id = ?');
            $stmt->execute([$paymentId, $id]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Movimentacao nao encontrada.');
            }
        } elseif (in_array($action, ['delete', 'cancel'], true)) {
            if (contasCents($entry['paid_amount']) > 0) {
                throw new InvalidArgumentException('Esta conta tem movimentacoes. Corrija o historico antes de excluir ou cancelar.');
            }
            if ($action === 'delete') {
                $pdo->prepare('DELETE FROM accounts_entries WHERE id = ? AND owner_user_id = ?')->execute([$id, $owner]);
            } else {
                $pdo->prepare('UPDATE accounts_entries SET canceled = ? WHERE id = ? AND owner_user_id = ?')->execute([(int)$entry['canceled'] === 1 ? 0 : 1, $id, $owner]);
            }
        } else {
            throw new InvalidArgumentException('Operacao invalida.');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function contasAnalytics(array $entries, array $payments, string $today): array
{
    $today = contasDate($today);
    $month = substr($today, 0, 7);
    $first = new DateTimeImmutable($month . '-01');
    $next7 = (new DateTimeImmutable($today))->modify('+7 days')->format('Y-m-d');
    $summary = array_fill_keys(['pending_payable','pending_receivable','overdue_payable','overdue_receivable','next7_payable','received_month','paid_month'], 0);
    $actual = $forecast = $categories = [];
    for ($i = -11; $i <= 0; $i++) {
        $actual[$first->modify(($i >= 0 ? '+' : '') . $i . ' months')->format('Y-m')] = ['payable' => 0, 'receivable' => 0];
    }
    for ($i = 0; $i < 6; $i++) {
        $forecast[$first->modify('+' . $i . ' months')->format('Y-m')] = ['payable' => 0, 'receivable' => 0];
    }
    $upcoming = [];
    foreach ($entries as $entry) {
        if ((int)$entry['canceled'] === 1 || contasRemaining($entry) <= 0) {
            continue;
        }
        $remaining = contasRemaining($entry);
        $kind = $entry['kind'];
        $summary['pending_' . $kind] += $remaining;
        if ($entry['due_date'] < $today) {
            $summary['overdue_' . $kind] += $remaining;
        } elseif ($kind === 'payable' && $entry['due_date'] <= $next7) {
            $summary['next7_payable'] += $remaining;
        }
        $key = substr($entry['due_date'], 0, 7);
        if (isset($forecast[$key])) {
            $forecast[$key][$kind] += $remaining;
        }
        $upcoming[] = $entry;
    }
    usort($upcoming, static fn($a, $b) => [$a['due_date'],$a['id']] <=> [$b['due_date'],$b['id']]);
    foreach ($payments as $payment) {
        if ($payment['paid_date'] > $today) {
            continue;
        }
        $key = substr($payment['paid_date'], 0, 7);
        $amount = contasCents($payment['amount']);
        $kind = $payment['kind'];
        if ($key === $month) {
            $summary[$kind === 'receivable' ? 'received_month' : 'paid_month'] += $amount;
        }
        if (isset($actual[$key])) {
            $actual[$key][$kind] += $amount;
            if ($kind === 'payable') {
                $category = $payment['category'] ?: 'Sem categoria';
                $categories[$category] = ($categories[$category] ?? 0) + $amount;
            }
        }
    }
    arsort($categories);
    return ['summary' => $summary, 'actual' => $actual, 'forecast' => $forecast, 'categories' => array_slice($categories,0,6,true), 'upcoming' => array_slice($upcoming,0,8)];
}
