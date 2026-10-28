<?php

declare(strict_types=1);

namespace PawBerry\Support;

final class EventTypePresenter
{
    /** @param array<string, mixed> $type */
    public static function isArchived(array $type): bool
    {
        return !empty($type['archived_at']);
    }

    /** @param array<string, mixed> $type */
    public static function isRecurring(array $type): bool
    {
        return isset($type['recurrence_interval'], $type['recurrence_unit'])
            && $type['recurrence_interval'] !== null
            && $type['recurrence_unit'] !== null
            && (int) $type['recurrence_interval'] > 0;
    }

    /** @param array<string, mixed> $type */
    public static function recurrenceSummary(array $type): ?string
    {
        if (!self::isRecurring($type)) {
            return null;
        }

        $interval = (int) $type['recurrence_interval'];
        $unit = (string) $type['recurrence_unit'];
        $labels = [
            'days' => ['day', 'days'],
            'weeks' => ['week', 'weeks'],
            'months' => ['month', 'months'],
            'years' => ['year', 'years'],
        ];
        $pair = $labels[$unit] ?? [$unit, $unit];
        $unitLabel = $interval === 1 ? $pair[0] : $pair[1];

        return 'Every ' . $interval . ' ' . $unitLabel;
    }

    /** @param array<string, mixed> $type */
    public static function reminderSummary(array $type): ?string
    {
        if (!self::isRecurring($type)) {
            return null;
        }

        $days = (int) ($type['reminder_lead_days'] ?? 0);
        if ($days === 0) {
            return 'Remind on the due date';
        }
        if ($days === 1) {
            return 'Remind 1 day before';
        }

        return 'Remind ' . $days . ' days before';
    }

    public static function fieldTypeLabel(string $type): string
    {
        return EventTypeCatalog::fieldTypeLabels()[$type] ?? $type;
    }
}
