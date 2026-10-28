<?php

declare(strict_types=1);

namespace PawBerry\Controllers;

use DateTimeImmutable;
use PawBerry\Core\Request;
use PawBerry\Core\Response;
use PawBerry\Core\View;
use PawBerry\Repositories\EventTypeRepository;
use PawBerry\Repositories\MemberRepository;
use PawBerry\Services\CalendarService;

final class CalendarController
{
    private CalendarService $calendar;
    private MemberRepository $members;
    private EventTypeRepository $types;

    public function __construct()
    {
        $this->calendar = new CalendarService();
        $this->members = new MemberRepository();
        $this->types = new EventTypeRepository();
    }

    public function index(Request $request): Response
    {
        $today = new DateTimeImmutable('today');
        $year = (int) $request->query('year', $today->format('Y'));
        $month = (int) $request->query('month', $today->format('n'));
        [$year, $month] = $this->calendar->normalizeYearMonth($year, $month);

        $memberIds = $this->parseMemberIds($request->query('member', null));
        $typeId = (int) $request->query('type', 0);
        if ($typeId > 0 && $this->types->find($typeId) === null) {
            $typeId = 0;
        }
        $validMemberIds = [];
        foreach ($memberIds as $memberId) {
            if ($this->members->find($memberId) !== null) {
                $validMemberIds[] = $memberId;
            }
        }

        $showEvents = $this->parseToggle($request->query('events', '1'), true);
        $showDues = $this->parseToggle($request->query('dues', '1'), true);

        $monthData = $this->calendar->buildMonth(
            $year,
            $month,
            $validMemberIds,
            $typeId > 0 ? $typeId : null,
            $showEvents,
            $showDues
        );

        $selected = $this->resolveSelectedDate(
            (string) $request->query('day', ''),
            $monthData['month_start'],
            $monthData['month_end'],
            $monthData['today']
        );

        $filtersActive = $validMemberIds !== []
            || $typeId > 0
            || !$showEvents
            || !$showDues;

        [$prevYear, $prevMonth] = $this->calendar->adjacentMonth($year, $month, -1);
        [$nextYear, $nextMonth] = $this->calendar->adjacentMonth($year, $month, 1);

        $filterQuery = $this->filterQueryParams($validMemberIds, $typeId, $showEvents, $showDues);

        return View::render('calendar/index', [
            'title' => 'Calendar',
            'nav' => 'calendar',
            'calendar' => $monthData,
            'selectedDate' => $selected,
            'selectedDayData' => $monthData['by_date'][$selected] ?? ['events' => [], 'notifications' => []],
            'filterMemberIds' => $validMemberIds,
            'filterTypeId' => $typeId,
            'showEvents' => $showEvents,
            'showDues' => $showDues,
            'filtersActive' => $filtersActive,
            'allMembersForFilter' => $this->membersForFilter($validMemberIds),
            'allTypesForFilter' => $this->typesForFilter($typeId),
            'prevYear' => $prevYear,
            'prevMonth' => $prevMonth,
            'nextYear' => $nextYear,
            'nextMonth' => $nextMonth,
            'filterQuery' => $filterQuery,
            'weekdayLabels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'canAddForSelected' => $selected <= $monthData['today'],
        ]);
    }

    private function resolveSelectedDate(string $dayParam, string $monthStart, string $monthEnd, string $today): string
    {
        if ($dayParam !== '') {
            $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $dayParam);
            if ($dt && $dt->format('Y-m-d') === $dayParam) {
                // Allow selecting adjacent-month days visible on the grid.
                return $dayParam;
            }
            // Bare day-of-month within current month.
            if (ctype_digit($dayParam)) {
                $n = (int) $dayParam;
                $candidate = substr($monthStart, 0, 8) . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
                $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $candidate);
                if ($dt && $dt->format('Y-m-d') === $candidate && $candidate >= $monthStart && $candidate <= $monthEnd) {
                    return $candidate;
                }
            }
        }

        if ($today >= $monthStart && $today <= $monthEnd) {
            return $today;
        }
        return $monthStart;
    }

    /**
     * @param list<int> $memberIds
     * @return array<string, mixed>
     */
    private function filterQueryParams(array $memberIds, int $typeId, bool $showEvents, bool $showDues): array
    {
        $params = [];
        if (count($memberIds) === 1) {
            $params['member'] = $memberIds[0];
        } elseif (count($memberIds) > 1) {
            $params['member'] = $memberIds;
        }
        if ($typeId > 0) {
            $params['type'] = $typeId;
        }
        if (!$showEvents) {
            $params['events'] = '0';
        }
        if (!$showDues) {
            $params['dues'] = '0';
        }
        return $params;
    }

    private function parseToggle(mixed $raw, bool $default): bool
    {
        if ($raw === null || $raw === '') {
            return $default;
        }
        $value = strtolower(trim((string) $raw));
        if (in_array($value, ['0', 'false', 'off', 'no'], true)) {
            return false;
        }
        if (in_array($value, ['1', 'true', 'on', 'yes'], true)) {
            return true;
        }
        return $default;
    }

    /** @return list<int> */
    private function parseMemberIds(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $values = is_array($raw) ? $raw : [$raw];
        $ids = [];
        foreach ($values as $value) {
            if (is_array($value)) {
                continue;
            }
            $id = (int) $value;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    /**
     * @param list<int> $selectedIds
     * @return list<array<string, mixed>>
     */
    private function membersForFilter(array $selectedIds): array
    {
        $active = $this->members->allActive();
        if ($selectedIds === []) {
            return $active;
        }
        $activeIds = [];
        foreach ($active as $m) {
            $activeIds[(int) $m['id']] = true;
        }
        $extra = [];
        foreach ($selectedIds as $selectedId) {
            if (isset($activeIds[$selectedId])) {
                continue;
            }
            $member = $this->members->find($selectedId);
            if ($member !== null) {
                $extra[] = $member;
            }
        }
        return $extra === [] ? $active : array_merge($active, $extra);
    }

    /** @return list<array<string, mixed>> */
    private function typesForFilter(int $selectedId): array
    {
        $active = $this->types->allActive();
        if ($selectedId < 1) {
            return $active;
        }
        foreach ($active as $t) {
            if ((int) $t['id'] === $selectedId) {
                return $active;
            }
        }
        $extra = $this->types->find($selectedId);
        return $extra ? array_merge($active, [$extra]) : $active;
    }
}
