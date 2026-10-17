<?php
declare(strict_types=1);

function competitionEnsureTrackingSchema(PDO $pdo): void
{
    $columns = $pdo->query('SHOW COLUMNS FROM competitions')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('official_position', $columns, true)) {
        $pdo->exec('ALTER TABLE competitions ADD COLUMN official_position INT UNSIGNED NULL');
    }
}

function competitionTrainingAvailable(PDO $pdo): bool
{
    try {
        $helper = __DIR__.'/../meu_time/formations_helpers.php';
        if (!is_file($helper)) return false;
        require_once $helper;
        if (!teamSecondFormationAllowed()) return false;
        return (int)$pdo->query('SELECT formations_count FROM team_profile WHERE id=1')->fetchColumn() === 2
            && is_file(__DIR__.'/../meu_time/lineup_helpers.php');
    } catch (Throwable $e) {
        return false;
    }
}

function competitionOfficialPosition(mixed $value): ?int
{
    if ($value === null || $value === '') return null;
    if (!is_scalar($value) || !preg_match('/^[1-9][0-9]{0,3}$/', (string)$value)) {
        throw new InvalidArgumentException('Informe uma posicao de 1 a 9999 ou deixe vazio.');
    }
    return (int)$value;
}

if (!function_exists('competitionIsDefaultFriendly')) {
    function competitionIsDefaultFriendly(array $competition): bool
    {
        return strtolower(trim((string)($competition['name'] ?? ''))) === 'amistoso'
            && (string)($competition['type'] ?? '') === 'friendly';
    }
}
