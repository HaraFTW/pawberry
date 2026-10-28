<?php

declare(strict_types=1);

namespace PawBerry\Support;

use DateTimeImmutable;

final class NotificationPresenter
{
    /** App-timezone "today" as Y-m-d. */
    public static function todayString(?DateTimeImmutable $today = null): string
    {
        return ($today ?? new DateTimeImmutable('today'))->format('Y-m-d');
    }

    /**
     * UI category derived from DB status + reminder window (not a DB status).
     * Returns active | upcoming | history.
     *
     * @param array<string, mixed> $notification
     */
    public static function uiCategory(array $notification, ?DateTimeImmutable $today = null): string
    {
        $status = (string) ($notification['status'] ?? '');
        if ($status === 'completed' || $status === 'disabled') {
            return 'history';
        }
        if ($status !== 'active') {
            return 'history';
        }

        $todayStr = self::todayString($today);
        $reminder = (string) ($notification['reminder_start_date'] ?? '');
        if ($reminder !== '' && $reminder > $todayStr) {
            return 'upcoming';
        }

        return 'active';
    }

    /** @param array<string, mixed> $notification */
    public static function activityName(array $notification): string
    {
        $title = trim((string) ($notification['source_title'] ?? ''));
        if ($title !== '') {
            return $title;
        }
        return (string) ($notification['type_name'] ?? 'Care reminder');
    }

    /**
     * @param list<array<string, mixed>> $members
     */
    public static function memberNames(array $members): string
    {
        if ($members === []) {
            return 'The Gang';
        }
        $names = array_map(static fn (array $m): string => (string) $m['name'], $members);
        $count = count($names);
        if ($count === 1) {
            return $names[0];
        }
        if ($count === 2) {
            return $names[0] . ' and ' . $names[1];
        }
        $last = array_pop($names);
        return implode(', ', $names) . ', and ' . $last;
    }

    /** upcoming | due_soon | due_today | overdue */
    public static function displayState(array $notification, ?DateTimeImmutable $today = null): string
    {
        $today ??= new DateTimeImmutable('today');
        $todayStr = $today->format('Y-m-d');
        $due = (string) $notification['due_date'];
        $reminder = (string) $notification['reminder_start_date'];

        if ($due < $todayStr) {
            return 'overdue';
        }
        if ($due === $todayStr) {
            return 'due_today';
        }
        if ($reminder <= $todayStr) {
            return 'due_soon';
        }
        return 'upcoming';
    }

    /**
     * Four-stage urgency within the reminder window.
     * Returns green | yellow | red | black, or null when not yet in the reminder period.
     *
     * @param array<string, mixed> $notification
     */
    public static function urgency(array $notification, ?DateTimeImmutable $today = null): ?string
    {
        $today ??= new DateTimeImmutable('today');
        $todayStr = $today->format('Y-m-d');
        $dueStr = (string) ($notification['due_date'] ?? '');
        $reminderStr = (string) ($notification['reminder_start_date'] ?? '');

        if ($dueStr === '' || $reminderStr === '') {
            return null;
        }

        if ($todayStr > $dueStr) {
            return 'black';
        }
        if ($todayStr < $reminderStr) {
            return null;
        }

        $reminder = DateTimeImmutable::createFromFormat('!Y-m-d', $reminderStr);
        $due = DateTimeImmutable::createFromFormat('!Y-m-d', $dueStr);
        $todayDate = DateTimeImmutable::createFromFormat('!Y-m-d', $todayStr);
        if (!$reminder || !$due || !$todayDate) {
            return null;
        }

        if ($due < $reminder) {
            return 'red';
        }

        $n = ((int) $reminder->diff($due)->format('%a')) + 1;
        $d = ((int) $reminder->diff($todayDate)->format('%a')) + 1;
        $greenDays = (int) ceil($n / 2);
        $redStart = max(1, $n - 1);

        if ($d >= $redStart) {
            return 'red';
        }
        if ($d <= $greenDays) {
            return 'green';
        }

        return 'yellow';
    }

    /** Accessible urgency wording paired with colored UI (not color-only). */
    public static function urgencyLabel(?string $urgency): string
    {
        return match ($urgency) {
            'green' => 'Early in reminder period',
            'yellow' => 'Mid reminder period',
            'red' => 'Final days of reminder period',
            'black' => 'Overdue',
            default => '',
        };
    }

    public static function timingLabel(array $notification, ?DateTimeImmutable $today = null): string
    {
        $today ??= new DateTimeImmutable('today');
        $due = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $notification['due_date']);
        if (!$due) {
            return '';
        }
        $diff = (int) $today->diff($due)->format('%r%a');

        if ($diff === 0) {
            return 'Due today';
        }
        if ($diff === 1) {
            return 'Due tomorrow';
        }
        if ($diff === -1) {
            return '1 day overdue';
        }
        if ($diff < 0) {
            return abs($diff) . ' days overdue';
        }
        return 'Due in ' . $diff . ' days';
    }

    public static function popupMessage(array $notification): string
    {
        $activity = self::activityName($notification);
        $members = self::memberNames($notification['members'] ?? []);
        $state = self::displayState($notification);
        $today = new DateTimeImmutable('today');
        $due = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $notification['due_date']);
        $diff = $due ? (int) $today->diff($due)->format('%r%a') : 0;

        return match ($state) {
            'overdue' => sprintf(
                '%s for %s is %s.',
                $activity,
                $members,
                $diff === -1 ? '1 day overdue' : (abs($diff) . ' days overdue')
            ),
            'due_today' => sprintf('%s for %s is due today.', $activity, $members),
            default => sprintf(
                '%s for %s is due in %d %s.',
                $activity,
                $members,
                max(1, $diff),
                max(1, $diff) === 1 ? 'day' : 'days'
            ),
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Active',
            'completed' => 'Completed',
            'disabled' => 'Disabled',
            'upcoming' => 'Upcoming',
            'due_soon' => 'Due soon',
            'due_today' => 'Due today',
            'overdue' => 'Overdue',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * Calendar / detail status: completed|disabled|upcoming|due_soon|due_today|overdue
     *
     * @param array<string, mixed> $notification
     */
    public static function calendarStatus(array $notification, ?DateTimeImmutable $today = null): string
    {
        $persisted = (string) ($notification['status'] ?? 'active');
        if ($persisted === 'completed' || $persisted === 'disabled') {
            return $persisted;
        }
        return self::displayState($notification, $today);
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

    public static function formatDateTime(?string $datetime): ?string
    {
        if ($datetime === null || $datetime === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($datetime))->format('M j, Y');
        } catch (\Exception) {
            return null;
        }
    }
}
