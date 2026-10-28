<?php

declare(strict_types=1);

return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS event_types (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                description TEXT NULL,
                icon VARCHAR(64) NOT NULL,
                color CHAR(7) NOT NULL,
                recurrence_interval INT UNSIGNED NULL,
                recurrence_unit VARCHAR(16) NULL,
                reminder_lead_days INT UNSIGNED NOT NULL DEFAULT 0,
                archived_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY event_types_archived_at_index (archived_at),
                KEY event_types_name_index (name),
                CONSTRAINT event_types_recurrence_check CHECK (
                    (recurrence_interval IS NULL AND recurrence_unit IS NULL)
                    OR (
                        recurrence_interval IS NOT NULL
                        AND recurrence_interval > 0
                        AND recurrence_unit IN (\'days\', \'weeks\', \'months\', \'years\')
                    )
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS event_type_fields (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_type_id INT UNSIGNED NOT NULL,
                name VARCHAR(120) NOT NULL,
                field_key VARCHAR(64) NOT NULL,
                field_type VARCHAR(32) NOT NULL,
                is_required TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                deleted_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY event_type_fields_type_key_unique (event_type_id, field_key),
                KEY event_type_fields_type_sort_index (event_type_id, sort_order),
                KEY event_type_fields_deleted_at_index (deleted_at),
                CONSTRAINT event_type_fields_type_fk
                    FOREIGN KEY (event_type_id) REFERENCES event_types (id)
                    ON DELETE CASCADE,
                CONSTRAINT event_type_fields_type_check CHECK (
                    field_type IN (\'text\', \'number\', \'date\', \'checkbox\', \'rating\')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
};
