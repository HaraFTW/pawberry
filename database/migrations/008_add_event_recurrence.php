<?php

declare(strict_types=1);

return new class {
    public function up(PDO $pdo): void
    {
        $pdo->exec(
            'ALTER TABLE events
                ADD COLUMN recurrence_interval INT UNSIGNED NULL AFTER cost,
                ADD COLUMN recurrence_unit VARCHAR(16) NULL AFTER recurrence_interval,
                ADD COLUMN reminder_lead_days INT UNSIGNED NOT NULL DEFAULT 0 AFTER recurrence_unit'
        );

        $this->copyTypeRecurrenceOntoEvents($pdo);
    }

    /**
     * Every event starts with the repeat schedule of its event type.
     * A type that does not repeat leaves those events unset.
     */
    private function copyTypeRecurrenceOntoEvents(PDO $pdo): void
    {
        $pdo->exec(
            'UPDATE events e
             INNER JOIN event_types t ON t.id = e.event_type_id
             SET e.recurrence_interval = t.recurrence_interval,
                 e.recurrence_unit = t.recurrence_unit,
                 e.reminder_lead_days = t.reminder_lead_days'
        );
    }
};
