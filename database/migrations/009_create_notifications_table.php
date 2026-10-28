<?php

declare(strict_types=1);

return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS notifications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_event_id INT UNSIGNED NOT NULL,
                completion_event_id INT UNSIGNED NULL,
                due_date DATE NOT NULL,
                reminder_start_date DATE NOT NULL,
                recurrence_interval INT UNSIGNED NOT NULL,
                recurrence_unit VARCHAR(16) NOT NULL,
                reminder_lead_days INT UNSIGNED NOT NULL DEFAULT 0,
                status VARCHAR(16) NOT NULL DEFAULT \'active\',
                completed_at DATETIME NULL,
                disabled_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                active_source_guard INT UNSIGNED
                    GENERATED ALWAYS AS (
                        CASE WHEN status = \'active\' THEN source_event_id ELSE NULL END
                    ) STORED,
                UNIQUE KEY notifications_active_source_unique (active_source_guard),
                KEY notifications_status_due_index (status, due_date),
                KEY notifications_status_reminder_index (status, reminder_start_date),
                KEY notifications_source_event_id_index (source_event_id),
                KEY notifications_completion_event_id_index (completion_event_id),
                CONSTRAINT notifications_source_event_fk
                    FOREIGN KEY (source_event_id) REFERENCES events (id)
                    ON DELETE RESTRICT,
                CONSTRAINT notifications_completion_event_fk
                    FOREIGN KEY (completion_event_id) REFERENCES events (id)
                    ON DELETE SET NULL,
                CONSTRAINT notifications_status_check CHECK (
                    status IN (\'active\', \'completed\', \'disabled\')
                ),
                CONSTRAINT notifications_recurrence_check CHECK (
                    recurrence_interval > 0
                    AND recurrence_unit IN (\'days\', \'weeks\', \'months\', \'years\')
                ),
                CONSTRAINT notifications_completion_consistency CHECK (
                    (status = \'completed\' AND completed_at IS NOT NULL)
                    OR (status <> \'completed\' AND completed_at IS NULL AND completion_event_id IS NULL)
                ),
                CONSTRAINT notifications_disabled_consistency CHECK (
                    (status = \'disabled\' AND disabled_at IS NOT NULL)
                    OR (status <> \'disabled\' AND disabled_at IS NULL)
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
};
