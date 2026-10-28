<?php

declare(strict_types=1);

return new class {
    public function up(PDO $pdo): void
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
                popup_snoozed_through_date DATE NULL,
                status VARCHAR(16) NOT NULL DEFAULT \'active\',
                completed_at DATETIME NULL,
                disabled_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY notifications_status_due_index (status, due_date),
                KEY notifications_status_reminder_index (status, reminder_start_date),
                KEY notifications_popup_snooze_index (status, popup_snoozed_through_date),
                KEY notifications_source_event_id_index (source_event_id),
                KEY notifications_completion_event_id_index (completion_event_id),
                CONSTRAINT notifications_source_event_fk
                    FOREIGN KEY (source_event_id) REFERENCES events (id)
                    ON DELETE RESTRICT,
                CONSTRAINT notifications_completion_event_fk
                    FOREIGN KEY (completion_event_id) REFERENCES events (id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
};
