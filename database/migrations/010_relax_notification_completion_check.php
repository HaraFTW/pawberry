<?php

declare(strict_types=1);

/**
 * Allow completed notifications to keep history after their completion event
 * is deleted (completion_event_id SET NULL via FK).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        // MariaDB 10.4 uses DROP CONSTRAINT (not DROP CHECK).
        $pdo->exec('ALTER TABLE notifications DROP CONSTRAINT notifications_completion_consistency');
        $pdo->exec(
            "ALTER TABLE notifications
             ADD CONSTRAINT notifications_completion_consistency CHECK (
                (status = 'completed' AND completed_at IS NOT NULL)
                OR (status <> 'completed' AND completed_at IS NULL AND completion_event_id IS NULL)
             )"
        );
    }
};
