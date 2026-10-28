<?php

declare(strict_types=1);

namespace PawBerry\Services;

use PawBerry\Core\Config;
use PawBerry\Core\Database;

final class RateLimiter
{
    public function tooManyAttempts(string $ip): bool
    {
        $max = (int) Config::get('rate_limit.max_attempts', 5);
        $window = (int) Config::get('rate_limit.window_seconds', 900);

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS cnt FROM login_attempts
             WHERE ip_address = ? AND attempted_at > (NOW() - INTERVAL ? SECOND)'
        );
        $stmt->execute([$ip, $window]);
        $row = $stmt->fetch();
        $count = (int) ($row['cnt'] ?? 0);

        return $count >= $max;
    }

    public function hit(string $ip): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO login_attempts (ip_address, attempted_at) VALUES (?, NOW())'
        );
        $stmt->execute([$ip]);

        // Opportunistic cleanup of old rows
        $window = (int) Config::get('rate_limit.window_seconds', 900);
        $cleanup = Database::connection()->prepare(
            'DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL ? SECOND)'
        );
        $cleanup->execute([$window * 4]);
    }

    public function clear(string $ip): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM login_attempts WHERE ip_address = ?');
        $stmt->execute([$ip]);
    }
}
