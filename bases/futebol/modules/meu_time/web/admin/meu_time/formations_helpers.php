<?php
declare(strict_types=1);

function teamEnsureFormationsSchema(PDO $pdo): void
{
    $columns = $pdo->query('SHOW COLUMNS FROM team_profile')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('formations_count', $columns, true)) {
        $pdo->exec('ALTER TABLE team_profile ADD COLUMN formations_count TINYINT UNSIGNED NOT NULL DEFAULT 1');
    }
    $columns = $pdo->query('SHOW COLUMNS FROM team_roster')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('team_key', $columns, true)) {
        $pdo->exec("ALTER TABLE team_roster ADD COLUMN team_key ENUM('time_1','time_2') NOT NULL DEFAULT 'time_1'");
    }
}

function teamSetFormationsCount(PDO $pdo, int $count): void
{
    if (!in_array($count, [1, 2], true)) {
        throw new InvalidArgumentException('Quantidade de times invalida.');
    }
    $pdo->beginTransaction();
    try {
        $pdo->query('SELECT id FROM team_profile WHERE id = 1 FOR UPDATE')->fetchColumn();
        if ($count === 1) {
            $pdo->exec("UPDATE team_roster SET status = 'inactive', slot_group = NULL, slot_index = NULL WHERE team_key = 'time_2'");
        }
        $stmt = $pdo->prepare('UPDATE team_profile SET formations_count = ? WHERE id = 1');
        $stmt->execute([$count]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
