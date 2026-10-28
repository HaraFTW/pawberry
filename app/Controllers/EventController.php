<?php

declare(strict_types=1);

namespace PawBerry\Controllers;

use PawBerry\Core\Request;
use PawBerry\Core\Response;
use PawBerry\Core\Session;
use PawBerry\Core\Url;
use PawBerry\Core\View;
use PawBerry\Repositories\EventRepository;
use PawBerry\Repositories\EventTypeRepository;
use PawBerry\Repositories\MemberRepository;
use PawBerry\Repositories\NotificationRepository;
use PawBerry\Services\EventAttachmentService;
use PawBerry\Services\RecurrenceService;
use PawBerry\Support\EventTypeCatalog;
use RuntimeException;
use Throwable;

final class EventController
{
    private EventRepository $events;
    private EventTypeRepository $types;
    private MemberRepository $members;
    private EventAttachmentService $attachments;
    private RecurrenceService $recurrence;
    private NotificationRepository $notifications;

    public function __construct()
    {
        $this->events = new EventRepository();
        $this->types = new EventTypeRepository();
        $this->members = new MemberRepository();
        $this->attachments = new EventAttachmentService();
        $this->recurrence = new RecurrenceService();
        $this->notifications = new NotificationRepository();
    }

    public function index(Request $request): Response
    {
        $memberIds = $this->parseMemberFilterIds($request->query('member', null));
        $typeId = (int) $request->query('type', 0);
        $page = (int) $request->query('page', 1);

        $validMemberIds = [];
        foreach ($memberIds as $memberId) {
            if ($this->members->find($memberId) !== null) {
                $validMemberIds[] = $memberId;
            }
        }
        if ($typeId > 0 && $this->types->find($typeId) === null) {
            $typeId = 0;
        }

        $result = $this->events->paginate([
            'member_ids' => $validMemberIds,
            'event_type_id' => $typeId > 0 ? $typeId : null,
            'page' => $page,
        ]);

        $filtersActive = $validMemberIds !== [] || $typeId > 0;

        return View::render('events/index', [
            'title' => 'Events',
            'nav' => 'events',
            'events' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'filterMemberIds' => $validMemberIds,
            'filterTypeId' => $typeId,
            'filtersActive' => $filtersActive,
            'filterMembers' => $this->members->allActive(),
            'filterTypes' => $this->types->allActive(),
            'allMembersForFilter' => $this->membersForFilter($validMemberIds),
            'allTypesForFilter' => $this->typesForFilter($typeId),
        ]);
    }

    public function show(Request $request): Response
    {
        $event = $this->requireEvent($request);
        if ($event instanceof Response) {
            return $event;
        }

        $id = (int) $event['id'];
        return View::render('events/show', [
            'title' => \PawBerry\Support\EventPresenter::displayTitle($event),
            'nav' => 'events',
            'event' => $event,
            'fieldValues' => $this->events->fieldValues($id),
            'attachments' => $this->events->attachments($id),
            'deletionImpact' => $this->recurrence->deletionImpact($id),
        ]);
    }

    public function createForm(Request $request): Response
    {
        $preselectMember = (int) $request->query('member', 0);
        $fromNotification = (int) $request->query('from_notification', 0);
        $old = [];
        $completingNotification = null;

        if ($fromNotification > 0) {
            $notification = $this->notifications->find($fromNotification);
            if ($notification === null || (string) $notification['status'] !== 'active') {
                Session::flash('error', 'That reminder is no longer active.');
                return Response::redirect(Url::to('/notifications'));
            }
            $source = $this->events->find((int) $notification['source_event_id']);
            if ($source === null) {
                Session::flash('error', 'The source event for that reminder could not be found.');
                return Response::redirect(Url::to('/notifications'));
            }
            $old = $this->prefillFromSourceEvent($source);
            $old['completes_notification_id'] = $fromNotification;
            $completingNotification = $notification;
        } elseif ($preselectMember > 0 && $this->members->find($preselectMember) !== null) {
            $old['member_ids'] = [$preselectMember];
        }

        // Optional calendar date preselect — never allow future dates.
        if ($fromNotification < 1) {
            $dateParam = trim((string) $request->query('date', ''));
            if ($dateParam !== '') {
                $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateParam);
                $today = new \DateTimeImmutable('today');
                if ($dt && $dt->format('Y-m-d') === $dateParam && $dt <= $today) {
                    $old['event_date'] = $dateParam;
                    if (!isset($old['event'])) {
                        $old['event'] = [];
                    }
                    if (is_array($old['event'])) {
                        $old['event']['event_date'] = $dateParam;
                    }
                }
            }
        }

