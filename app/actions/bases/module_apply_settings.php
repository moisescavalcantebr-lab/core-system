<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require dirname(__DIR__, 3) . '/app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

$baseId = (int)($_POST['base_id'] ?? 0);
$moduleSlug = preg_replace('/[^a-z0-9\-_]/', '', strtolower((string)($_POST['module'] ?? '')));

function applySettingsProjectPdo(array $project): ?PDO
{
    $configPath = ROOT_PATH . '/' . ltrim((string)$project['path'], '/') . '/app/config/database.php';

    if (!is_file($configPath)) {
        return null;
    }

    $dbConfig = require $configPath;

    if (!is_array($dbConfig)) {
        return null;
    }

    return new PDO(
        'mysql:host=' . $dbConfig['host'] . ';dbname=' . $dbConfig['name'] . ';charset=' . ($dbConfig['charset'] ?? 'utf8mb4'),
        (string)$dbConfig['user'],
        (string)$dbConfig['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

function applyParticipantSettings(PDO $projectPdo, array $settings): void
{
    $projectPdo->exec("
        CREATE TABLE IF NOT EXISTS project_settings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key VARCHAR(120) NOT NULL,
            setting_value TEXT NULL,
            setting_group VARCHAR(80) DEFAULT 'general',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_project_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $projectPdo->prepare("
        INSERT INTO project_settings (setting_key, setting_value, setting_group)
        VALUES (?, ?, 'participants')
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            setting_group = VALUES(setting_group),
            updated_at = CURRENT_TIMESTAMP
    ");

    foreach (['participant_context', 'participant_label', 'participant_label_plural'] as $key) {
        $stmt->execute([$key, (string)($settings[$key] ?? '')]);
    }
}

function applySettingsSlugify(string $value): string
{
    $value = trim($value);
    $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $value = $normalized !== false ? $normalized : $value;
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

    return trim($value, '-');
}

function applySettingsParticipantRouteSlug(array $settings): string
{
    $slug = applySettingsSlugify((string)($settings['participant_label_plural'] ?? ''));

    return $slug !== '' && !in_array($slug, ['participante', 'participantes'], true) ? $slug : 'participantes';
}

function applySettingsCopyFolder(string $source, string $destination): void
{
    if (!is_dir($source)) {
        return;
    }

    if (!is_dir($destination) && !@mkdir($destination, 0755, true) && !is_dir($destination)) {
        throw new RuntimeException('Nao foi possivel criar a pasta: ' . $destination);
    }

    foreach (scandir($source) ?: [] as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $sourceFile = $source . '/' . $file;
        $destinationFile = $destination . '/' . $file;

        if (is_dir($sourceFile)) {
            applySettingsCopyFolder($sourceFile, $destinationFile);
            continue;
        }

        @copy($sourceFile, $destinationFile);
    }
}

function applyParticipantAdminAlias(array $project, array $settings): void
{
    $routeSlug = applySettingsParticipantRouteSlug($settings);

    if ($routeSlug === 'participantes') {
        return;
    }

    $projectPath = ROOT_PATH . '/' . ltrim((string)$project['path'], '/');
    applySettingsCopyFolder($projectPath . '/web/admin/participantes', $projectPath . '/web/admin/' . $routeSlug);
}

function applyFinanceSettings(PDO $projectPdo, array $settings): void
{
    $mode = (string)($settings['finance_mode'] ?? 'personal');

    if ($mode === 'participants') {
        $stmt = $projectPdo->prepare("SELECT setting_value FROM project_settings WHERE setting_key = 'participant_context' LIMIT 1");
        $stmt->execute();
        $participantContext = applySettingsSlugify((string)($stmt->fetchColumn() ?: ''));
        $mode = $participantContext !== '' && !in_array($participantContext, ['custom', 'participante', 'participantes'], true)
            ? $participantContext
            : 'participants';
    }

    if ($mode !== 'personal') {
        $mode = applySettingsSlugify($mode);
    }

    if ($mode === '' || in_array($mode, ['custom', 'participante', 'participantes'], true)) {
        $mode = 'participants';
    }

    $projectPdo->exec("
        CREATE TABLE IF NOT EXISTS finance_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $projectPdo->prepare("
        INSERT INTO finance_settings (setting_key, setting_value)
        VALUES ('finance_mode', ?)
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$mode]);
}

try {
    if ($baseId <= 0 || $moduleSlug === '') {
        throw new RuntimeException('Dados invalidos.');
    }

    if (!in_array($moduleSlug, ['participantes', 'financeiro'], true)) {
        throw new RuntimeException('Aplicacao de configuracao indisponivel para este modulo.');
    }

    $stmt = $pdo->prepare('SELECT * FROM bases WHERE id = ? LIMIT 1');
    $stmt->execute([$baseId]);
    $base = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$base || (string)$base['slug'] === 'base') {
        throw new RuntimeException('Base invalida.');
    }

    $stmt = $pdo->prepare("
        SELECT settings_json
        FROM base_module_prices
        WHERE base_id = ?
          AND module_slug = ?
        LIMIT 1
    ");
    $stmt->execute([$baseId, $moduleSlug]);
    $settings = json_decode((string)($stmt->fetchColumn() ?: ''), true);

    if (!is_array($settings) || empty($settings)) {
        throw new RuntimeException('Configure o modulo antes de aplicar aos projetos.');
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM projects
        WHERE base_id = ?
          AND status <> 'deleted'
        ORDER BY id ASC
    ");
    $stmt->execute([$baseId]);

    $updated = 0;
    $failed = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $project) {
        try {
            $projectPdo = applySettingsProjectPdo($project);

            if (!$projectPdo instanceof PDO) {
                $failed[] = (string)$project['slug'];
                continue;
            }

            if ($moduleSlug === 'participantes') {
                applyParticipantSettings($projectPdo, $settings);
                applyParticipantAdminAlias($project, $settings);
            } elseif ($moduleSlug === 'financeiro') {
                applyFinanceSettings($projectPdo, $settings);
            }

            $updated++;
        } catch (Throwable $e) {
            $failed[] = (string)$project['slug'];
        }
    }

    $message = 'Configuracao aplicada em ' . $updated . ' projeto(s).';

    if (!empty($failed)) {
        $message .= ' Falha em: ' . implode(', ', array_slice($failed, 0, 8)) . '.';
        flash('warning', $message);
    } else {
        flash('success', $message);
    }
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

redirect('/web/admin/bases/modules.php?id=' . $baseId);
