<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

function teamEnsureBaseSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS team_profile (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL DEFAULT 'Meu Time',
            short_name VARCHAR(80) NULL,
            team_type ENUM('futsal','society','beach','field','other','custom') DEFAULT 'field',
            starters_count INT DEFAULT 11,
            reserves_count INT DEFAULT 7,
            custom_slots_json JSON NULL,
            city VARCHAR(100) NULL,
            venue VARCHAR(150) NULL,
            primary_color VARCHAR(20) NULL,
            secondary_color VARCHAR(20) NULL,
            responsible_name VARCHAR(150) NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("INSERT INTO team_profile (id, name) VALUES (1, 'Meu Time') ON DUPLICATE KEY UPDATE id = id");
}

function teamEnsureCustomSchema(PDO $pdo): void
{
    teamEnsureBaseSchema($pdo);

    $columns = $pdo->query("SHOW COLUMNS FROM team_profile")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('custom_slots_json', $columns, true)) {
        $pdo->exec("ALTER TABLE team_profile ADD COLUMN custom_slots_json JSON NULL AFTER reserves_count");
    }

    $pdo->exec("ALTER TABLE team_profile MODIFY COLUMN team_type ENUM('futsal','society','beach','field','other','custom') DEFAULT 'field'");
}

teamEnsureCustomSchema($pdo);

$currentTeam = $pdo->query("SELECT custom_slots_json FROM team_profile WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$currentCustomSlotsJson = $currentTeam['custom_slots_json'] ?? null;
$teamType = 'custom';
$setupByType = [
    'field' => ['starters' => 11, 'reserves' => 9],
    'society' => ['starters' => 7, 'reserves' => 7],
    'futsal' => ['starters' => 5, 'reserves' => 7],
    'beach' => ['starters' => 5, 'reserves' => 7],
    'custom' => ['starters' => 11, 'reserves' => 9],
];
$teamSetup = $setupByType[$teamType] ?? $setupByType['field'];
$startersCount = (int)($_POST['starters_count'] ?? $teamSetup['starters']);
$reservesCount = (int)($_POST['reserves_count'] ?? $teamSetup['reserves']);
$startersCount = max(1, min(11, $startersCount));
$friendlyCardsEnabled = ($_POST['friendly_cards_enabled'] ?? '1') === '0' ? '0' : '1';
$customSlotsInput = is_array($_POST['custom_slots'] ?? null) ? $_POST['custom_slots'] : [];
$customSlots = [];

foreach (['GO', 'ZC1', 'ZC2', 'LE', 'LD', 'VOL1', 'VOL2', 'MAT1', 'MAT2', 'PTE', 'PTD', 'CA1', 'CA2'] as $code) {
    if (count($customSlots) >= $startersCount) {
        break;
    }

    if (!empty($customSlotsInput[$code])) {
        $customSlots[$code] = 1;
    }
}

$currentCustomSlotsJson = $customSlots ? json_encode($customSlots, JSON_UNESCAPED_UNICODE) : $currentCustomSlotsJson;

$startersCount = max(1, min(11, $startersCount));
$reservesCount = max(0, min(9, $reservesCount));

$stmt = $pdo->prepare("
    UPDATE team_profile
    SET name = ?, short_name = ?, team_type = ?, starters_count = ?, reserves_count = ?, custom_slots_json = ?, city = ?, venue = ?, responsible_name = ?
    WHERE id = 1
");
$stmt->execute([
    trim((string)($_POST['name'] ?? 'Meu Time')) ?: 'Meu Time',
    trim((string)($_POST['short_name'] ?? '')) ?: null,
    $teamType,
    $startersCount,
    $reservesCount,
    $currentCustomSlotsJson,
    trim((string)($_POST['city'] ?? '')) ?: null,
    trim((string)($_POST['venue'] ?? '')) ?: null,
    trim((string)($_POST['responsible_name'] ?? '')) ?: null,
]);

if (function_exists('setSetting')) {
    setSetting('friendly_cards_enabled', $friendlyCardsEnabled, 'meu_time');
}

flash('success', 'Time atualizado.');
redirect(PROJECT_URL . '/admin/meu_time/index.php');
