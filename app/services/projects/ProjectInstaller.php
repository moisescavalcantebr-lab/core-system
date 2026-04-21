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
            SELECT *
            FROM projects
            WHERE id = :id
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
}