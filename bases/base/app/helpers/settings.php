<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

function getSetting(string $key, $default = null)
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT setting_value
        FROM project_settings
        WHERE setting_key = :key
        LIMIT 1
    ");

    $stmt->execute(['key' => $key]);
    $value = $stmt->fetchColumn();

    return $value !== false ? $value : $default;
}

function setSetting(string $key, $value, string $group = 'general'): void
{
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO project_settings (setting_key, setting_value, setting_group)
        VALUES (:key, :value, :group)
        ON DUPLICATE KEY UPDATE
            setting_value = :value,
            setting_group = :group
    ");

    $stmt->execute([
        'key'   => $key,
        'value' => $value,
        'group' => $group
    ]);
}