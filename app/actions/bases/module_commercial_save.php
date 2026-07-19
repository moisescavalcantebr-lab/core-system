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
$commercialCategory = (string)($_POST['commercial_category'] ?? 'extra');
$status = (int)($_POST['status'] ?? 1);
$displayOrder = (int)($_POST['display_order'] ?? 0);

function modulePriceDecimal(?string $value): ?float
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $value = str_replace(['R$', ' '], '', $value);
    $value = str_replace('.', '', $value);
    $value = str_replace(',', '.', $value);

    return is_numeric($value) ? round((float)$value, 2) : null;
}

function baseModuleEnsureSettingsColumn(PDO $pdo): void
{
    $columns = $pdo->query("SHOW COLUMNS FROM base_module_prices")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('settings_json', $columns, true)) {
        $pdo->exec("ALTER TABLE base_module_prices ADD COLUMN settings_json LONGTEXT NULL AFTER annual_price");
    }
}

function moduleParticipantSettingsFromPost(): array
{
    $contexts = [
        'jogador' => ['Jogador', 'Jogadores'],
    ];
    $contexts += participantKnownContexts();

    $context = (string)($_POST['participant_context'] ?? 'custom');

    if ($context === 'custom') {
        $label = trim((string)($_POST['participant_label'] ?? 'Participante')) ?: 'Participante';
        $labelPlural = trim((string)($_POST['participant_label_plural'] ?? 'Participantes')) ?: 'Participantes';
        $context = participantContextFromLabels($label);
    } elseif (isset($contexts[$context])) {
        [$label, $labelPlural] = $contexts[$context];
    } else {
        $label = trim((string)($_POST['participant_label'] ?? 'Participante')) ?: 'Participante';
        $labelPlural = trim((string)($_POST['participant_label_plural'] ?? 'Participantes')) ?: 'Participantes';
        $context = participantContextFromLabels($label);
    }

    return [
        'participant_context' => $context,
        'participant_label' => $label,
        'participant_label_plural' => $labelPlural,
    ];
}

function participantContextFromLabels(string $label): string
{
    $slug = participantSlugify($label);

    return $slug !== '' && !in_array($slug, ['participante', 'participantes'], true) ? $slug : 'custom';
}

function participantKnownContexts(): array
{
    global $pdo;

    if (!$pdo instanceof PDO) {
        return [];
    }

    $contexts = [];
    $stmt = $pdo->query("
        SELECT settings_json
        FROM base_module_prices
        WHERE module_slug = 'participantes'
          AND settings_json IS NOT NULL
          AND settings_json <> ''
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $settingsJson) {
        $settings = json_decode((string)$settingsJson, true);

        if (!is_array($settings)) {
            continue;
        }

        $context = participantSlugify((string)($settings['participant_context'] ?? ''));
        $label = trim((string)($settings['participant_label'] ?? ''));
        $labelPlural = trim((string)($settings['participant_label_plural'] ?? ''));

        if ($context === 'membros') {
            $context = 'membro';
        }

        if (
            $context === ''
            || in_array($context, ['custom', 'participante', 'participantes'], true)
            || $label === ''
            || $labelPlural === ''
        ) {
            continue;
        }

        $contexts[$context] = [$label, $labelPlural];
    }

    return $contexts;
}

function participantSlugify(string $value): string
{
    $value = trim($value);
    $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $value = $normalized !== false ? $normalized : $value;
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

    return trim($value, '-');
}

function copyRecursive(string $source, string $destination): void
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
            copyRecursive($sourceFile, $destinationFile);
            continue;
        }

        if (!@copy($sourceFile, $destinationFile)) {
            throw new RuntimeException('Nao foi possivel copiar o arquivo para: ' . $destinationFile);
        }
    }
}

function participantRouteSlugFromSettings(array $settings): string
{
    $slug = participantSlugify((string)($settings['participant_label_plural'] ?? ''));

    return $slug !== '' && !in_array($slug, ['participante', 'participantes'], true) ? $slug : 'participantes';
}

