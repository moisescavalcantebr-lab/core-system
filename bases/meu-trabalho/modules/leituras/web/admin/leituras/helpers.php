<?php
declare(strict_types=1);

function eqEscape($value): string
{
    return htmlspecialchars(is_scalar($value) ? (string)$value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function eqToday(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
}

function eqOwner(): int
{
    requireProjectRole(['ADMIN']);
    $user = projectUser();
    if (!$user || ($user['status'] ?? '') !== 'active') {
        http_response_code(403);
        exit('Acesso negado.');
    }
    return (int)$user['id'];
}

function eqEnsureSchema(PDO $pdo): void
{
    $path = defined('PROJECT_PATH') ? PROJECT_PATH.'/modules/leituras/database/schema.sql' : '';
    if (!is_file($path)) $path = dirname(__DIR__, 3).'/database/schema.sql';
    if (!is_file($path)) throw new RuntimeException('Esquema Leituras nao encontrado.');
    $key = 'leituras-schema-'.substr(hash('sha256',(string)$pdo->query('SELECT DATABASE()')->fetchColumn()),0,32);
    $lock = $pdo->prepare('SELECT GET_LOCK(?,10)');
    $lock->execute([$key]);
    if ((int)$lock->fetchColumn() !== 1) throw new RuntimeException('Migracao de Leituras em uso. Tente novamente.');
    try {
        $renames = eqLegacyTablePlan($pdo);
        if ($renames) $pdo->exec('RENAME TABLE '.implode(', ',$renames));
        $pdo->exec((string)file_get_contents($path));
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$key]);
    }
}

function eqLegacyTablePlan(PDO $pdo): array
{
    $tables = $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
    $renames = [];
    foreach (['reasons','readings','workspaces','periods'] as $suffix) {
        $old = 'equatorial_'.$suffix;
        $new = 'leituras_'.$suffix;
        if (!in_array($old,$tables,true)) continue;
        if (in_array($new,$tables,true)) throw new RuntimeException('Tabelas antigas e novas coexistem. Migracao interrompida para proteger os dados.');
        $renames[] = '`'.$old.'` TO `'.$new.'`';
    }
    return $renames;
}

function eqText($value, int $limit, string $label, bool $required = false): string
{
    if (!is_string($value) && !is_int($value) && $value !== null) throw new InvalidArgumentException($label.' invalido.');
    $text = trim((string)$value);
    if (($required && $text === '') || mb_strlen($text) > $limit) throw new InvalidArgumentException('Confira '.$label.'.');
    return $text;
}

function eqInteger($value, string $label, bool $optional = false, int $max = 1000000): ?int
{
    $text = eqText($value, 20, $label);
    if ($optional && $text === '') return null;
    if (!preg_match('/^\d+$/D', $text) || strlen($text) > 10 || (int)$text > $max) throw new InvalidArgumentException($label.' deve ser um inteiro entre 0 e '.$max.'.');
    return (int)$text;
}

function eqId($value): int
{
    $id = eqInteger($value, 'Identificador', false, 2147483647);
    if ($id < 1) throw new InvalidArgumentException('Identificador invalido.');
    return $id;
}

function eqDate($value): string
{
    $text = eqText($value, 10, 'Data', true);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
    if (!$date || $date->format('Y-m-d') !== $text || $text < '1900-01-01' || $text > '2200-12-31') throw new InvalidArgumentException('Data invalida.');
    return $text;
}

function eqMonth($value): string
{
    $text = eqText($value, 7, 'Mes', true);
    eqDate($text.'-01');
    return $text;
}

function eqNormalizeReason(array $input): array
{
    $number = eqText($input['number'] ?? '', 20, 'Razao', true);
    if (!preg_match('/^\d{1,20}$/D', $number)) throw new InvalidArgumentException('A razao deve conter apenas numeros.');
    $number = ltrim($number, '0');
    return ['number' => $number === '' ? '0' : $number, 'location' => eqText($input['location'] ?? '', 120, 'Local', true)];
}

function eqWorkspace(PDO $pdo, int $owner, bool $lock = false): string
{
    $stmt = $pdo->prepare('SELECT active_month FROM leituras_workspaces WHERE owner_user_id=?'.($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$owner]);
    if ($stmt->fetchColumn() === false) {
        $latest = $pdo->prepare('SELECT MAX(work_date) FROM leituras_readings WHERE owner_user_id=?');
        $latest->execute([$owner]);
        $month = substr($latest->fetchColumn() ?: eqToday(),0,7);
        $insert = $pdo->prepare('INSERT INTO leituras_workspaces (owner_user_id,active_month) VALUES (?,?) ON DUPLICATE KEY UPDATE owner_user_id=owner_user_id');
        $insert->execute([$owner,$month]);
    }
    $stmt = $pdo->prepare('SELECT active_month FROM leituras_workspaces WHERE owner_user_id=?'.($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$owner]);
    return eqMonth($stmt->fetchColumn());
}

