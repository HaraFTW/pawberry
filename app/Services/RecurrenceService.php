<?php

declare(strict_types=1);

namespace PawBerry\Services;

use PawBerry\Core\Database;
use PawBerry\Repositories\EventRepository;
use PawBerry\Repositories\EventTypeRepository;
use PawBerry\Repositories\NotificationRepository;
use PawBerry\Support\EventTypePresenter;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Owns recurrence grouping and active-notification scheduling.
 *
 * Group key: event_type_id + exact member set.
 * Schedule anchor: most recent event in the group (event_date DESC, id DESC).
 * The anchor event's own repeat schedule decides whether a reminder is created.
 * An event type's schedule is only the default for new events, unless the user
 * chooses to copy a type change onto every existing event of that type.
 */
final class RecurrenceService
{
    private EventRepository $events;
    private EventTypeRepository $types;
    private NotificationRepository $notifications;
    private RecurrenceCalculator $calculator;

    public function __construct(
        ?EventRepository $events = null,
        ?EventTypeRepository $types = null,
        ?NotificationRepository $notifications = null,
        ?RecurrenceCalculator $calculator = null
    ) {
        $this->events = $events ?? new EventRepository();
        $this->types = $types ?? new EventTypeRepository();
        $this->notifications = $notifications ?? new NotificationRepository();
        $this->calculator = $calculator ?? new RecurrenceCalculator();
    }

