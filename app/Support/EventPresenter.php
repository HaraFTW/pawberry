<?php

declare(strict_types=1);

namespace PawBerry\Support;

use DateTimeImmutable;

final class EventPresenter
{
    /** @param array<string, mixed> $event */
    public static function displayTitle(array $event): string
    {
        $title = trim((string) ($event['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }
        return (string) ($event['type_name'] ?? $event['event_type_name'] ?? 'Event');
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

    public static function formatCost(mixed $cost): ?string
    {
        if ($cost === null || $cost === '') {
            return null;
        }
        if (!is_numeric($cost)) {
            return null;
        }
        return number_format((float) $cost, 2);
    }

    /**
     * @param array<string, mixed> $valueRow
     */
    public static function formatFieldValue(array $valueRow): string
    {
        $type = (string) ($valueRow['field_type'] ?? 'text');
        $raw = $valueRow['value_text'] ?? null;
        if ($raw === null || $raw === '') {
            return '—';
        }

        return match ($type) {
            'checkbox' => ((string) $raw === '1') ? 'Yes' : 'No',
            'rating' => ((string) $raw) . ' / 5',
            'date' => self::formatDate((string) $raw) ?? (string) $raw,
            'number' => is_numeric($raw) ? rtrim(rtrim(number_format((float) $raw, 4, '.', ''), '0'), '.') : (string) $raw,
            default => (string) $raw,
        };
    }

    public static function notesPreview(?string $notes, int $max = 120): ?string
    {
        if ($notes === null) {
            return null;
        }
        $notes = trim(preg_replace('/\s+/u', ' ', $notes) ?? '');
        if ($notes === '') {
            return null;
        }
        if (mb_strlen($notes) <= $max) {
            return $notes;
        }
        return rtrim(mb_substr($notes, 0, $max - 1)) . '…';
    }
}