function eqPeriod(PDO $pdo, int $owner, string $month): array
{
    $stmt = $pdo->prepare('SELECT * FROM leituras_periods WHERE owner_user_id=? AND work_month=?');
    $stmt->execute([$owner,eqMonth($month)]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['owner_user_id'=>$owner,'work_month'=>$month,'archived_at'=>null,'revision'=>0];
}

function eqAssertOpen(PDO $pdo, int $owner, string $month): void
{
    if (eqPeriod($pdo,$owner,$month)['archived_at'] !== null) throw new InvalidArgumentException('Mes arquivado: reabra-o antes de alterar as leituras.');
}

function eqTouchPeriod(PDO $pdo, int $owner, string $month): void
{
    $stmt = $pdo->prepare('INSERT INTO leituras_periods (owner_user_id,work_month,revision) VALUES (?,?,1) ON DUPLICATE KEY UPDATE revision=revision+1');
    $stmt->execute([$owner,$month]);
}

function eqPeriodSummary(PDO $pdo, int $owner, string $month): array
{
    $anchor = new DateTimeImmutable(eqMonth($month).'-01');
    $stmt = $pdo->prepare('SELECT COUNT(*) AS days, COALESCE(SUM(total),0) AS total, COALESCE(SUM(executed IS NULL),0) AS missing_executed, COALESCE(SUM(sent IS NULL),0) AS missing_sent FROM leituras_readings WHERE owner_user_id=? AND work_date>=? AND work_date<=?');
    $stmt->execute([$owner,$anchor->format('Y-m-d'),$anchor->format('Y-m-t')]);
    return array_map('intval',$stmt->fetch(PDO::FETCH_ASSOC));
}

function eqArchives(PDO $pdo, int $owner): array
{
    $stmt = $pdo->prepare('SELECT * FROM leituras_periods WHERE owner_user_id=? AND archived_at IS NOT NULL ORDER BY work_month DESC');
    $stmt->execute([$owner]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function eqSetPeriod(PDO $pdo, int $owner, string $month, string $operation, int $revision): string
{
    $month = eqMonth($month);
    if (!in_array($operation,['archive_month','reopen_month'],true)) throw new InvalidArgumentException('Operacao invalida.');
    $pdo->beginTransaction();
    try {
        // All reading mutations lock this same owner row before touching monthly data.
        $active = eqWorkspace($pdo,$owner,true);
        $period = eqPeriod($pdo,$owner,$month);
        eqRevision($period,$revision);
        if ($operation === 'archive_month') {
            if ($period['archived_at'] !== null) throw new InvalidArgumentException('Mes ja arquivado.');
            if (eqPeriodSummary($pdo,$owner,$month)['days'] === 0) throw new InvalidArgumentException('Nao ha leituras para arquivar neste mes.');
            if ($active === $month) {
                $next = new DateTimeImmutable($month.'-01');
                do {
                    $next = $next->modify('+1 month');
                    $active = eqMonth($next->format('Y-m'));
                } while (eqPeriod($pdo,$owner,$active)['archived_at'] !== null);
            }
        } else {
            if ($period['archived_at'] === null) throw new InvalidArgumentException('Mes nao esta arquivado.');
            $active = $month;
        }
        $stamp = $operation === 'archive_month' ? (new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s') : null;
        $stmt = $pdo->prepare('INSERT INTO leituras_periods (owner_user_id,work_month,archived_at,revision) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE archived_at=VALUES(archived_at), revision=revision+1');
        $stmt->execute([$owner,$month,$stamp]);
        $stmt = $pdo->prepare('UPDATE leituras_workspaces SET active_month=? WHERE owner_user_id=?');
        $stmt->execute([$active,$owner]);
        $pdo->commit();
        return $active;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function eqNormalizeReading(array $input): array
{
    $data = ['reason_id' => eqId($input['reason_id'] ?? ''), 'work_date' => eqDate($input['work_date'] ?? ''), 'notes' => eqText($input['notes'] ?? '', 1000, 'Observacoes')];
    foreach (['total', 'executed', 'sent', 'lis', 'leit'] as $key) $data[$key] = eqInteger($input[$key] ?? '', $key, $key !== 'total');
    if ($data['executed'] !== null && $data['executed'] > $data['total']) throw new InvalidArgumentException('Executadas nao podem superar o total.');
    if ($data['sent'] !== null && ($data['executed'] === null || $data['sent'] > $data['executed'])) throw new InvalidArgumentException('Informe executadas antes de enviadas; enviadas nao podem superar executadas.');
    return $data;
}

function eqFind(PDO $pdo, int $owner, int $id, bool $reason = false, bool $lock = false): ?array
{
    $table = $reason ? 'leituras_reasons' : 'leituras_readings';
    $stmt = $pdo->prepare('SELECT * FROM '.$table.' WHERE owner_user_id=? AND id=?'.($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$owner, $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function eqReasons(PDO $pdo, int $owner): array
{
    $stmt = $pdo->prepare('SELECT r.*, (SELECT COUNT(*) FROM leituras_readings d WHERE d.reason_id=r.id AND d.owner_user_id=r.owner_user_id) AS used_count FROM leituras_reasons r WHERE r.owner_user_id=? ORDER BY active DESC, CHAR_LENGTH(number), number');
    $stmt->execute([$owner]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function eqReadings(PDO $pdo, int $owner, string $start, string $end, int $reason = 0): array
{
    $stmt = $pdo->prepare('SELECT * FROM leituras_readings WHERE owner_user_id=? AND work_date>=? AND work_date<=?'.($reason ? ' AND reason_id=?' : '').' ORDER BY work_date DESC');
    $args = [$owner, eqDate($start), eqDate($end)];
    if ($reason) $args[] = $reason;
    $stmt->execute($args);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function eqRevision(array $row, int $revision): void
{
    if ((int)$row['revision'] !== $revision) throw new InvalidArgumentException('Este registro mudou em outra tela. Reabra para editar os dados atuais.');
}

function eqWriteError(Throwable $error): Throwable
{
    if ($error instanceof PDOException && ($error->errorInfo[1] ?? 0) === 1062) return new InvalidArgumentException('Razao ou dia ja cadastrado. Edite o registro existente.');
    return $error;
}

function eqSaveReason(PDO $pdo, int $owner, int $id, array $data, int $revision = 0): int
{
    $data = eqNormalizeReason($data);
    $pdo->beginTransaction();
    try {
        if ($id) {
            $row = eqFind($pdo, $owner, $id, true, true);
            if (!$row) throw new RuntimeException('Razao nao encontrada.');
            eqRevision($row, $revision);
            $stmt = $pdo->prepare('UPDATE leituras_reasons SET number=?, location=?, revision=revision+1 WHERE id=? AND owner_user_id=?');
            $stmt->execute([$data['number'], $data['location'], $id, $owner]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO leituras_reasons (owner_user_id, number, location) VALUES (?,?,?)');
            $stmt->execute([$owner, $data['number'], $data['location']]);
            $id = (int)$pdo->lastInsertId();
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw eqWriteError($error);
    }
}

function eqSaveReading(PDO $pdo, int $owner, int $id, array $data, int $revision = 0): int
{
    $data = eqNormalizeReading($data);
    $pdo->beginTransaction();
    try {
        eqWorkspace($pdo,$owner,true);
        $row = $id ? eqFind($pdo, $owner, $id, false, true) : null;
        if ($id && !$row) throw new RuntimeException('Leitura nao encontrada.');
        if ($row) eqRevision($row, $revision);
        $months = array_unique([substr($data['work_date'],0,7),substr($row['work_date'] ?? $data['work_date'],0,7)]);
        foreach ($months as $month) eqAssertOpen($pdo,$owner,$month);
        $reason = eqFind($pdo, $owner, $data['reason_id'], true, true);
        if (!$reason) throw new InvalidArgumentException('Razao nao encontrada.');
        $sameReason = $row && (int)$row['reason_id'] === $data['reason_id'];
        if (!(int)$reason['active'] && !$sameReason) throw new InvalidArgumentException('Selecione uma razao ativa.');
        // Preserve the historical location when editing results after a reason is renamed.
        $number = $sameReason ? $row['reason_number'] : $reason['number'];
        $location = $sameReason ? $row['location'] : $reason['location'];
        $args = [$data['reason_id'], $number, $location, $data['work_date'], $data['total'], $data['executed'], $data['sent'], $data['lis'], $data['leit'], $data['notes']];
        if ($id) {
            $stmt = $pdo->prepare('UPDATE leituras_readings SET reason_id=?, reason_number=?, location=?, work_date=?, total=?, executed=?, sent=?, lis=?, leit=?, notes=?, revision=revision+1 WHERE id=? AND owner_user_id=?');
            $stmt->execute([...$args, $id, $owner]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO leituras_readings (reason_id, reason_number, location, work_date, total, executed, sent, lis, leit, notes, owner_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([...$args, $owner]);
            $id = (int)$pdo->lastInsertId();
        }
        foreach ($months as $month) eqTouchPeriod($pdo,$owner,$month);
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw eqWriteError($error);
    }
}

function eqAction(PDO $pdo, int $owner, int $id, string $operation, int $revision): void
{
    if (!in_array($operation, ['delete_reading', 'delete_reason', 'toggle_reason'], true)) throw new InvalidArgumentException('Operacao invalida.');
    $reasonAction = $operation !== 'delete_reading';
    $pdo->beginTransaction();
    try {
        if (!$reasonAction) eqWorkspace($pdo,$owner,true);
        $row = eqFind($pdo, $owner, $id, $reasonAction, true);
        if (!$row) throw new RuntimeException('Registro nao encontrado.');
        eqRevision($row, $revision);
        if (!$reasonAction) eqAssertOpen($pdo,$owner,substr($row['work_date'],0,7));
        if ($operation === 'toggle_reason') {
            $stmt = $pdo->prepare('UPDATE leituras_reasons SET active=1-active, revision=revision+1 WHERE id=? AND owner_user_id=?');
        } else {
            if ($reasonAction) {
                $used = $pdo->prepare('SELECT COUNT(*) FROM leituras_readings WHERE reason_id=? AND owner_user_id=?');
                $used->execute([$id, $owner]);
                if ($used->fetchColumn()) throw new InvalidArgumentException('Razao com historico nao pode ser excluida. Desative-a.');
            }
            $stmt = $pdo->prepare('DELETE FROM '.($reasonAction ? 'leituras_reasons' : 'leituras_readings').' WHERE id=? AND owner_user_id=?');
        }
        $stmt->execute([$id, $owner]);
        if (!$reasonAction) eqTouchPeriod($pdo,$owner,substr($row['work_date'],0,7));
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function eqNumber($value): string
{
    return $value === null ? '-' : number_format((int)$value, 0, ',', '.');
}

function eqStatus(array $row): string
{
    if ($row['executed'] === null) return 'A executar';
    if ((int)$row['executed'] < (int)$row['total']) return 'Em execucao';
    if ($row['sent'] === null || (int)$row['sent'] < (int)$row['executed']) return 'A enviar';
    return 'Concluida';
}

function eqAnalytics(array $records, string $month): array
{
    $anchor = new DateTimeImmutable(eqMonth($month).'-01');
    $months = [];
    for ($i = 11; $i >= 0; $i--) {
        $key = $anchor->modify('-'.$i.' months')->format('Y-m');
        $months[$key] = ['days' => 0, 'total' => 0, 'executed' => 0, 'sent' => 0, 'pending' => 0, 'unsent' => 0, 'lis' => 0, 'leit' => 0, 'executed_days' => 0, 'sent_days' => 0, 'lis_days' => 0, 'leit_days' => 0];
    }
    $daily = [];
    $previousTotals = [];
    $previousMonth = $anchor->modify('-1 month')->format('Y-m');
    foreach ($records as $row) {
        $key = substr($row['work_date'], 0, 7);
        if ($key === $previousMonth) {
            $reason = (int)$row['reason_id'];
            $previousTotals[$reason] = ($previousTotals[$reason] ?? 0) + (int)$row['total'];
        }
        if (!isset($months[$key])) continue;
        $bucket = &$months[$key];
        $bucket['days']++;
        $bucket['total'] += (int)$row['total'];
        foreach (['executed', 'sent', 'lis', 'leit'] as $field) if ($row[$field] !== null) {
            $bucket[$field] += (int)$row[$field];
            $bucket[$field.'_days']++;
        }
        if ($row['executed'] !== null) {
            $bucket['pending'] += (int)$row['total'] - (int)$row['executed'];
            if ($row['sent'] !== null) $bucket['unsent'] += (int)$row['executed'] - (int)$row['sent'];
        }
        unset($bucket);
        if ($key === $month) $daily[] = $row;
    }
    usort($daily, static fn($a, $b) => strcmp($a['work_date'], $b['work_date']));
    $current = $months[$month];
    $previous = $months[$anchor->modify('-1 month')->format('Y-m')];
    $deltas = [];
    foreach (['lis', 'leit'] as $field) {
        $complete = $current['days'] > 0 && $previous['days'] > 0 && $current[$field.'_days'] === $current['days'] && $previous[$field.'_days'] === $previous['days'];
        $delta = $complete ? $current[$field] - $previous[$field] : null;
        $deltas[$field] = ['amount' => $delta, 'percent' => $complete && $previous[$field] > 0 ? $delta / $previous[$field] * 100 : null];
    }
    return ['months' => $months, 'current' => $current, 'previous' => $previous, 'daily' => $daily, 'deltas' => $deltas, 'previous_totals'=>$previousTotals, 'month'=>$month];
}
