<?php

declare(strict_types=1);

namespace PawBerry\Services;

use DateTimeImmutable;
use PawBerry\Repositories\EventRepository;
use PawBerry\Repositories\NotificationRepository;
use PawBerry\Support\EventPresenter;
use PawBerry\Support\NotificationPresenter;

/**
 * Builds Monday-first month grids and aggregates events + notification due dates.
 */
final class CalendarService
{
    public const MAX_CELL_INDICATORS = 4;

    private EventRepository $events;
    private NotificationRepository $notifications;

    public function __construct(
        ?EventRepository $events = null,
        ?NotificationRepository $notifications = null
    ) {
        $this->events = $events ?? new EventRepository();
        $this->notifications = $notifications ?? new NotificationRepository();
    }

    /**
     * @return array{
     *   year: int,
     *   month: int,
     *   month_start: string,
     *   month_end: string,
     *   grid_start: string,
     *   grid_end: string,
     *   heading: string,
     *   weeks: list<list<array<string, mixed>>>,
     *   by_date: array<string, array{events: list<array<string, mixed>>, notifications: list<array<string, mixed>>}>,
     *   today: string
     * }
     */
    public function buildMonth(
        int $year,
        int $month,
        array $memberIds = [],
        ?int $eventTypeId = null,
        bool $showEvents = true,
        bool $showDues = true
    ): array {
        [$year, $month] = $this->normalizeYearMonth($year, $month);
        $today = new DateTimeImmutable('today');
        $monthStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $monthEnd = $monthStart->modify('last day of this month');

        $startDow = (int) $monthStart->format('N'); // 1=Mon … 7=Sun
        $gridStart = $monthStart->modify('-' . ($startDow - 1) . ' days');
        $endDow = (int) $monthEnd->format('N');
        $gridEnd = $monthEnd->modify('+' . (7 - $endDow) . ' days');

        $from = $gridStart->format('Y-m-d');
        $to = $gridEnd->format('Y-m-d');

        $events = $showEvents
            ? $this->events->forDateRange($from, $to, $memberIds, $eventTypeId)
            : [];
        $dues = $showDues
            ? $this->notifications->forDueDateRange($from, $to, $memberIds, $eventTypeId)
            : [];

        $byDate = [];
        foreach ($events as $event) {
            $date = (string) $event['event_date'];
            $byDate[$date]['events'][] = $this->presentEvent($event);
            $byDate[$date]['notifications'] ??= [];
        }
        foreach ($dues as $notification) {
            $date = (string) $notification['due_date'];
            $byDate[$date]['notifications'][] = $this->presentNotification($notification, $today);
            $byDate[$date]['events'] ??= [];
        }

        $weeks = [];
        $cursor = $gridStart;
        while ($cursor <= $gridEnd) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $dateStr = $cursor->format('Y-m-d');
                $dayEvents = $byDate[$dateStr]['events'] ?? [];
                $dayNotifs = $byDate[$dateStr]['notifications'] ?? [];
                $indicators = $this->buildIndicators($dayEvents, $dayNotifs);

                $week[] = [
                    'date' => $dateStr,
                    'day' => (int) $cursor->format('j'),
                    'in_month' => (int) $cursor->format('n') === $month,
                    'is_today' => $dateStr === $today->format('Y-m-d'),
                    'event_count' => count($dayEvents),
                    'notification_count' => count($dayNotifs),
                    'indicators' => $indicators['visible'],
                    'overflow' => $indicators['overflow'],
                    'has_items' => $dayEvents !== [] || $dayNotifs !== [],
                ];
                $cursor = $cursor->modify('+1 day');
            }
            $weeks[] = $week;
        }

        return [
            'year' => $year,
            'month' => $month,
            'month_start' => $monthStart->format('Y-m-d'),
            'month_end' => $monthEnd->format('Y-m-d'),
            'grid_start' => $from,
            'grid_end' => $to,
            'heading' => $monthStart->format('F Y'),
            'weeks' => $weeks,
            'by_date' => $byDate,
            'today' => $today->format('Y-m-d'),
        ];
    }

    /** @return array{0: int, 1: int} */
    public function normalizeYearMonth(int $year, int $month): array
    {
        if ($month < 1 || $month > 12 || $year < 1970 || $year > 2100) {
            $today = new DateTimeImmutable('today');
            return [(int) $today->format('Y'), (int) $today->format('n')];
        }
        return [$year, $month];
    }

    public function adjacentMonth(int $year, int $month, int $delta): array
    {
        [$year, $month] = $this->normalizeYearMonth($year, $month);
        $dt = (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))
            ->modify(($delta >= 0 ? '+' : '') . $delta . ' months');
        return [(int) $dt->format('Y'), (int) $dt->format('n')];
    }

    /**
     * @param list<array<string, mixed>> $events
     * @param list<array<string, mixed>> $notifications
     * @return array{visible: list<array{kind: string, color: string, label: string}>, overflow: int}
     */
    private function buildIndicators(array $events, array $notifications): array
    {
        $all = [];
        foreach ($events as $event) {
            $all[] = [
                'kind' => 'event',
                'color' => (string) ($event['type_color'] ?? '#E4BB93'),
                'label' => 'Recorded: ' . (string) ($event['title'] ?? 'Event'),
            ];
        }
        foreach ($notifications as $notification) {
            $all[] = [
                'kind' => 'due',
                'color' => (string) ($notification['type_color'] ?? '#E4BB93'),
                'label' => 'Due: ' . (string) ($notification['activity'] ?? 'Reminder'),
            ];
        }

        $total = count($all);
        if ($total <= self::MAX_CELL_INDICATORS) {
            return ['visible' => $all, 'overflow' => 0];
        }

        $visible = array_slice($all, 0, self::MAX_CELL_INDICATORS);
        return [
            'visible' => $visible,
            'overflow' => $total - self::MAX_CELL_INDICATORS,
        ];
    }

    /** @param array<string, mixed> $event @return array<string, mixed> */
    private function presentEvent(array $event): array
    {
        $members = $event['members'] ?? [];
        return [
            'id' => (int) $event['id'],
            'date' => (string) $event['event_date'],
            'title' => EventPresenter::displayTitle($event),
            'type_name' => (string) ($event['type_name'] ?? ''),
            'type_icon' => (string) ($event['type_icon'] ?? 'notebook-pen'),
            'type_color' => (string) ($event['type_color'] ?? '#E4BB93'),
            'type_archived' => !empty($event['type_archived_at']),
            'members' => array_map(static function (array $m): array {
                return [
                    'id' => (int) $m['id'],
                    'name' => (string) $m['name'],
                    'archived' => !empty($m['archived_at']),
                ];
            }, $members),
            'member_names' => NotificationPresenter::memberNames($members),
        ];
    }

    /** @param array<string, mixed> $notification @return array<string, mixed> */
    private function presentNotification(array $notification, DateTimeImmutable $today): array
    {
        $members = $notification['members'] ?? [];
        $status = NotificationPresenter::calendarStatus($notification, $today);
        return [
            'id' => (int) $notification['id'],
            'date' => (string) $notification['due_date'],
            'activity' => NotificationPresenter::activityName($notification),
            'status' => $status,
            'status_label' => NotificationPresenter::statusLabel($status),
            'persisted_status' => (string) ($notification['status'] ?? 'active'),
            'type_name' => (string) ($notification['type_name'] ?? ''),
            'type_icon' => (string) ($notification['type_icon'] ?? 'bell'),
            'type_color' => (string) ($notification['type_color'] ?? '#E4BB93'),
            'type_archived' => !empty($notification['type_archived_at']),
            'members' => array_map(static function (array $m): array {
                return [
                    'id' => (int) $m['id'],
                    'name' => (string) $m['name'],
                    'archived' => !empty($m['archived_at']),
                ];
            }, $members),
            'member_names' => NotificationPresenter::memberNames($members),
        ];
    }
}
