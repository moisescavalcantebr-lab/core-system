<?php
declare(strict_types=1);

class SettingsService
{
    public function __construct(private PDO $pdo) {}

    public function get(string $key, $default = null)
    {
        $stmt = $this->pdo->prepare("
            SELECT setting_value 
            FROM core_settings 
            WHERE setting_key = :key
        ");

        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();

        return $value !== false ? $value : $default;
    }

    public function set(string $key, string $value): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO core_settings (setting_key, setting_value)
            VALUES (:key, :value)
            ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value)
        ");

        $stmt->execute([
            'key' => $key,
            'value' => $value
        ]);
    }

    public function all(): array
    {
        return $this->pdo->query("
            SELECT setting_key, setting_value 
            FROM core_settings
        ")->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