function ensureParticipantAdminAlias(string $basePath, array $settings): void
{
    $routeSlug = participantRouteSlugFromSettings($settings);

    if ($routeSlug === 'participantes') {
        return;
    }

    $source = $basePath . '/web/admin/participantes';
    $destination = $basePath . '/web/admin/' . $routeSlug;

    copyRecursive($source, $destination);
}

function moduleFinanceSettingsFromPost(): array
{
    $mode = (string)($_POST['finance_mode'] ?? 'personal');

    if ($mode === 'participants') {
        $participantSettings = moduleParticipantSettingsFromBase();
        $participantContext = participantSlugify((string)($participantSettings['participant_context'] ?? ''));
        $mode = $participantContext !== '' && !in_array($participantContext, ['custom', 'participante', 'participantes'], true)
            ? $participantContext
            : 'participants';
    }

    if ($mode !== 'personal') {
        $mode = participantSlugify($mode);
    }

    if ($mode === '' || in_array($mode, ['custom', 'participante', 'participantes'], true)) {
        $mode = 'participants';
    }

    if ($mode === 'personal') {
        $mode = 'personal';
    } elseif ($mode === '') {
        $mode = 'personal';
    }

    return [
        'finance_mode' => $mode,
    ];
}

function moduleParticipantSettingsFromBase(): array
{
    global $pdo, $baseId;

    if (!$pdo instanceof PDO || $baseId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT settings_json
        FROM base_module_prices
        WHERE base_id = ?
          AND module_slug = 'participantes'
        LIMIT 1
    ");
    $stmt->execute([$baseId]);
    $settings = json_decode((string)($stmt->fetchColumn() ?: ''), true);

    return is_array($settings) ? $settings : [];
}

function updateParticipantSchemaDefaults(string $schemaPath, array $settings): void
{
    if (!is_file($schemaPath)) {
        return;
    }

    $content = (string)file_get_contents($schemaPath);
    $replacements = [
        "/\\('participant_context',\\s*'[^']*',\\s*'participants'\\)/" => "('participant_context', '" . addslashes((string)$settings['participant_context']) . "', 'participants')",
        "/\\('participant_label',\\s*'[^']*',\\s*'participants'\\)/" => "('participant_label', '" . addslashes((string)$settings['participant_label']) . "', 'participants')",
        "/\\('participant_label_plural',\\s*'[^']*',\\s*'participants'\\)/" => "('participant_label_plural', '" . addslashes((string)$settings['participant_label_plural']) . "', 'participants')",
    ];

    foreach ($replacements as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content) ?? $content;
    }

    file_put_contents($schemaPath, $content);
}

function updateFinanceSchemaDefaults(string $schemaPath, array $settings): void
{
    if (!is_file($schemaPath)) {
        return;
    }

    $mode = (string)($settings['finance_mode'] ?? 'personal');

    if ($mode !== 'personal') {
        $mode = participantSlugify($mode);
    }

    if ($mode === '' || in_array($mode, ['custom', 'participante', 'participantes'], true)) {
        $mode = 'participants';
    }

    $content = (string)file_get_contents($schemaPath);
    $row = "('finance_mode', '" . addslashes($mode) . "')";

    if (preg_match("/\\('finance_mode',\\s*'[^']*'\\)/", $content)) {
        $content = preg_replace("/\\('finance_mode',\\s*'[^']*'\\)/", $row, $content) ?? $content;
    } elseif (preg_match("/\\('finance_user_label',\\s*'[^']*'\\)/", $content)) {
        $content = preg_replace("/\\('finance_user_label',\\s*'[^']*'\\)/", $row . "," . PHP_EOL . "('finance_user_label', 'Usuário')", $content, 1) ?? $content;
    }

    if (!is_writable($schemaPath)) {
        throw new RuntimeException('Schema sem permissao de escrita: ' . $schemaPath);
    }

    if (file_put_contents($schemaPath, $content) === false) {
        throw new RuntimeException('Nao foi possivel atualizar as configuracoes do schema.');
    }
}

