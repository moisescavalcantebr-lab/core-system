<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require dirname(__DIR__, 3) . '/app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();
coreRequireLaboratory('/web/admin/bases/index.php', 'Instalacao de modulos em bases fica disponivel apenas no laboratorio.');

$baseId = (int)($_POST['base_id'] ?? 0);
$moduleSlug = safeModuleSlug((string)($_POST['module'] ?? ''));

function safeModuleSlug(string $slug): string
{
    return preg_replace('/[^a-z0-9\-_]/', '', strtolower($slug));
}

function copyRecursive(string $source, string $destination): void
{
    if (!is_dir($source)) {
        return;
    }

    if (!is_dir($destination)) {
        if (!@mkdir($destination, 0755, true) && !is_dir($destination)) {
            throw new RuntimeException('Nao foi possivel criar a pasta: ' . $destination);
        }
    }

    foreach (scandir($source) as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $sourceFile = $source . '/' . $file;
        $destinationFile = $destination . '/' . $file;

        if (is_dir($sourceFile)) {
            copyRecursive($sourceFile, $destinationFile);
            continue;
        }

        if (!is_file($destinationFile) || md5_file($sourceFile) !== md5_file($destinationFile)) {
            if (!@copy($sourceFile, $destinationFile)) {
                throw new RuntimeException('Nao foi possivel copiar o arquivo para: ' . $destinationFile);
            }
        }
    }
}

function copyModuleTargets(string $basePath, string $modulePath, array $manifest): void
{
    foreach (($manifest['copy'] ?? []) as $copy) {
        if (!is_array($copy)) {
            continue;
        }

        $from = trim((string)($copy['from'] ?? ''), '/');
        $to = trim((string)($copy['to'] ?? ''), '/');

        if ($from === '' || $to === '' || str_contains($from, '..') || str_contains($to, '..')) {
            continue;
        }

        $sourcePath = $modulePath . '/' . $from;
        $destinationPath = $basePath . '/' . $to;

        if (is_dir($sourcePath)) {
            copyRecursive($sourcePath, $destinationPath);
            continue;
        }

        if (!is_file($sourcePath)) {
            continue;
        }

        $destinationDir = dirname($destinationPath);
        if (!is_dir($destinationDir) && !@mkdir($destinationDir, 0755, true) && !is_dir($destinationDir)) {
            throw new RuntimeException('Nao foi possivel criar a pasta: ' . $destinationDir);
        }

        if (!@copy($sourcePath, $destinationPath)) {
            throw new RuntimeException('Nao foi possivel copiar o arquivo para: ' . $destinationPath);
        }
    }
}

function appendModuleSchemaOnce(string $basePath, string $moduleSlug, string $modulePath, array $manifest): void
{
    $schema = $manifest['database']['schema'] ?? '';

    if (!$schema) {
        return;
    }

    $schemaPath = $modulePath . '/' . ltrim((string)$schema, '/');
    $baseSchemaPath = $basePath . '/app/database/schema.sql';

    if (!is_file($schemaPath) || !is_file($baseSchemaPath)) {
        return;
    }

    if (!is_writable($baseSchemaPath)) {
        throw new RuntimeException('Schema da base sem permissao de escrita: ' . $baseSchemaPath);
    }

    $baseSchema = (string)file_get_contents($baseSchemaPath);
    $marker = '-- MODULE: ' . $moduleSlug;

    if (str_contains($baseSchema, $marker)) {
        return;
    }

    $moduleSchema = file_get_contents($schemaPath);

    if ($moduleSchema === false || trim($moduleSchema) === '') {
        return;
    }

    $written = file_put_contents(
        $baseSchemaPath,
        PHP_EOL . PHP_EOL . '-- =====================================================' .
        PHP_EOL . $marker .
        PHP_EOL . '-- =====================================================' .
        PHP_EOL . $moduleSchema,
        FILE_APPEND
    );

    if ($written === false) {
        throw new RuntimeException('Nao foi possivel atualizar o schema da base.');
    }
}

