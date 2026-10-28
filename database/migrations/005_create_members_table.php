<?php

declare(strict_types=1);

return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS members (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                species VARCHAR(120) NOT NULL,
                breed VARCHAR(120) NULL,
                sex VARCHAR(40) NULL,
                birth_date DATE NULL,
                microchip_number VARCHAR(64) NULL,
                description TEXT NULL,
                notes TEXT NULL,
                photo_original VARCHAR(255) NULL,
                photo_cropped VARCHAR(255) NULL,
                archived_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY members_archived_at_index (archived_at),
                KEY members_name_index (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
};
