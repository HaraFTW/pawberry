<?php

declare(strict_types=1);

namespace PawBerry\Support;

/**
 * Curated icons and colors for event types.
 * Icon identifiers must exist in public/assets/js/icons.js.
 */
final class EventTypeCatalog
{
    public const RECURRENCE_UNITS = ['days', 'weeks', 'months', 'years'];

    public const FIELD_TYPES = ['text', 'number', 'date', 'checkbox', 'rating'];

    /** @return list<array{id: string, label: string}> */
    public static function icons(): array
    {
        return [
            ['id' => 'stethoscope', 'label' => 'Stethoscope'],
            ['id' => 'syringe', 'label' => 'Syringe'],
            ['id' => 'pill', 'label' => 'Pill'],
            ['id' => 'bug', 'label' => 'Bug'],
            ['id' => 'scissors', 'label' => 'Scissors'],
            ['id' => 'droplets', 'label' => 'Droplets'],
            ['id' => 'sparkles', 'label' => 'Sparkles'],
            ['id' => 'heart-pulse', 'label' => 'Heart pulse'],
            ['id' => 'scale', 'label' => 'Scale'],
            ['id' => 'bone', 'label' => 'Bone'],
            ['id' => 'thermometer', 'label' => 'Thermometer'],
            ['id' => 'clipboard-list', 'label' => 'Clipboard'],
            ['id' => 'shield', 'label' => 'Shield'],
            ['id' => 'leaf', 'label' => 'Leaf'],
            ['id' => 'paw-print', 'label' => 'Paw'],
            ['id' => 'heart', 'label' => 'Heart'],
            ['id' => 'activity', 'label' => 'Activity'],
            ['id' => 'bandage', 'label' => 'Bandage'],
            ['id' => 'bath', 'label' => 'Bath'],
            ['id' => 'brush', 'label' => 'Brush'],
            ['id' => 'star', 'label' => 'Star'],
            ['id' => 'tags', 'label' => 'Tags'],
            ['id' => 'notebook-pen', 'label' => 'Notebook'],
            ['id' => 'calendar-days', 'label' => 'Calendar'],
        ];
    }

    /** @return list<string> */
    public static function iconIds(): array
    {
        return array_map(static fn (array $icon): string => $icon['id'], self::icons());
    }

    public static function isAllowedIcon(string $icon): bool
    {
        return in_array($icon, self::iconIds(), true);
    }

    /** @return list<string> */
    public static function palette(): array
    {
        return [
            '#E4BB93',
            '#E9AD78',
            '#E2A096',
            '#D5A0A5',
            '#B8A4C9',
            '#9BB8C9',
            '#8FBCB8',
            '#A9BE9C',
            '#CDB6A5',
            '#D4C4A8',
        ];
    }

    public static function isValidHexColor(string $color): bool
    {
        return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $color);
    }

    /** @return array<string, string> */
    public static function fieldTypeLabels(): array
    {
        return [
            'text' => 'Text',
            'number' => 'Number',
            'date' => 'Date',
            'checkbox' => 'Checkbox',
            'rating' => 'Rating (1–5)',
        ];
    }

    /** @return array<string, string> */
    public static function recurrenceUnitLabels(): array
    {
        return [
            'days' => 'days',
            'weeks' => 'weeks',
            'months' => 'months',
            'years' => 'years',
        ];
    }

    /**
     * Build a stable field_key from a display name.
     *
     * @param list<string> $taken
     */
    public static function makeFieldKey(string $name, array $taken = []): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/u', '_', $slug) ?? '';
        $slug = trim($slug, '_');
        if ($slug === '') {
            $slug = 'field';
        }
        if (strlen($slug) > 48) {
            $slug = substr($slug, 0, 48);
            $slug = rtrim($slug, '_');
        }

        $key = $slug;
        $i = 2;
        $takenMap = array_fill_keys($taken, true);
        while (isset($takenMap[$key])) {
            $suffix = '_' . $i;
            $base = substr($slug, 0, max(1, 64 - strlen($suffix)));
            $key = rtrim($base, '_') . $suffix;
            $i++;
        }

        return $key;
    }
}
