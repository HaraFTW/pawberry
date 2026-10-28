<?php

declare(strict_types=1);

namespace PawBerry\Services;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Calendar-aware recurrence arithmetic.
 * Month/year advances clamp to the last valid day (e.g. Jan 31 + 1 month → Feb 28/29).
 */
final class RecurrenceCalculator
{
    public const UNITS = ['days', 'weeks', 'months', 'years'];

    public function nextDueDate(string $fromDate, int $interval, string $unit): string
    {
        $from = $this->parseDate($fromDate);
        $next = $this->addInterval($from, $interval, $unit);
        return $next->format('Y-m-d');
    }

    public function reminderStartDate(string $dueDate, int $leadDays): string
    {
        $due = $this->parseDate($dueDate);
        $leadDays = max(0, $leadDays);
        if ($leadDays === 0) {
            return $due->format('Y-m-d');
        }
        return $due->modify('-' . $leadDays . ' days')->format('Y-m-d');
    }

    public function addInterval(DateTimeImmutable $from, int $interval, string $unit): DateTimeImmutable
    {
        if ($interval < 1) {
            throw new InvalidArgumentException('Recurrence interval must be at least 1.');
        }
        if (!in_array($unit, self::UNITS, true)) {
            throw new InvalidArgumentException('Unsupported recurrence unit.');
        }

        return match ($unit) {
            'days' => $from->modify('+' . $interval . ' days'),
            'weeks' => $from->modify('+' . ($interval * 7) . ' days'),
            'months' => $this->addCalendarMonths($from, $interval),
            'years' => $this->addCalendarYears($from, $interval),
        };
    }

    private function addCalendarMonths(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        $day = (int) $date->format('j');
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');

        $monthIndex = ($year * 12) + ($month - 1) + $months;
        $targetYear = intdiv($monthIndex, 12);
        $targetMonth = ($monthIndex % 12) + 1;
        $lastDay = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $targetYear, $targetMonth)))->format('t');
        $clampedDay = min($day, $lastDay);

        return $date->setDate($targetYear, $targetMonth, $clampedDay)->setTime(0, 0);
    }

    private function addCalendarYears(DateTimeImmutable $date, int $years): DateTimeImmutable
    {
        $year = (int) $date->format('Y') + $years;
        $month = (int) $date->format('n');
        $day = (int) $date->format('j');
        $lastDay = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
        return $date->setDate($year, $month, min($day, $lastDay))->setTime(0, 0);
    }

    private function parseDate(string $date): DateTimeImmutable
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Invalid date: ' . $date);
        }
        return $dt;
    }
}
