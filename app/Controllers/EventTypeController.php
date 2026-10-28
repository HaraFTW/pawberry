<?php

declare(strict_types=1);

namespace PawBerry\Controllers;

use PawBerry\Core\Request;
use PawBerry\Core\Response;
use PawBerry\Core\Session;
use PawBerry\Core\Url;
use PawBerry\Core\View;
use PawBerry\Repositories\EventTypeRepository;
use PawBerry\Support\EventTypeCatalog;

final class EventTypeController
{
    private EventTypeRepository $types;

    public function __construct()
    {
        $this->types = new EventTypeRepository();
    }

    public function index(Request $request): Response
    {
        $showArchived = (string) $request->query('archived', '') === '1';
        $active = $this->types->allActive();
        $archived = $showArchived ? $this->types->allArchived() : [];
        $archivedCount = $this->types->countArchived();

        return View::render('event-types/index', [
            'title' => 'Event Types',
            'nav' => 'event-types',
            'types' => $active,
            'archivedTypes' => $archived,
            'showArchived' => $showArchived,
            'archivedCount' => $archivedCount,
        ]);
    }

    public function createForm(Request $request): Response
    {
        return View::render('event-types/form', [
            'title' => 'Add Event Type',
            'nav' => 'event-types',
            'mode' => 'create',
            'type' => $this->emptyType(),
            'fields' => [],
            'errors' => [],
            'old' => [],
        ]);
    }

    public function store(Request $request): Response
    {
        $parsed = $this->parseInput($request);
        $errors = $this->validate($parsed);

        if ($errors !== []) {
            return View::render('event-types/form', [
                'title' => 'Add Event Type',
                'nav' => 'event-types',
                'mode' => 'create',
                'type' => $this->emptyType(),
                'fields' => $parsed['fields'],
                'errors' => $errors,
                'old' => $parsed,
            ]);
        }

        $id = $this->types->createWithFields($parsed['type'], $parsed['fields']);
        Session::flash('success', $parsed['type']['name'] . ' is ready to use.');
        Session::flash('sound', 'type');
        return Response::redirect(Url::to('/event-types'));
    }

