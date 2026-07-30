<?php
declare(strict_types=1);

class ProjectInstaller
{
    public static function generateConfig(
        string $projectPath,
        array $project
    ): void {
        self::writeConfig($projectPath, $project);
    }

    public static function syncFromDatabase(
        PDO $pdo,
        int $projectId
    ): void {

        $stmt = $pdo->prepare("
            SELECT p.*,
                   pl.name AS plan_name,
                   COALESCE(pp.billing_cycle, pl.billing_cycle) AS billing_cycle
            FROM projects p
            LEFT JOIN plans pl ON pl.id = p.plan_id
            LEFT JOIN plan_prices pp ON pp.id = p.plan_price_id
            WHERE p.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) {
            throw new RuntimeException('Projeto não encontrado.');
        }

        if (empty($project['path'])) {
            throw new RuntimeException('Projeto sem path.');
        }

        $projectPath = ROOT_PATH . '/' . ltrim($project['path'], '/');

        self::writeConfig($projectPath, $project);
    }

    public static function syncInstalledModulesFromBase(PDO $pdo, int $projectId): array
    {
        $stmt = $pdo->prepare("
            SELECT p.*, b.slug AS base_slug
            FROM projects p
            INNER JOIN bases b ON b.id = p.base_id
            WHERE p.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project || empty($project['path']) || empty($project['base_slug'])) {
            return [];
        }

        $projectPath = ROOT_PATH . '/' . ltrim((string)$project['path'], '/');
        $baseModulesPath = BASES_PATH . '/' . $project['base_slug'] . '/modules';

        if (!is_dir($projectPath) || !is_dir($baseModulesPath)) {
            return [];
        }

        $projectPdo = self::projectPdo($projectPath);
        $synced = [];

        foreach (scandir($baseModulesPath) ?: [] as $moduleSlug) {
            if ($moduleSlug === '.' || $moduleSlug === '..') {
                continue;
            }

            $modulePath = $baseModulesPath . '/' . $moduleSlug;
            $manifestPath = $modulePath . '/module.json';

            if (!is_dir($modulePath) || !is_file($manifestPath)) {
                continue;
            }

            $manifest = json_decode((string)file_get_contents($manifestPath), true);

            if (!is_array($manifest)) {
                continue;
            }

            self::recursiveCopy($modulePath, $projectPath . '/modules/' . $moduleSlug);

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
                $destinationPath = $projectPath . '/' . $to;

                if (is_dir($sourcePath)) {
                    self::recursiveCopy($sourcePath, $destinationPath);
                } elseif (is_file($sourcePath)) {
                    self::copyFile($sourcePath, $destinationPath);
                }
            }

            $schema = $manifest['database']['schema'] ?? '';
            if ($projectPdo && $schema) {
                $schemaPath = $modulePath . '/' . ltrim((string)$schema, '/');
                if (is_file($schemaPath)) {
                    $projectPdo->exec((string)file_get_contents($schemaPath));
                }
            }

            $synced[] = (string)($manifest['label'] ?? $moduleSlug);
        }

        return $synced;
    }

    private static function recursiveCopy(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            return;
        }

        if (!is_dir($destination) && !mkdir($destination, 0755, true) && !is_dir($destination)) {
            throw new RuntimeException("Nao foi possivel criar pasta: {$destination}");
        }

        foreach (scandir($source) ?: [] as $file) {
            if ($file === '.' || $file === '..' || $file === '_notes') {
                continue;
            }

            $sourcePath = $source . '/' . $file;
            $destinationPath = $destination . '/' . $file;

            if (is_dir($sourcePath)) {
                self::recursiveCopy($sourcePath, $destinationPath);
            } elseif (is_file($sourcePath)) {
                self::copyFile($sourcePath, $destinationPath);
            }
        }
    }

    private static function copyFile(string $source, string $destination): void
    {
        $destinationDir = dirname($destination);

        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0755, true) && !is_dir($destinationDir)) {
            throw new RuntimeException("Nao foi possivel criar pasta: {$destinationDir}");
        }

        if (is_file($destination) && md5_file($source) === md5_file($destination)) {
            return;
        }

        if (is_file($destination) && !is_writable($destination)) {
            throw new RuntimeException("Arquivo de destino sem permissao de escrita: {$destination}");
        }

        if (!@copy($source, $destination)) {
            throw new RuntimeException("Nao foi possivel copiar arquivo: {$source} para {$destination}");
        }

        @chmod($destination, 0644);
    }

    private static function projectPdo(string $projectPath): ?PDO
    {
        $configPath = $projectPath . '/app/config/database.php';

        if (!is_file($configPath)) {
            return null;
        }

        $dbConfig = require $configPath;

        try {
            return new PDO(
                "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}",
                $dbConfig['user'],
                $dbConfig['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
                ]
            );
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function writeConfig(
        string $projectPath,
        array $project
    ): void {

        if (!is_dir($projectPath)) {
            throw new RuntimeException('Pasta do projeto inexistente.');
        }

        $config = [
            'id'             => (int)$project['id'],
            'name'           => $project['name'],
            'slug'           => $project['slug'],
            'owner_name'     => $project['owner_name'] ?? null,
            'owner_email'    => $project['owner_email'] ?? null,
            'status'         => $project['status'],
            'billing_status' => $project['billing_status'],
            'expires_at'     => $project['expires_at'],
            'plan_id'        => isset($project['plan_id']) ? (int)$project['plan_id'] : null,
            'plan_price_id'  => isset($project['plan_price_id']) ? (int)$project['plan_price_id'] : null,
            'plan_name'      => $project['plan_name'] ?? null,
            'billing_cycle'  => $project['billing_cycle'] ?? null,
            'core_api_key'   => self::coreApiKey(),
            'version'        => 1,
            'created_at'     => $project['created_at'] ?? date('c'),
            'synced_at'      => date('c'),
        ];

        file_put_contents(
            $projectPath . '/project.json',
            json_encode(
                $config,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            )
        );
    }

    private static function coreApiKey(): string
    {
        $envPath = ROOT_PATH . '/env/env.production.php';

        if (!is_file($envPath)) {
            return '';
        }

        $config = require $envPath;

        if (!is_array($config)) {
            return '';
        }

        return (string)($config['api_key'] ?? '');
    }
}