function participantSettingsFromBase(PDO $pdo, int $baseId): array
{
    $stmt = $pdo->prepare("
        SELECT settings_json
        FROM base_module_prices
        WHERE base_id = ?
          AND module_slug = 'participantes'
        LIMIT 1
    ");
    $stmt->execute([$baseId]);
    $settingsJson = (string)($stmt->fetchColumn() ?: '');
    $settings = $settingsJson !== '' ? json_decode($settingsJson, true) : [];

    if (!is_array($settings)) {
        $settings = [];
    }

    return [
        'participant_context' => (string)($settings['participant_context'] ?? 'custom'),
        'participant_label' => (string)($settings['participant_label'] ?? 'Participante'),
        'participant_label_plural' => (string)($settings['participant_label_plural'] ?? 'Participantes'),
    ];
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

    copyRecursive($basePath . '/web/admin/participantes', $basePath . '/web/admin/' . $routeSlug);
}

function financeSettingsFromBase(PDO $pdo, int $baseId): array
{
    $stmt = $pdo->prepare("
        SELECT settings_json
        FROM base_module_prices
        WHERE base_id = ?
          AND module_slug = 'financeiro'
        LIMIT 1
    ");
    $stmt->execute([$baseId]);
    $settingsJson = (string)($stmt->fetchColumn() ?: '');
    $settings = $settingsJson !== '' ? json_decode($settingsJson, true) : [];

    if (!is_array($settings)) {
        $settings = [];
    }

    $mode = (string)($settings['finance_mode'] ?? 'personal');

    if ($mode === 'participants') {
        $participantSettings = participantSettingsFromBase($pdo, $baseId);
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

    return [
        'finance_mode' => $mode,
    ];
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

    if (!is_writable($schemaPath)) {
        throw new RuntimeException('Schema sem permissao de escrita: ' . $schemaPath);
    }

    if (file_put_contents($schemaPath, $content) === false) {
        throw new RuntimeException('Nao foi possivel atualizar as configuracoes do schema.');
    }
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

function moduleInstallRemovePath(string $path, string $allowedRoot): void
{
    $allowedRoot = rtrim(str_replace('\\', '/', $allowedRoot), '/');
    $normalizedPath = str_replace('\\', '/', $path);

    if (!str_starts_with($normalizedPath, $allowedRoot . '/')) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        @chmod($path, 0644);
        @unlink($path);
        return;
    }

    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        moduleInstallRemovePath($path . '/' . $file, $allowedRoot);
    }

    @chmod($path, 0755);
    @rmdir($path);
}

try {
    if ($baseId <= 0 || $moduleSlug === '') {
        throw new RuntimeException('Dados invalidos.');
    }

    $stmt = $pdo->prepare('SELECT * FROM bases WHERE id = :id');
    $stmt->execute(['id' => $baseId]);
    $base = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$base || (string)$base['slug'] === 'base') {
        throw new RuntimeException('Base invalida.');
    }

    if ((int)($base['is_protected'] ?? 0) === 1) {
        throw new RuntimeException('Desbloqueie a base antes de instalar modulos.');
    }

    $basePath = BASES_PATH . '/' . $base['slug'];
    $modulePath = ROOT_PATH . '/modules/' . $moduleSlug;
    $manifestPath = $modulePath . '/module.json';
    $baseModulePath = $basePath . '/modules/' . $moduleSlug;

    if (!is_dir($basePath)) {
        throw new RuntimeException('Pasta da base nao encontrada.');
    }

    $baseModulesRoot = $basePath . '/modules';

    if (!is_dir($baseModulesRoot) && !@mkdir($baseModulesRoot, 0755, true) && !is_dir($baseModulesRoot)) {
        throw new RuntimeException('Nao foi possivel criar a pasta de modulos da base: ' . $baseModulesRoot);
    }

    if (!is_writable($basePath) || !is_writable($baseModulesRoot)) {
        throw new RuntimeException('A base nao tem permissao de escrita para instalar modulos: ' . $basePath);
    }

    if (!is_dir($modulePath) || !is_file($manifestPath)) {
        throw new RuntimeException('Modulo nao encontrado.');
    }

    $manifest = json_decode((string)file_get_contents($manifestPath), true);

    if (!is_array($manifest)) {
        throw new RuntimeException('Manifesto do modulo invalido.');
    }

    $requiredModules = array_values(array_filter(array_map('strval', $manifest['attach_to'] ?? [])));
    foreach ($requiredModules as $requiredModule) {
        $requiredModule = safeModuleSlug($requiredModule);
        if ($requiredModule === '') {
            continue;
        }

        if (!is_file($baseModulesRoot . '/' . $requiredModule . '/module.json')) {
            throw new RuntimeException('Instale o modulo "' . $requiredModule . '" antes de instalar este acoplavel.');
        }
    }

    if (is_file($baseModulePath . '/module.json')) {
        flash('warning', 'Modulo ja instalado nesta base.');
        redirect('/web/admin/bases/modules.php?id=' . $baseId . '&already=1');
    }

    if (is_dir($baseModulePath)) {
        moduleInstallRemovePath($baseModulePath, $basePath);
    }

    copyRecursive($modulePath, $baseModulePath);
    copyModuleTargets($basePath, $modulePath, $manifest);
    appendModuleSchemaOnce($basePath, $moduleSlug, $modulePath, $manifest);

    if ($moduleSlug === 'participantes') {
        $participantSettings = participantSettingsFromBase($pdo, $baseId);
        updateParticipantSchemaDefaults($basePath . '/app/database/schema.sql', $participantSettings);
        updateParticipantSchemaDefaults($basePath . '/modules/participantes/database/schema.sql', $participantSettings);
        ensureParticipantAdminAlias($basePath, $participantSettings);
    } elseif ($moduleSlug === 'financeiro') {
        $financeSettings = financeSettingsFromBase($pdo, $baseId);
        updateFinanceSchemaDefaults($basePath . '/app/database/schema.sql', $financeSettings);
        updateFinanceSchemaDefaults($basePath . '/modules/financeiro/database/schema.sql', $financeSettings);
    }

    updateBaseSchemaMetadata($basePath);

    flash('success', 'Modulo instalado na base.');
    redirect('/web/admin/bases/modules.php?id=' . $baseId . '&installed=1');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    redirect('/web/admin/bases/modules.php?id=' . $baseId);
}
