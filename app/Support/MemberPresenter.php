<?php

declare(strict_types=1);

namespace PawBerry\Support;

use DateTimeImmutable;

final class MemberPresenter
{
    public static function ageLabel(?string $birthDate): ?string
    {
        if ($birthDate === null || $birthDate === '') {
            return null;
        }

        try {
            $born = new DateTimeImmutable($birthDate);
        } catch (\Exception) {
            return null;
        }

        $now = new DateTimeImmutable('today');
        if ($born > $now) {
            return null;
        }

        $diff = $born->diff($now);
        if ($diff->y >= 1) {
            return $diff->y === 1 ? '1 year old' : $diff->y . ' years old';
        }
        if ($diff->m >= 1) {
            return $diff->m === 1 ? '1 month old' : $diff->m . ' months old';
        }
        $days = max(1, $diff->d);
        return $days === 1 ? '1 day old' : $days . ' days old';
    }

    public static function formatDate(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($date))->format('M j, Y');
        } catch (\Exception) {
            return null;
        }
    }

    public static function isArchived(array $member): bool
    {
        return !empty($member['archived_at']);
    }
}
