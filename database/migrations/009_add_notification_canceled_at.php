<?php

declare(strict_types=1);

return new class {
    public function up(PDO $pdo): void
    {
        $pdo->exec(
            'ALTER TABLE notifications
                ADD COLUMN canceled_at DATETIME NULL AFTER disabled_at'
        );
    }
};