        return View::render('events/form', $this->formData('create', null, [], $old, [], [], $completingNotification));
    }

    public function store(Request $request): Response
    {
        $parsed = $this->parseInput($request);
        $completesId = (int) ($parsed['completes_notification_id'] ?? 0);
        $errors = $this->validate($parsed);

        if ($completesId > 0) {
            $notification = $this->notifications->find($completesId);
            if ($notification === null || (string) $notification['status'] !== 'active') {
                $errors['completes_notification_id'] = 'That reminder is no longer active.';
            }
        }

        $uploadResult = $this->collectUploads($request, 0, $errors);
        if ($errors !== []) {
            $completing = $completesId > 0 ? $this->notifications->find($completesId) : null;
            return View::render('events/form', $this->formData('create', null, $errors, $parsed, [], [], $completing));
        }

        try {
            $result = $this->recurrence->createEventWithOptionalCompletion(
                $parsed['event'],
                $parsed['member_ids'],
                $parsed['field_values'],
                $completesId > 0 ? $completesId : null
            );
            $id = $result['event_id'];
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $completing = $completesId > 0 ? $this->notifications->find($completesId) : null;
            return View::render('events/form', $this->formData(
                'create',
                null,
                ['form' => $e->getMessage()],
                $parsed,
                [],
                [],
                $completing
            ));
        } catch (Throwable) {
            Session::flash('error', 'The event could not be saved. Please try again.');
            $completing = $completesId > 0 ? $this->notifications->find($completesId) : null;
            return View::render('events/form', $this->formData('create', null, ['form' => 'Could not save.'], $parsed, [], [], $completing));
        }

        $storedPaths = [];
        try {
            $rows = [];
            foreach ($uploadResult as $upload) {
                $row = $this->attachments->store($id, $upload);
                $storedPaths[] = $row['stored_path'];
                $rows[] = $row;
            }
            $this->events->addAttachments($id, $rows);
        } catch (Throwable) {
            foreach ($storedPaths as $path) {
                $this->attachments->deleteRelative($path);
            }
            Session::flash('error', 'The event was saved, but attachments could not be stored. You can add them from Edit.');
            return Response::redirect(Url::to('/events/' . $id . '/edit'));
        }

        if ($completesId > 0) {
            Session::flash('success', 'Completion recorded. Next reminder scheduled.');
            Session::flash('sound', 'complete');
        } else {
            Session::flash('success', 'Event recorded.');
            Session::flash('sound', 'success');
        }
        return Response::redirect(Url::to('/events/' . $id));
    }

    public function editForm(Request $request): Response
    {
        $event = $this->requireEvent($request);
        if ($event instanceof Response) {
            return $event;
        }

        $id = (int) $event['id'];
        return View::render('events/form', $this->formData(
            'edit',
            $event,
            [],
            [],
            $this->events->fieldValues($id),
            $this->events->attachments($id)
        ));
    }

    public function update(Request $request): Response
    {
        $event = $this->requireEvent($request);
        if ($event instanceof Response) {
            return $event;
        }

        $id = (int) $event['id'];
        $existingAttachments = $this->events->attachments($id);
        $existingValues = $this->events->fieldValues($id);

        $parsed = $this->parseInput($request, $event, $existingValues);
        $errors = $this->validate($parsed, $event);

        $removeIds = $parsed['remove_attachment_ids'];
        $remainingCount = count($existingAttachments) - count($removeIds);
        $uploadResult = $this->collectUploads($request, max(0, $remainingCount), $errors);

        if ($errors !== []) {
            return View::render('events/form', $this->formData(
                'edit',
                $event,
                $errors,
                $parsed,
                $existingValues,
                $existingAttachments
            ));
        }

        $typeChanged = (int) $parsed['event']['event_type_id'] !== (int) $event['event_type_id'];
        $previousTypeId = (int) $event['event_type_id'];
        $previousMemberIds = [];
        foreach ($event['members'] ?? [] as $m) {
            $previousMemberIds[] = (int) $m['id'];
        }

        $pathsToDelete = [];
        foreach ($existingAttachments as $att) {
            if (in_array((int) $att['id'], $removeIds, true)) {
                $pathsToDelete[] = (string) $att['stored_path'];
            }
        }

        $storedPaths = [];
        $newRows = [];
        try {
            foreach ($uploadResult as $upload) {
                $row = $this->attachments->store($id, $upload);
                $storedPaths[] = $row['stored_path'];
                $newRows[] = $row;
            }

            $this->events->update(
                $id,
                $parsed['event'],
                $parsed['member_ids'],
                $parsed['field_values'],
                $newRows,
                $removeIds,
                $typeChanged
            );

            $this->recurrence->syncAfterEventUpdated(
                (int) $parsed['event']['event_type_id'],
                $parsed['member_ids'],
                $previousTypeId,
                $previousMemberIds
            );

            foreach ($pathsToDelete as $path) {
                $this->attachments->deleteRelative($path);
            }
        } catch (Throwable) {
            foreach ($storedPaths as $path) {
                $this->attachments->deleteRelative($path);
            }
            Session::flash('error', 'The event could not be updated. Please try again.');
            return View::render('events/form', $this->formData(
                'edit',
                $event,
                ['form' => 'Could not save.'],
                $parsed,
                $existingValues,
                $existingAttachments
            ));
        }

        Session::flash('success', 'Event updated.');
        return Response::redirect(Url::to('/events/' . $id));
    }

    public function destroy(Request $request): Response
    {
        $event = $this->requireEvent($request);
        if ($event instanceof Response) {
            return $event;
        }

        $id = (int) $event['id'];
        $typeId = (int) $event['event_type_id'];
        $memberIds = [];
        foreach ($event['members'] ?? [] as $m) {
            $memberIds[] = (int) $m['id'];
        }

        $confirmHistory = (string) $request->input('confirm_history_delete', '') === '1';
        $impact = $this->recurrence->deletionImpact($id);

        if ($impact['needs_history_confirm'] && !$confirmHistory) {
            Session::flash(
                'error',
                'This event is linked to notification history (' . $impact['history_count']
                . '). Delete again with confirmation to remove those history records as well.'
            );
            return Response::redirect(Url::to('/events/' . $id));
        }

        try {
            $this->recurrence->prepareEventDeletion($id, $confirmHistory);
            $removed = $this->events->delete($id);
            $this->recurrence->syncAfterEventDeleted($typeId, $memberIds);
            foreach ($removed as $att) {
                $this->attachments->deleteRelative((string) $att['stored_path']);
            }
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            return Response::redirect(Url::to('/events/' . $id));
        } catch (Throwable) {
            Session::flash('error', 'The event could not be deleted.');
            return Response::redirect(Url::to('/events/' . $id));
        }

        Session::flash('success', 'Event deleted.');
        return Response::redirect(Url::to('/events'));
    }

    public function attachment(Request $request): Response
    {
        $event = $this->requireEvent($request);
        if ($event instanceof Response) {
            return $event;
        }

        $attachmentId = (int) $request->param('attachmentId', 0);
        $att = $this->events->findAttachment((int) $event['id'], $attachmentId);
        if ($att === null) {
            return View::error(404, 'Attachment not found', 'That file could not be found.');
        }

        try {
            $absolute = $this->attachments->absolutePath((string) $att['stored_path']);
        } catch (RuntimeException) {
            return View::error(404, 'Attachment not found', 'That file could not be found.');
        }
        if (!is_file($absolute)) {
            return View::error(404, 'Attachment not found', 'That file could not be found.');
        }

        $mime = (string) $att['mime_type'];
        $filename = (string) $att['original_filename'];
        $disposition = str_starts_with($mime, 'image/') || $mime === 'application/pdf'
            ? 'inline'
            : 'attachment';

        $safeName = str_replace(['"', "\r", "\n"], '', $filename);

        return Response::file($absolute, $mime)
            ->withHeader('Content-Disposition', $disposition . '; filename="' . $safeName . '"');
    }

    /** @return array<string, mixed>|Response */
    private function requireEvent(Request $request): array|Response
    {
        $id = (int) $request->param('id', 0);
        if ($id < 1) {
            return View::error(404, 'Event not found', 'That event could not be found.');
        }
        $event = $this->events->find($id);
        if ($event === null) {
            return View::error(404, 'Event not found', 'That event could not be found.');
        }
        return $event;
    }

    /**
     * Prefill create form from a source event (This just happened). Does not copy cost or attachments.
     *
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function prefillFromSourceEvent(array $source): array
    {
        $memberIds = [];
        foreach ($source['members'] ?? [] as $m) {
            $memberIds[] = (int) $m['id'];
        }

        $fieldValues = $this->events->fieldValues((int) $source['id']);
        $fields = [];
        foreach ($fieldValues as $row) {
            if (!empty($row['field_deleted_at'])) {
                continue;
            }
            $key = (string) $row['field_key'];
            $fields[$key] = $row['value_text'] !== null ? (string) $row['value_text'] : '';
        }

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        return [
            'event' => [
                'event_type_id' => (int) $source['event_type_id'],
                'event_date' => $today,
                'title' => $source['title'] !== null ? (string) $source['title'] : null,
                'notes' => $source['notes'] !== null ? (string) $source['notes'] : null,
                'cost' => null,
            ],
            'member_ids' => $memberIds,
            'event_type_id' => (int) $source['event_type_id'],
            'event_date' => $today,
            'title' => (string) ($source['title'] ?? ''),
            'notes' => (string) ($source['notes'] ?? ''),
            'cost' => '',
            'fields' => $fields,
        ];
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     * @param list<array<string, mixed>> $existingValues
     * @param list<array<string, mixed>> $existingAttachments
     * @param array<string, mixed>|null $completingNotification
     * @return array<string, mixed>
     */
    private function formData(
        string $mode,
        ?array $event,
        array $errors,
        array $old,
        array $existingValues = [],
        array $existingAttachments = [],
        ?array $completingNotification = null
    ): array {
        $activeTypes = $this->types->allActive();
        $activeMembers = $this->members->allActive();

        $selectedTypeId = null;
        if (isset($old['event']['event_type_id'])) {
            $selectedTypeId = (int) $old['event']['event_type_id'];
        } elseif (isset($old['event_type_id'])) {
            $selectedTypeId = (int) $old['event_type_id'];
        } elseif ($event !== null) {
            $selectedTypeId = (int) $event['event_type_id'];
        }

        $typesForSelect = $activeTypes;
        if ($selectedTypeId) {
            $selectedType = $this->types->find($selectedTypeId);
            if ($selectedType !== null && !empty($selectedType['archived_at'])) {
                $typesForSelect[] = $selectedType;
            }
        }

        $memberIds = [];
        if (isset($old['member_ids']) && is_array($old['member_ids'])) {
            $memberIds = array_map('intval', $old['member_ids']);
        } elseif ($event !== null) {
            foreach ($event['members'] ?? [] as $m) {
                $memberIds[] = (int) $m['id'];
            }
        }

        $membersForSelect = $activeMembers;
        foreach ($memberIds as $mid) {
            $found = false;
            foreach ($membersForSelect as $m) {
                if ((int) $m['id'] === $mid) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $archived = $this->members->find($mid);
                if ($archived !== null) {
                    $membersForSelect[] = $archived;
                }
            }
        }

        $fieldsByType = [];
        foreach ($typesForSelect as $type) {
            $fieldsByType[(int) $type['id']] = $this->types->fieldsForType((int) $type['id']);
        }

        $completesNotificationId = 0;
        if (isset($old['completes_notification_id'])) {
            $completesNotificationId = (int) $old['completes_notification_id'];
        }

        // Prefill custom fields into existingValues shape for create-from-notification.
        if ($mode === 'create' && $existingValues === [] && isset($old['fields']) && is_array($old['fields']) && $selectedTypeId) {
            $defs = $fieldsByType[$selectedTypeId] ?? [];
            foreach ($defs as $def) {
                $key = (string) $def['field_key'];
                if (!array_key_exists($key, $old['fields'])) {
                    continue;
                }
                $existingValues[] = [
                    'event_type_field_id' => (int) $def['id'],
                    'field_key' => $key,
                    'field_name' => (string) $def['name'],
                    'field_type' => (string) $def['field_type'],
                    'value_text' => $old['fields'][$key] === '' ? null : (string) $old['fields'][$key],
                ];
            }
        }

        return [
            'title' => $mode === 'edit' ? 'Edit Event' : ($completingNotification ? 'Record completion' : 'Add Event'),
            'nav' => 'events',
            'mode' => $mode,
            'event' => $event,
            'errors' => $errors,
            'old' => $old,
            'types' => $typesForSelect,
            'members' => $membersForSelect,
            'fieldsByType' => $fieldsByType,
            'existingValues' => $existingValues,
            'existingAttachments' => $existingAttachments,
            'selectedTypeId' => $selectedTypeId,
            'selectedMemberIds' => $memberIds,
            'today' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'completesNotificationId' => $completesNotificationId,
            'completingNotification' => $completingNotification,
        ];
    }

    /**
     * @param list<array<string, mixed>> $existingValues
     * @return array<string, mixed>
     */
    private function parseInput(Request $request, ?array $existingEvent = null, array $existingValues = []): array
    {
        $nullIfEmpty = static function (string $value): ?string {
            $value = trim($value);
            return $value === '' ? null : $value;
        };

        $typeId = (int) $request->input('event_type_id', 0);
        $date = trim((string) $request->input('event_date', ''));
        $title = $nullIfEmpty((string) $request->input('title', ''));
        $notes = $nullIfEmpty((string) $request->input('notes', ''));
        $costRaw = trim((string) $request->input('cost', ''));
        $confirmTypeChange = (string) $request->input('confirm_type_change', '') === '1';

        $memberRaw = $request->input('member_ids', []);
        if (!is_array($memberRaw)) {
            $memberRaw = [];
        }
        $memberIds = [];
        foreach ($memberRaw as $mid) {
            $id = (int) $mid;
            if ($id > 0) {
                $memberIds[$id] = $id;
            }
        }
        $memberIds = array_values($memberIds);

        $removeRaw = $request->input('remove_attachments', []);
        if (!is_array($removeRaw)) {
            $removeRaw = [];
        }
        $removeIds = [];
        foreach ($removeRaw as $rid) {
            $id = (int) $rid;
            if ($id > 0) {
                $removeIds[] = $id;
            }
        }

        $cost = null;
        if ($costRaw !== '') {
            $cost = $costRaw;
        }

        $typeChanged = $existingEvent !== null
            && $typeId > 0
            && $typeId !== (int) $existingEvent['event_type_id'];

        $fieldDefs = $typeId > 0 ? $this->types->fieldsForType($typeId) : [];
        $rawFields = $request->input('fields', []);
        if (!is_array($rawFields)) {
            $rawFields = [];
        }

        $fieldValues = [];
        foreach ($fieldDefs as $def) {
            $fid = (int) $def['id'];
            $key = (string) $def['field_key'];
            $ftype = (string) $def['field_type'];
            $submitted = $rawFields[$key] ?? $rawFields[(string) $fid] ?? null;
            $valueText = $this->normalizeFieldValue($ftype, $submitted);
            $fieldValues[] = [
                'event_type_field_id' => $fid,
                'field_key' => $key,
                'field_name' => (string) $def['name'],
                'field_type' => $ftype,
                'value_text' => $valueText,
                'is_required' => !empty($def['is_required']),
            ];
        }

        // Preserve historical soft-deleted field values when type unchanged.
        if ($existingEvent !== null && !$typeChanged) {
            $activeIds = array_map(static fn (array $d): int => (int) $d['id'], $fieldDefs);
            foreach ($existingValues as $existing) {
                $fid = (int) $existing['event_type_field_id'];
                if (in_array($fid, $activeIds, true)) {
                    continue;
                }
                if (empty($existing['field_deleted_at'])) {
                    continue;
                }
                $key = (string) $existing['field_key'];
                $ftype = (string) $existing['field_type'];
                $submitted = $rawFields[$key] ?? $rawFields[(string) $fid] ?? null;
                if ($submitted === null && !array_key_exists($key, $rawFields) && !array_key_exists((string) $fid, $rawFields)) {
                    // Keep prior value untouched when not present in POST.
                    $fieldValues[] = [
                        'event_type_field_id' => $fid,
                        'field_key' => $key,
                        'field_name' => (string) $existing['field_name'],
                        'field_type' => $ftype,
                        'value_text' => $existing['value_text'] !== null ? (string) $existing['value_text'] : null,
                        'is_required' => false,
                        'historical' => true,
                    ];
                    continue;
                }
                $fieldValues[] = [
                    'event_type_field_id' => $fid,
                    'field_key' => $key,
                    'field_name' => (string) $existing['field_name'],
                    'field_type' => $ftype,
                    'value_text' => $this->normalizeFieldValue($ftype, $submitted),
                    'is_required' => false,
                    'historical' => true,
                ];
            }
        }

        $completesNotificationId = (int) $request->input('completes_notification_id', 0);

        return [
            'event' => [
                'event_type_id' => $typeId,
                'event_date' => $date,
                'title' => $title,
                'notes' => $notes,
                'cost' => $cost,
            ],
            'member_ids' => $memberIds,
            'field_values' => $fieldValues,
            'remove_attachment_ids' => $removeIds,
            'confirm_type_change' => $confirmTypeChange,
            'type_changed' => $typeChanged,
            'completes_notification_id' => $completesNotificationId,
            // flat mirrors for form redisplay
            'event_type_id' => $typeId,
            'event_date' => $date,
            'title' => (string) ($title ?? ''),
            'notes' => (string) ($notes ?? ''),
            'cost' => $costRaw,
            'fields' => $rawFields,
        ];
    }

    private function normalizeFieldValue(string $type, mixed $submitted): ?string
    {
        if ($type === 'checkbox') {
            if (is_array($submitted)) {
                $submitted = reset($submitted);
            }
            return ($submitted === '1' || $submitted === 1 || $submitted === true || $submitted === 'on') ? '1' : '0';
        }

        if ($submitted === null) {
            return null;
        }
        if (is_array($submitted)) {
            return null;
        }
        $value = trim((string) $submitted);
        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $parsed
     * @return array<string, string>
     */
    private function validate(array $parsed, ?array $existingEvent = null): array
    {
        $errors = [];
        $data = $parsed['event'];

        $type = $data['event_type_id'] > 0 ? $this->types->find($data['event_type_id']) : null;
        if ($type === null) {
            $errors['event_type_id'] = 'Please choose an event type.';
        } elseif (!empty($type['archived_at'])) {
            $okArchived = $existingEvent !== null
                && (int) $existingEvent['event_type_id'] === (int) $type['id'];
            if (!$okArchived) {
                $errors['event_type_id'] = 'Please choose an active event type.';
            }
        }

        if ($data['event_date'] === '') {
            $errors['event_date'] = 'Please choose a date.';
        } else {
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $data['event_date']);
            $valid = $dt && $dt->format('Y-m-d') === $data['event_date'];
            if (!$valid) {
                $errors['event_date'] = 'Please enter a valid date.';
            } elseif ($dt > new \DateTimeImmutable('today')) {
                $errors['event_date'] = 'Events record things that already happened — please use today or a past date.';
            }
        }

        if ($data['title'] !== null && mb_strlen($data['title']) > 160) {
            $errors['title'] = 'Title should be 160 characters or fewer.';
        }
        if ($data['notes'] !== null && mb_strlen($data['notes']) > 10000) {
            $errors['notes'] = 'Notes are a bit too long.';
        }

        if ($data['cost'] !== null) {
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $data['cost'])) {
                $errors['cost'] = 'Enter a valid cost with up to two decimal places.';
            } elseif ((float) $data['cost'] < 0) {
                $errors['cost'] = 'Cost cannot be negative.';
            } elseif ((float) $data['cost'] > 99999999.99) {
                $errors['cost'] = 'That cost is a bit large.';
            }
        }

        if ($parsed['member_ids'] === []) {
            $errors['member_ids'] = 'Please choose at least one member.';
        } else {
            foreach ($parsed['member_ids'] as $mid) {
                $member = $this->members->find($mid);
                if ($member === null) {
                    $errors['member_ids'] = 'One of the selected members could not be found.';
                    break;
                }
                if (!empty($member['archived_at'])) {
                    $allowed = false;
                    if ($existingEvent !== null) {
                        foreach ($existingEvent['members'] ?? [] as $em) {
                            if ((int) $em['id'] === $mid) {
                                $allowed = true;
                                break;
                            }
                        }
                    }
                    if (!$allowed) {
                        $errors['member_ids'] = 'Archived members can only stay on events they already belong to.';
                        break;
                    }
                }
            }
        }

        if (!empty($parsed['type_changed']) && empty($parsed['confirm_type_change'])) {
            $errors['confirm_type_change'] = 'Confirm that custom field values from the previous type may be replaced.';
        }

        foreach ($parsed['field_values'] as $field) {
            if (!empty($field['historical'])) {
                continue;
            }
            $key = 'fields.' . $field['field_key'];
            $val = $field['value_text'];
            $required = !empty($field['is_required']);
            $ftype = $field['field_type'];

            if ($ftype === 'checkbox') {
                if ($required && $val !== '1') {
                    $errors[$key] = 'Please confirm ' . $field['field_name'] . '.';
                }
                continue;
            }

            if ($required && ($val === null || $val === '')) {
                $errors[$key] = 'Please fill in ' . $field['field_name'] . '.';
                continue;
            }
            if ($val === null || $val === '') {
                continue;
            }

            if ($ftype === 'number' && !is_numeric($val)) {
                $errors[$key] = 'Enter a number for ' . $field['field_name'] . '.';
            } elseif ($ftype === 'date') {
                $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $val);
                if (!$dt || $dt->format('Y-m-d') !== $val) {
                    $errors[$key] = 'Enter a valid date for ' . $field['field_name'] . '.';
                }
            } elseif ($ftype === 'rating') {
                if (!ctype_digit($val) || (int) $val < 1 || (int) $val > 5) {
                    $errors[$key] = 'Choose a rating from 1 to 5 for ' . $field['field_name'] . '.';
                }
            } elseif ($ftype === 'text' && mb_strlen($val) > 2000) {
                $errors[$key] = $field['field_name'] . ' is a bit too long.';
            }

            if (!in_array($ftype, EventTypeCatalog::FIELD_TYPES, true)) {
                $errors[$key] = 'Invalid field type.';
            }
        }

        return $errors;
    }

    /**
     * @param array<string, string> $errors
     * @return list<array{mime: string, extension: string, tmp: string, size: int, original_filename: string}>
     */
    private function collectUploads(Request $request, int $existingCount, array &$errors): array
    {
        if (!isset($_FILES['attachments'])) {
            return [];
        }

        $bag = $_FILES['attachments'];
        $files = [];
        if (is_array($bag['name'] ?? null)) {
            $count = count($bag['name']);
            for ($i = 0; $i < $count; $i++) {
                if ((int) ($bag['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $files[] = [
                    'name' => $bag['name'][$i] ?? '',
                    'type' => $bag['type'][$i] ?? '',
                    'tmp_name' => $bag['tmp_name'][$i] ?? '',
                    'error' => $bag['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $bag['size'][$i] ?? 0,
                ];
            }
        }

        if ($files === []) {
            return [];
        }

        if ($existingCount + count($files) > EventAttachmentService::MAX_PER_EVENT) {
            $errors['attachments'] = 'Please keep attachments to '
                . EventAttachmentService::MAX_PER_EVENT . ' or fewer per event.';
            return [];
        }

        $validated = [];
        foreach ($files as $i => $file) {
            try {
                $validated[] = $this->attachments->validateUpload($file);
            } catch (RuntimeException $e) {
                $errors['attachments'] = $e->getMessage();
                return [];
            }
        }

        return $validated;
    }

    /**
     * Accepts `member=123`, `member[]=1&member[]=2`, or repeated member params.
     *
     * @return list<int>
     */
    private function parseMemberFilterIds(mixed $raw): array
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
