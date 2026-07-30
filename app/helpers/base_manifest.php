<?php

if (!function_exists('base_manifest_payload')) {
    function base_manifest_payload(array $base, string $basePath): array
    {
        $schemaPath = $basePath . '/app/database/schema.sql';
        $schemaHash = is_file($schemaPath) ? md5_file($schemaPath) : null;

        return [
            'name' => (string)($base['name'] ?? ''),
            'slug' => (string)($base['slug'] ?? ''),
            'description' => (string)($base['description'] ?? ''),
            'allows_users' => (int)($base['allows_users'] ?? 1),
            'max_admins' => (int)($base['max_admins'] ?? 1),
            'status' => (int)($base['status'] ?? 1),
            'is_protected' => (int)($base['is_protected'] ?? 0),
            'schema' => [
                'path' => 'app/database/schema.sql',
                'hash' => $schemaHash,
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }
}

if (!function_exists('base_write_manifest')) {
    function base_write_manifest(array $base, string $basePath): void
    {
        if (!is_dir($basePath)) {
            throw new RuntimeException('Pasta da base nao encontrada.');
        }

        $manifest = base_manifest_payload($base, $basePath);
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Nao foi possivel gerar o manifesto da base.');
        }

        if (file_put_contents($basePath . '/base.json', $json . PHP_EOL) === false) {
            throw new RuntimeException('Nao foi possivel salvar o manifesto da base.');
        }
    }
}

if (!function_exists('base_ensure_plan_prices')) {
    function base_ensure_plan_prices(PDO $pdo, int $baseId): void
    {
        $planBases = $pdo->prepare("
            INSERT IGNORE INTO plan_bases (plan_id, base_id)
            SELECT id, :base_id
            FROM plans
            WHERE status = 1
        ");
        $planBases->execute(['base_id' => $baseId]);

        $stmt = $pdo->prepare("
            INSERT INTO base_plan_prices (base_id, plan_price_id, custom_price, status)
            SELECT :base_id, pp.id, pp.price, 1
            FROM plan_prices pp
            INNER JOIN plans pl ON pl.id = pp.plan_id
            WHERE pp.status = 1
              AND pl.status = 1
              AND (
                  pp.billing_cycle = 'free'
                  OR (LOWER(pl.name) LIKE '%start%' AND pp.billing_cycle = 'monthly')
                  OR (LOWER(pl.name) LIKE '%plus%' AND pp.billing_cycle = 'annual')
                  OR (LOWER(pl.name) NOT LIKE '%start%' AND LOWER(pl.name) NOT LIKE '%plus%' AND pp.billing_cycle IN ('monthly', 'annual'))
              )
              AND NOT EXISTS (
                  SELECT 1
                  FROM base_plan_prices bpp
                  WHERE bpp.base_id = :base_id_lookup
                    AND bpp.plan_price_id = pp.id
              )
        ");
        $stmt->execute([
            'base_id' => $baseId,
            'base_id_lookup' => $baseId,
        ]);
    }
}

if (!function_exists('base_register_manifests')) {
    function base_register_manifests(PDO $pdo): void
    {
        if (!defined('BASES_PATH') || !is_dir(BASES_PATH)) {
            return;
        }

        $folders = scandir(BASES_PATH);
        if ($folders === false) {
            return;
        }

        foreach ($folders as $folder) {
            if ($folder === '.' || $folder === '..') {
                continue;
            }

            $basePath = BASES_PATH . '/' . $folder;
            $manifestPath = $basePath . '/base.json';

            if (!is_dir($basePath) || !is_file($manifestPath)) {
                continue;
            }

            $manifest = json_decode((string)file_get_contents($manifestPath), true);
            if (!is_array($manifest)) {
                continue;
            }

            $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($manifest['slug'] ?? $folder)));
            if ($slug === '') {
                continue;
            }

            $name = trim((string)($manifest['name'] ?? $slug));
            $description = (string)($manifest['description'] ?? '');
            $allowsUsers = (int)($manifest['allows_users'] ?? 1);
            $maxAdmins = (int)($manifest['max_admins'] ?? 1);
            $status = (int)($manifest['status'] ?? 1);
            $isProtected = (int)($manifest['is_protected'] ?? 0);

            $stmt = $pdo->prepare("
                INSERT INTO bases (name, slug, description, allows_users, max_admins, status, is_protected)
                VALUES (:name, :slug, :description, :allows_users, :max_admins, :status, :is_protected)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    description = VALUES(description),
                    allows_users = VALUES(allows_users),
                    max_admins = VALUES(max_admins),
                    status = VALUES(status),
                    is_protected = VALUES(is_protected)
            ");
            $stmt->execute([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'allows_users' => $allowsUsers,
                'max_admins' => $maxAdmins,
                'status' => $status,
                'is_protected' => $isProtected,
            ]);

            $baseId = (int)$pdo->lastInsertId();
            if ($baseId <= 0) {
                $lookup = $pdo->prepare("SELECT id FROM bases WHERE slug = ? LIMIT 1");
                $lookup->execute([$slug]);
                $baseId = (int)$lookup->fetchColumn();
            }

            if ($baseId > 0) {
                base_ensure_plan_prices($pdo, $baseId);
            }
        }
    }
}