function updateBaseSchemaMetadata(string $basePath): void
{
    $schemaPath = $basePath . '/app/database/schema.sql';
    $baseJsonPath = $basePath . '/base.json';

    if (!is_file($schemaPath) || !is_file($baseJsonPath)) {
        return;
    }

    $baseJson = json_decode((string)file_get_contents($baseJsonPath), true);

    if (!is_array($baseJson)) {
        return;
    }

    $baseJson['schema'] = is_array($baseJson['schema'] ?? null) ? $baseJson['schema'] : [];
    $baseJson['schema']['path'] = $baseJson['schema']['path'] ?? 'app/database/schema.sql';
    $baseJson['schema']['hash'] = md5_file($schemaPath) ?: ($baseJson['schema']['hash'] ?? '');
    $baseJson['schema']['updated_at'] = date('Y-m-d H:i:s');

    file_put_contents(
        $baseJsonPath,
        json_encode($baseJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
    );
}

try {
    baseModuleEnsureSettingsColumn($pdo);

    if ($baseId <= 0 || $moduleSlug === '') {
        throw new RuntimeException('Dados invalidos.');
    }

    if (!in_array($commercialCategory, ['included', 'extra', 'coming_soon'], true)) {
        $commercialCategory = 'extra';
    }

    $stmt = $pdo->prepare('SELECT * FROM bases WHERE id = ? LIMIT 1');
    $stmt->execute([$baseId]);
    $base = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$base || (string)$base['slug'] === 'base') {
        throw new RuntimeException('Base invalida.');
    }

    $manifestPath = ROOT_PATH . '/modules/' . $moduleSlug . '/module.json';
    if (!is_file($manifestPath)) {
        throw new RuntimeException('Modulo nao encontrado.');
    }

    $monthlyPrice = modulePriceDecimal($_POST['monthly_price'] ?? null);
    $annualPrice = modulePriceDecimal($_POST['annual_price'] ?? null);
    $settings = [];

    if ($moduleSlug === 'participantes') {
        $settings = moduleParticipantSettingsFromPost();
    } elseif ($moduleSlug === 'financeiro') {
        $settings = moduleFinanceSettingsFromPost();
    }

    $settingsJson = !empty($settings)
        ? json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : null;

    $stmt = $pdo->prepare("
        INSERT INTO base_module_prices
            (base_id, module_slug, commercial_category, monthly_price, annual_price, settings_json, status, display_order)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            commercial_category = VALUES(commercial_category),
            monthly_price = VALUES(monthly_price),
            annual_price = VALUES(annual_price),
            settings_json = VALUES(settings_json),
            status = VALUES(status),
            display_order = VALUES(display_order),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        $baseId,
        $moduleSlug,
        $commercialCategory,
        $monthlyPrice,
        $annualPrice,
        $settingsJson,
        $status === 1 ? 1 : 0,
        $displayOrder,
    ]);

    if ($moduleSlug === 'participantes') {
        $basePath = BASES_PATH . '/' . $base['slug'];
        updateParticipantSchemaDefaults($basePath . '/app/database/schema.sql', $settings);
        updateParticipantSchemaDefaults($basePath . '/modules/participantes/database/schema.sql', $settings);
        ensureParticipantAdminAlias($basePath, $settings);
        updateBaseSchemaMetadata($basePath);
    } elseif ($moduleSlug === 'financeiro') {
        $basePath = BASES_PATH . '/' . $base['slug'];
        updateFinanceSchemaDefaults($basePath . '/app/database/schema.sql', $settings);
        updateFinanceSchemaDefaults($basePath . '/modules/financeiro/database/schema.sql', $settings);
        updateBaseSchemaMetadata($basePath);
    }

    flash('success', 'Configuracao comercial do modulo salva.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

redirect('/web/admin/bases/modules.php?id=' . $baseId);
