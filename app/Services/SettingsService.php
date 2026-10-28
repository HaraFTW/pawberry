<?php

declare(strict_types=1);

namespace PawBerry\Services;

use PawBerry\Core\Database;

final class SettingsService
{
    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = Database::connection()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if ($row === false) {
            return $default;
        }
        return (string) $row['setting_value'];
    }

    public function set(string $key, string $value): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO settings (setting_key, setting_value, updated_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
        );
        $stmt->execute([$key, $value]);
    }
}
