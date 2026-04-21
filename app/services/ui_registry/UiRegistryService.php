<?php

class UiRegistryService
{
    private string $basePath;

    public function __construct()
    {
        $this->basePath = APP_PATH . '/ui_registry/components';
    }

    public function getAllGrouped(): array
    {
        return registry_get_all_grouped();
    }

    public function getComponent(string $path): ?array
    {
        $full = $this->basePath . '/' . $path;

        if (!is_dir($full)) {
            return null;
        }

        return [
            'meta' => json_decode(file_get_contents("$full/meta.json"), true),
            'code' => file_get_contents("$full/code.php"),
            'css'  => file_get_contents("$full/style.css"),
            'path' => $path
        ];
    }

    public function restore(string $component, string $backup): bool
    {
        $base = $this->basePath . '/' . $component;
        $backupDir = $base . '/backups/' . $backup;

        if (!is_dir($backupDir)) {
            return false;
        }

        copy($backupDir . '/code.php', $base . '/code.php');
        copy($backupDir . '/style.css', $base . '/style.css');

        return true;
    }

    public function getBackups(string $component): array
    {
        $path = $this->basePath . '/' . $component . '/backups';

        if (!is_dir($path)) return [];

        $items = array_diff(scandir($path), ['.', '..']);
        rsort($items);

        return $items;
    }
}