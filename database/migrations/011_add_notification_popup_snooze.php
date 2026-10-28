<?php

declare(strict_types=1);

/**
 * Calendar-day popup snooze: hide in-app popups until the next local day.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(
            'ALTER TABLE notifications
             ADD COLUMN popup_snoozed_through_date DATE NULL AFTER reminder_lead_days,
             ADD KEY notifications_popup_snooze_index (status, popup_snoozed_through_date)'
        );
    }
};