    public function editForm(Request $request): Response
    {
        $type = $this->requireType($request);
        if ($type instanceof Response) {
            return $type;
        }

        $fields = $this->types->fieldsForType((int) $type['id']);

        return View::render('event-types/form', [
            'title' => 'Edit Event Type',
            'nav' => 'event-types',
            'mode' => 'edit',
            'type' => $type,
            'fields' => $fields,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function update(Request $request): Response
    {
        $type = $this->requireType($request);
        if ($type instanceof Response) {
            return $type;
        }

        $id = (int) $type['id'];
        $parsed = $this->parseInput($request, $id);
        $errors = $this->validate($parsed, $id);

        if ($errors !== []) {
            return View::render('event-types/form', [
                'title' => 'Edit Event Type',
                'nav' => 'event-types',
                'mode' => 'edit',
                'type' => $type,
                'fields' => $parsed['fields'],
                'errors' => $errors,
                'old' => $parsed,
            ]);
        }

        $this->types->updateWithFields($id, $parsed['type'], $parsed['fields']);
        Session::flash('success', $parsed['type']['name'] . ' was updated.');
        Session::flash('sound', 'type');
        return Response::redirect(Url::to('/event-types'));
    }

    public function archive(Request $request): Response
    {
        $type = $this->requireType($request);
        if ($type instanceof Response) {
            return $type;
        }

        $this->types->archive((int) $type['id']);
        Session::flash('success', $type['name'] . ' was archived.');
        return Response::redirect(Url::to('/event-types'));
    }

    public function restore(Request $request): Response
    {
        $type = $this->requireType($request);
        if ($type instanceof Response) {
            return $type;
        }

        $id = (int) $type['id'];
        $name = (string) $type['name'];

        if ($this->types->activeNameExists($name, $id)) {
            Session::flash('error', 'An active event type already uses the name “' . $name . '”. Rename one before restoring.');
            return Response::redirect(Url::to('/event-types?archived=1'));
        }

        $this->types->restore($id);
        Session::flash('success', $name . ' is back.');
        return Response::redirect(Url::to('/event-types'));
    }

    /** @return array<string, mixed>|Response */
    private function requireType(Request $request): array|Response
    {
        $id = (int) $request->param('id', 0);
        if ($id < 1) {
            return View::error(404, 'Event type not found', 'That event type could not be found.');
        }

        $type = $this->types->find($id);
        if ($type === null) {
            return View::error(404, 'Event type not found', 'That event type could not be found.');
        }

        return $type;
    }

    /** @return array<string, mixed> */
    private function emptyType(): array
    {
        return [
            'id' => null,
            'name' => '',
            'description' => '',
            'icon' => 'paw-print',
            'color' => '#E4BB93',
            'recurrence_interval' => null,
            'recurrence_unit' => null,
            'reminder_lead_days' => 0,
            'archived_at' => null,
        ];
    }

    /**
     * @return array{
     *   type: array{
     *     name: string,
     *     description: ?string,
     *     icon: string,
     *     color: string,
     *     recurrence_interval: ?int,
     *     recurrence_unit: ?string,
     *     reminder_lead_days: int
     *   },
     *   fields: list<array{
     *     id: ?int,
     *     field_key: ?string,
     *     name: string,
     *     field_type: string,
     *     is_required: bool
     *   }>,
     *   recurrence_enabled: bool,
     *   name: string,
     *   description: string,
     *   icon: string,
     *   color: string,
     *   recurrence_interval: string,
     *   recurrence_unit: string,
     *   reminder_lead_days: string
     * }
     */
    private function parseInput(Request $request, ?int $typeId = null): array
    {
        $nullIfEmpty = static function (string $value): ?string {
            $value = trim($value);
            return $value === '' ? null : $value;
        };

        $name = trim((string) $request->input('name', ''));
        $description = $nullIfEmpty((string) $request->input('description', ''));
        $icon = trim((string) $request->input('icon', ''));
        $color = strtoupper(trim((string) $request->input('color', '')));
        if ($color !== '' && !str_starts_with($color, '#')) {
            $color = '#' . $color;
        }
        // Normalize to #RRGGBB uppercase for storage consistency after validation.
        if (EventTypeCatalog::isValidHexColor($color)) {
            $color = strtoupper($color);
        }

        $recurrenceEnabled = (string) $request->input('recurrence_enabled', '') === '1';
        $intervalRaw = trim((string) $request->input('recurrence_interval', ''));
        $unitRaw = trim((string) $request->input('recurrence_unit', ''));
        $reminderRaw = trim((string) $request->input('reminder_lead_days', '0'));

        $interval = null;
        $unit = null;
        $reminder = 0;

        if ($recurrenceEnabled) {
            if ($intervalRaw !== '' && ctype_digit($intervalRaw)) {
                $interval = (int) $intervalRaw;
            }
            if ($unitRaw !== '') {
                $unit = $unitRaw;
            }
            if ($reminderRaw !== '' && ctype_digit($reminderRaw)) {
                $reminder = (int) $reminderRaw;
            } elseif ($reminderRaw === '0') {
                $reminder = 0;
            } else {
                $reminder = -1; // signal invalid in validate
            }
        }

        $existingById = [];
        if ($typeId !== null) {
            foreach ($this->types->fieldsForType($typeId, true) as $row) {
                $existingById[(int) $row['id']] = $row;
            }
        }

        $rawFields = $request->input('fields', []);
        if (!is_array($rawFields)) {
            $rawFields = [];
        }

        $fields = [];
        foreach ($rawFields as $row) {
            if (!is_array($row)) {
                continue;
            }

            $fieldName = trim((string) ($row['name'] ?? ''));
            if ($fieldName === '') {
                // Skip blank rows entirely (UI may leave an empty draft).
                continue;
            }

            $fieldIdRaw = trim((string) ($row['id'] ?? ''));
            $fieldId = ($fieldIdRaw !== '' && ctype_digit($fieldIdRaw)) ? (int) $fieldIdRaw : null;
            if ($fieldId !== null && !isset($existingById[$fieldId])) {
                $fieldId = null;
            }

            $fieldKey = null;
            if ($fieldId !== null) {
                $fieldKey = (string) $existingById[$fieldId]['field_key'];
            }

            $fieldType = trim((string) ($row['field_type'] ?? 'text'));
            $isRequired = isset($row['is_required']) && (string) $row['is_required'] !== '0' && (string) $row['is_required'] !== '';

            $fields[] = [
                'id' => $fieldId,
                'field_key' => $fieldKey,
                'name' => $fieldName,
                'field_type' => $fieldType,
                'is_required' => $isRequired,
            ];
        }

        return [
            'type' => [
                'name' => $name,
                'description' => $description,
                'icon' => $icon,
                'color' => $color,
                'recurrence_interval' => $interval,
                'recurrence_unit' => $unit,
                'reminder_lead_days' => max(0, $reminder),
            ],
            'fields' => $fields,
            'recurrence_enabled' => $recurrenceEnabled,
            'name' => $name,
            'description' => (string) ($description ?? ''),
            'icon' => $icon,
            'color' => $color,
            'recurrence_interval' => $intervalRaw,
            'recurrence_unit' => $unitRaw,
            'reminder_lead_days' => $reminderRaw === '' ? '0' : $reminderRaw,
            '_reminder_invalid' => $recurrenceEnabled && $reminder < 0,
        ];
    }

    /**
     * @param array<string, mixed> $parsed
     * @return array<string, string>
     */
    private function validate(array $parsed, ?int $exceptId = null): array
    {
        $errors = [];
        $data = $parsed['type'];

        if ($data['name'] === '') {
            $errors['name'] = 'Please enter a name.';
        } elseif (mb_strlen($data['name']) > 120) {
            $errors['name'] = 'Name should be 120 characters or fewer.';
        } elseif ($this->types->activeNameExists($data['name'], $exceptId)) {
            $errors['name'] = 'An active event type already uses this name.';
        }

        if ($data['description'] !== null && mb_strlen($data['description']) > 2000) {
            $errors['description'] = 'Description is a bit too long.';
        }

        if ($data['icon'] === '' || !EventTypeCatalog::isAllowedIcon($data['icon'])) {
            $errors['icon'] = 'Please choose an icon from the list.';
        }

        if (!EventTypeCatalog::isValidHexColor($data['color'])) {
            $errors['color'] = 'Please choose a valid color.';
        }

        $enabled = !empty($parsed['recurrence_enabled']);
        if ($enabled) {
            $interval = $data['recurrence_interval'];
            $unit = $data['recurrence_unit'];

            if ($interval === null || $interval < 1) {
                $errors['recurrence_interval'] = 'Enter how often this repeats (1 or more).';
            } elseif ($interval > 999) {
                $errors['recurrence_interval'] = 'That interval is a bit large.';
            }

            if ($unit === null || !in_array($unit, EventTypeCatalog::RECURRENCE_UNITS, true)) {
                $errors['recurrence_unit'] = 'Choose days, weeks, months, or years.';
            }

            if (!empty($parsed['_reminder_invalid'])) {
                $errors['reminder_lead_days'] = 'Enter 0 or a positive number of days.';
            } elseif ($data['reminder_lead_days'] > 365) {
                $errors['reminder_lead_days'] = 'Reminder lead time should be 365 days or fewer.';
            }
        }

        $seenNames = [];
        foreach ($parsed['fields'] as $i => $field) {
            $prefix = 'fields.' . $i;
            if (mb_strlen($field['name']) > 120) {
                $errors[$prefix . '.name'] = 'Field name should be 120 characters or fewer.';
            }
            $nameKey = mb_strtolower($field['name']);
            if (isset($seenNames[$nameKey])) {
                $errors[$prefix . '.name'] = 'Field names should be unique within this type.';
            }
            $seenNames[$nameKey] = true;

            if (!in_array($field['field_type'], EventTypeCatalog::FIELD_TYPES, true)) {
                $errors[$prefix . '.field_type'] = 'Choose a valid field type.';
            }
        }

        if (count($parsed['fields']) > 40) {
            $errors['fields'] = 'Please keep custom fields to 40 or fewer.';
        }

        return $errors;
    }
}
