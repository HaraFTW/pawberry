<?php

declare(strict_types=1);

return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS events (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_type_id INT UNSIGNED NOT NULL,
                event_date DATE NOT NULL,
                title VARCHAR(160) NULL,
                notes TEXT NULL,
                cost DECIMAL(10, 2) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY events_event_date_index (event_date),
                KEY events_event_type_id_index (event_type_id),
                KEY events_created_at_index (created_at),
                CONSTRAINT events_event_type_fk
                    FOREIGN KEY (event_type_id) REFERENCES event_types (id)
                    ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS event_members (
                event_id INT UNSIGNED NOT NULL,
                member_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (event_id, member_id),
                KEY event_members_member_id_index (member_id),
                CONSTRAINT event_members_event_fk
                    FOREIGN KEY (event_id) REFERENCES events (id)
                    ON DELETE CASCADE,
                CONSTRAINT event_members_member_fk
                    FOREIGN KEY (member_id) REFERENCES members (id)
                    ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS event_field_values (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_id INT UNSIGNED NOT NULL,
                event_type_field_id INT UNSIGNED NOT NULL,
                field_key VARCHAR(64) NOT NULL,
                field_name VARCHAR(120) NOT NULL,
                field_type VARCHAR(32) NOT NULL,
                value_text TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY event_field_values_event_field_unique (event_id, event_type_field_id),
                KEY event_field_values_field_id_index (event_type_field_id),
                CONSTRAINT event_field_values_event_fk
                    FOREIGN KEY (event_id) REFERENCES events (id)
                    ON DELETE CASCADE,
                CONSTRAINT event_field_values_field_fk
                    FOREIGN KEY (event_type_field_id) REFERENCES event_type_fields (id)
                    ON DELETE RESTRICT,
                CONSTRAINT event_field_values_type_check CHECK (
                    field_type IN (\'text\', \'number\', \'date\', \'checkbox\', \'rating\')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS event_attachments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_id INT UNSIGNED NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                stored_path VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) NOT NULL,
                file_size INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY event_attachments_stored_path_unique (stored_path),
                KEY event_attachments_event_id_index (event_id),
                CONSTRAINT event_attachments_event_fk
                    FOREIGN KEY (event_id) REFERENCES events (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
};