    /**
     * Create an event and, when completing a notification, mark it completed in
     * the same transaction so duplicate submissions cannot double-complete.
     *
     * @param array{
     *   event_type_id: int,
     *   event_date: string,
     *   title: ?string,
     *   notes: ?string,
     *   cost: ?string,
     *   recurrence_interval?: ?int,
     *   recurrence_unit?: ?string,
     *   reminder_lead_days?: int
     * } $eventData
     * @param list<int> $memberIds
     * @param list<array<string, mixed>> $fieldValues
     * @return array{event_id: int}
     */
    public function createEventWithOptionalCompletion(
        array $eventData,
        array $memberIds,
        array $fieldValues,
        ?int $completesNotificationId = null
    ): array {
        $eventData = $this->withRecurrenceDefaults($eventData);

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if ($completesNotificationId !== null && $completesNotificationId > 0) {
                $locked = $this->notifications->lockActiveForUpdate($completesNotificationId, $pdo);
                if ($locked === null) {
                    throw new RuntimeException('That reminder was already completed or is no longer active.');
                }
            }

            $eventId = $this->events->createInTransaction($pdo, $eventData, $memberIds, $fieldValues);

            if ($completesNotificationId !== null && $completesNotificationId > 0) {
                if (!$this->notifications->markCompleted($completesNotificationId, $eventId, $pdo)) {
                    throw new RuntimeException('That reminder was already completed or is no longer active.');
                }
            }

            $this->syncGroup((int) $eventData['event_type_id'], $memberIds, $pdo);
            $pdo->commit();
            return ['event_id' => $eventId];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * After an event update, sync its group and optionally the previous group.
     *
     * @param list<int> $memberIds
     * @param list<int>|null $previousMemberIds
     */
    public function syncAfterEventUpdated(
        int $eventTypeId,
        array $memberIds,
        ?int $previousTypeId = null,
        ?array $previousMemberIds = null
    ): void {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $this->syncGroup($eventTypeId, $memberIds, $pdo);

            if (
                $previousTypeId !== null
                && $previousMemberIds !== null
                && (
                    $previousTypeId !== $eventTypeId
                    || !$this->sameMemberSet($previousMemberIds, $memberIds)
                )
            ) {
                $this->syncGroup($previousTypeId, $previousMemberIds, $pdo);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Copy a type's repeat schedule onto every event of that type and rebuild
     * each group's reminder from the latest event.
     */
    public function applyTypeRecurrenceToEvents(
        int $eventTypeId,
        ?int $interval,
        ?string $unit,
        int $leadDays
    ): void {
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $this->events->applyRecurrenceForType($eventTypeId, $interval, $unit, $leadDays);
            foreach ($this->events->memberSetsForType($eventTypeId) as $memberIds) {
                $this->syncGroup($eventTypeId, $memberIds, $pdo);
            }
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Rebuild the single active notification for a recurrence group from the
     * latest matching event's own repeat schedule.
     *
     * @param list<int> $memberIds
     */
    public function syncGroup(int $eventTypeId, array $memberIds, ?PDO $pdo = null): void
    {
        $pdo ??= Database::connection();
        $memberIds = $this->normalizeMemberIds($memberIds);

        $groupEventIds = $this->events->idsForTypeAndExactMembers($eventTypeId, $memberIds);
        if ($groupEventIds !== []) {
            $this->notifications->deleteActiveForSources($groupEventIds, $pdo);
        }

        $latest = $this->events->findLatestForTypeAndExactMembers($eventTypeId, $memberIds);
        if ($latest === null || !EventTypePresenter::isRecurring($latest)) {
            return;
        }

        $latestId = (int) $latest['id'];
        $interval = (int) $latest['recurrence_interval'];
        $unit = (string) $latest['recurrence_unit'];
        $leadDays = (int) ($latest['reminder_lead_days'] ?? 0);

        // A disable blocks the same schedule only. A changed schedule may remind again.
        $disabled = $this->notifications->latestDisabledForSource($latestId, $pdo);
        if ($disabled !== null && $this->sameSchedule($disabled, $interval, $unit, $leadDays)) {
            return;
        }

        $eventDate = (string) $latest['event_date'];
        $dueDate = $this->calculator->nextDueDate($eventDate, $interval, $unit);
        $reminderStart = $this->calculator->reminderStartDate($dueDate, $leadDays);

        $this->notifications->createActive([
            'source_event_id' => $latestId,
            'due_date' => $dueDate,
            'reminder_start_date' => $reminderStart,
            'recurrence_interval' => $interval,
            'recurrence_unit' => $unit,
            'reminder_lead_days' => $leadDays,
        ], $pdo);
    }

    /**
     * @return array{needs_history_confirm: bool, history_count: int}
     */
    public function deletionImpact(int $eventId): array
    {
        $historyCount = $this->notifications->countHistoryReferencingEvent($eventId);
        return [
            'needs_history_confirm' => $historyCount > 0,
            'history_count' => $historyCount,
        ];
    }

    /**
     * Clear notification rows that would block event deletion (FK RESTRICT on source).
     */
    public function prepareEventDeletion(int $eventId, bool $confirmHistory): void
    {
        $impact = $this->deletionImpact($eventId);
        if ($impact['needs_history_confirm'] && !$confirmHistory) {
            throw new RuntimeException(
                'This event is linked to notification history. Confirm deletion to remove those history records as well.'
            );
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $this->notifications->deleteActiveForSource($eventId, $pdo);
            if ($impact['needs_history_confirm']) {
                $this->notifications->deleteHistoryReferencingEvent($eventId, $pdo);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param list<int> $memberIds
     */
    public function syncAfterEventDeleted(int $eventTypeId, array $memberIds): void
    {
        $this->syncGroup($eventTypeId, $memberIds);
    }

    public function calculator(): RecurrenceCalculator
    {
        return $this->calculator;
    }

    /**
     * Events created without an explicit schedule copy the event type's default.
     * An explicit null interval means this event does not repeat.
     *
     * @param array<string, mixed> $eventData
     * @return array<string, mixed>
     */
    private function withRecurrenceDefaults(array $eventData): array
    {
        if (array_key_exists('recurrence_interval', $eventData)) {
            $eventData['recurrence_unit'] = $eventData['recurrence_unit'] ?? null;
            $eventData['reminder_lead_days'] = (int) ($eventData['reminder_lead_days'] ?? 0);
            return $eventData;
        }

        $type = $this->types->find((int) ($eventData['event_type_id'] ?? 0));
        if ($type !== null && EventTypePresenter::isRecurring($type)) {
            $eventData['recurrence_interval'] = (int) $type['recurrence_interval'];
            $eventData['recurrence_unit'] = (string) $type['recurrence_unit'];
            $eventData['reminder_lead_days'] = (int) ($type['reminder_lead_days'] ?? 0);
            return $eventData;
        }

        $eventData['recurrence_interval'] = null;
        $eventData['recurrence_unit'] = null;
        $eventData['reminder_lead_days'] = 0;
        return $eventData;
    }

    /** @param array<string, mixed> $notification */
    private function sameSchedule(array $notification, int $interval, string $unit, int $leadDays): bool
    {
        return (int) $notification['recurrence_interval'] === $interval
            && (string) $notification['recurrence_unit'] === $unit
            && (int) $notification['reminder_lead_days'] === $leadDays;
    }

    /** @param list<int> $ids */
    private function normalizeMemberIds(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }
        $values = array_values($normalized);
        sort($values);
        return $values;
    }

    /** @param list<int> $a @param list<int> $b */
    private function sameMemberSet(array $a, array $b): bool
    {
        return $this->normalizeMemberIds($a) === $this->normalizeMemberIds($b);
    }
}
