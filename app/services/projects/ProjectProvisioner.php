<?php
declare(strict_types=1);

require_once __DIR__ . '/ProjectInstaller.php';
require_once __DIR__ . '/MailService.php';

class ProjectProvisioner
{
    public static function createFreeProject(PDO $pdo, array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        $slug = self::normalizeSlug((string)($data['slug'] ?? ''));
        $ownerName = trim((string)($data['owner_name'] ?? ''));
        $ownerEmail = trim((string)($data['owner_email'] ?? ''));
        $baseId = (int)($data['base_id'] ?? 0);
        $leadId = (int)($data['lead_id'] ?? 0);
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        if ($name === '' || $slug === '' || $ownerName === '' || $ownerEmail === '' || $baseId <= 0) {
            throw new RuntimeException('Campos obrigatórios não preenchidos.');
        }

        if (!preg_match('/^[a-z0-9-]{3,60}$/', $slug)) {
            throw new RuntimeException('Slug inválido.');
        }

        if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email inválido.');
        }

        $stmt = $pdo->prepare("SELECT id FROM projects WHERE slug = :slug LIMIT 1");
        $stmt->execute(['slug' => $slug]);

        if ($stmt->fetch()) {
            throw new RuntimeException('Slug já existe.');
        }

        $stmt = $pdo->prepare("SELECT * FROM bases WHERE id = :id AND status = 1 LIMIT 1");
        $stmt->execute(['id' => $baseId]);
        $base = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$base) {
            throw new RuntimeException('Base inválida.');
        }

        $plan = self::freePlanForBase($pdo, $baseId);

        if (!$plan) {
            throw new RuntimeException('Plano FREE não configurado.');
        }

        $planId = (int)$plan['id'];
        $planPriceId = !empty($plan['plan_price_id']) ? (int)$plan['plan_price_id'] : null;
        $expiresAt = null;
        $billingStatus = 'active';

        if (($plan['billing_cycle'] ?? '') === 'monthly') {
            $expiresAt = date('Y-m-d', strtotime('+30 days'));
        }

        if (($plan['billing_cycle'] ?? '') === 'annual') {
            $expiresAt = date('Y-m-d', strtotime('+365 days'));
        }

        $relativePath = '/projects/' . $slug;
        $physicalPath = PROJECTS_PATH . '/' . $slug;
        $sourcePath = BASES_PATH . '/' . $base['slug'];

        if (is_dir($physicalPath)) {
            throw new RuntimeException('A pasta do projeto já existe.');
        }

        $coreConfig = require ROOT_PATH . '/env/env.production.php';
        $projectDbConfig = $coreConfig['project_db'];

        $dbHost = $projectDbConfig['host'];
        $dbUser = $projectDbConfig['user'];
        $dbPass = $projectDbConfig['pass'];
        $dbCharset = $projectDbConfig['charset'] ?? 'utf8mb4';
        $dbName = self::projectDatabaseName($slug);

        $serverPdo = null;
        $databaseCreated = false;
        $installStep = 'conectando ao servidor MySQL';

        try {
            $serverPdo = new PDO(
                "mysql:host={$dbHost};charset={$dbCharset}",
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $installStep = 'verificando se o banco do projeto ja existe';
            $stmt = $serverPdo->prepare("
                SELECT SCHEMA_NAME
                FROM INFORMATION_SCHEMA.SCHEMATA
                WHERE SCHEMA_NAME = :name
                LIMIT 1
            ");
            $stmt->execute(['name' => $dbName]);

            if ($stmt->fetchColumn()) {
                throw new RuntimeException("Banco do projeto já existe: {$dbName}");
            }

            self::recursiveCopy($sourcePath, $physicalPath);

            $installStep = "criando o banco {$dbName}";
            $serverPdo->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $databaseCreated = true;

            $installStep = "conectando ao banco {$dbName}";
            $projectPdo = new PDO(
                "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}",
                $dbUser,
                $dbPass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                ]
            );

            $schemaPath = $sourcePath . '/app/database/schema.sql';

            $installStep = 'localizando o schema da base';
            if (!file_exists($schemaPath)) {
                throw new RuntimeException('Schema da base não encontrado.');
            }

            $installStep = "instalando o schema no banco {$dbName}";
            $projectPdo->exec((string)file_get_contents($schemaPath));

            $installStep = 'criando arquivo de configuracao do projeto';
            self::writeDatabaseConfig($physicalPath, [
                'host' => $dbHost,
                'name' => $dbName,
                'user' => $dbUser,
                'pass' => $dbPass,
                'charset' => $dbCharset,
            ]);

            self::ensureStorageDirs($physicalPath);

            $installStep = 'registrando projeto no core';
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO projects
                (name, slug, owner_name, owner_email, base_id, plan_id, plan_price_id, path, status, billing_status, expires_at)
                VALUES
                (:name, :slug, :owner, :email, :base, :plan, :plan_price, :path, 'active', :billing, :expires)
            ");

            $stmt->execute([
                'name' => $name,
                'slug' => $slug,
                'owner' => $ownerName,
                'email' => $ownerEmail,
                'base' => $baseId,
                'plan' => $planId,
                'plan_price' => $planPriceId,
                'path' => $relativePath,
                'billing' => $billingStatus,
                'expires' => $expiresAt,
            ]);

            $projectId = (int)$pdo->lastInsertId();

            ProjectInstaller::generateConfig(
                $physicalPath,
                [
                    'id' => $projectId,
                    'name' => $name,
                    'slug' => $slug,
                    'owner_name' => $ownerName,
                    'owner_email' => $ownerEmail,
                    'status' => 'active',
                    'billing_status' => $billingStatus,
                    'expires_at' => $expiresAt,
                    'plan_id' => $planId,
                    'plan_price_id' => $planPriceId,
                    'plan_name' => $plan['name'] ?? null,
                    'billing_cycle' => $plan['billing_cycle'] ?? null,
                ]
            );

            $logMessage = "Projeto criado e instalado com banco '{$dbName}'";
            if (!empty($meta['ip_address'])) {
                $logMessage .= " | IP: " . $meta['ip_address'];
            }

            $pdo->prepare("
                INSERT INTO project_logs
                (project_id, action, message, level)
                VALUES
                (:project_id, 'created_installed', :message, 'info')
            ")->execute([
                'project_id' => $projectId,
                'message' => $logMessage,
            ]);

            if ($leadId > 0) {
                $pdo->prepare("
                    UPDATE leads
                    SET implementation_status = 'converted', status = 'converted'
                    WHERE id = :id
                ")->execute(['id' => $leadId]);
            }

            $pdo->commit();

            return [
                'project_id' => $projectId,
                'slug' => $slug,
                'path' => $relativePath,
                'owner_email' => $ownerEmail,
                'db_name' => $dbName,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            self::deleteRecursive($physicalPath);

            if ($databaseCreated && $serverPdo instanceof PDO) {
                try {
                    $serverPdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");
                } catch (Throwable $cleanupError) {
                    error_log('Erro ao limpar banco apos falha: ' . $cleanupError->getMessage());
                }
            }

            if ($leadId > 0) {
                $pdo->prepare("
                    UPDATE leads
                    SET implementation_status = 'error'
                    WHERE id = :id
                ")->execute(['id' => $leadId]);
            }

            throw new RuntimeException('Erro ao criar projeto na etapa "' . $installStep . '": ' . $e->getMessage(), 0, $e);
        }
    }

    public static function sendAccessEmail(PDO $pdo, int $projectId): ?string
    {
        $stmt = $pdo->prepare("
            SELECT id, name, path, owner_email
            FROM projects
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) {
            throw new RuntimeException('Projeto não encontrado.');
        }

        $email = (string)$project['owner_email'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email do cliente inválido.');
        }

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $pdo->prepare("
            UPDATE project_access_tokens
            SET used = 1
            WHERE project_id = :id
        ")->execute(['id' => $projectId]);

        $pdo->prepare("
            INSERT INTO project_access_tokens
            (project_id, email, token, expires_at)
            VALUES
            (:project, :email, :token, :expires)
        ")->execute([
            'project' => $projectId,
            'email' => $email,
            'token' => $token,
            'expires' => $expires,
        ]);

        $config = require ROOT_PATH . '/env/env.production.php';
        $baseUrl = rtrim((string)$config['app_url'], '/');
        $link = $baseUrl . '/' . ltrim((string)$project['path'], '/') . "/web/create-password.php?token=" . $token;

        $mailId = MailService::send(
            $email,
            'Acesso ao seu projeto',
            "
<h2>Bem-vindo ao seu projeto</h2>

<p>Clique no botão abaixo para criar sua senha:</p>

<p>
<a href='{$link}'
style='
display:inline-block;
padding:12px 20px;
background:#2563eb;
color:#fff;
text-decoration:none;
border-radius:6px;
'>
Criar senha
</a>
</p>

<p>Ou copie o link abaixo:</p>

<p>{$link}</p>

<p>Este link expira em 24 horas.</p>
"
        );

        $pdo->prepare("
            INSERT INTO project_logs
            (project_id, action, message, level)
            VALUES
            (:id, 'access_sent', :msg, :level)
        ")->execute([
            'id' => $projectId,
            'msg' => $mailId ? "Email de acesso enviado para {$email}" : "Falha ao enviar email de acesso para {$email}",
            'level' => $mailId ? 'info' : 'warning',
        ]);

        return $mailId;
    }

    public static function normalizeSlug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        return trim((string)$slug, '-');
    }

    public static function projectDatabaseName(string $slug): string
    {
        $normalized = str_replace('-', '_', $slug);
        $normalized = preg_replace('/[^a-z0-9_]+/', '_', $normalized);
        $normalized = trim((string)$normalized, '_');

        return 'project_' . substr($normalized, 0, 48);
    }

    private static function freePlanForBase(PDO $pdo, int $baseId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT p.*, pp.id AS plan_price_id
            FROM plans p
            INNER JOIN plan_prices pp ON pp.plan_id = p.id AND pp.billing_cycle = 'free' AND pp.status = 1
            INNER JOIN base_plan_prices bpp ON bpp.plan_price_id = pp.id AND bpp.status = 1
            WHERE p.billing_cycle = 'free'
              AND p.status = 1
              AND bpp.base_id = ?
            LIMIT 1
        ");
        $stmt->execute([$baseId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($plan) {
            return $plan;
        }

        $stmt = $pdo->query("
            SELECT p.*, pp.id AS plan_price_id
            FROM plans p
            LEFT JOIN plan_prices pp ON pp.plan_id = p.id AND pp.billing_cycle = 'free'
            WHERE p.billing_cycle = 'free'
              AND p.status = 1
            LIMIT 1
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private static function recursiveCopy(string $src, string $dst): void
    {
        if (!is_dir($src)) {
            throw new RuntimeException("Base não encontrada: {$src}");
        }

        if (!is_dir($dst) && !mkdir($dst, 0755, true) && !is_dir($dst)) {
            throw new RuntimeException("Nao foi possivel criar pasta: {$dst}");
        }

        $files = scandir($src);

        if ($files === false) {
            throw new RuntimeException("Erro ao ler diretório: {$src}");
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === '_notes') {
                continue;
            }

            $source = $src . '/' . $file;
            $dest = $dst . '/' . $file;

            if (is_dir($source)) {
                self::recursiveCopy($source, $dest);
            } elseif (is_file($source) && !copy($source, $dest)) {
                throw new RuntimeException("Nao foi possivel copiar arquivo: {$source}");
            }
        }
    }

    private static function deleteRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                self::deleteRecursive($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    private static function writeDatabaseConfig(string $physicalPath, array $db): void
    {
        $configPath = $physicalPath . '/app/config/database.php';

        if (!is_dir(dirname($configPath)) && !mkdir(dirname($configPath), 0755, true) && !is_dir(dirname($configPath))) {
            throw new RuntimeException('Nao foi possivel criar pasta de configuracao.');
        }

        $configContent = "<?php\nreturn [\n" .
            "    'host' => '" . addslashes((string)$db['host']) . "',\n" .
            "    'name' => '" . addslashes((string)$db['name']) . "',\n" .
            "    'user' => '" . addslashes((string)$db['user']) . "',\n" .
            "    'pass' => '" . addslashes((string)$db['pass']) . "',\n" .
            "    'charset' => '" . addslashes((string)$db['charset']) . "'\n" .
            "];\n";

        file_put_contents($configPath, $configContent);
    }

    private static function ensureStorageDirs(string $physicalPath): void
    {
        foreach ([
            $physicalPath . '/storage/uploads',
            $physicalPath . '/storage/uploads/images',
            $physicalPath . '/storage/uploads/avatars',
            $physicalPath . '/storage/uploads/temp',
        ] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException("Nao foi possivel criar pasta {$dir}");
            }
        }
    }
}
