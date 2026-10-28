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
     *   cost: ?string
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
     * Rebuild the single active notification for a recurrence group from the
     * latest matching event (if the event type still has recurrence enabled).
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
        if ($latest === null) {
            return;
        }

        $latestId = (int) $latest['id'];

        // Respect an explicit Disable: do not recreate an active reminder
        // for the same source event. A newer recorded event may still schedule.
        if ($this->notifications->hasDisabledForSource($latestId, $pdo)) {
            return;
        }

        $type = $this->types->find($eventTypeId);
        if ($type === null || !EventTypePresenter::isRecurring($type)) {
            return;
        }

        $interval = (int) $type['recurrence_interval'];
        $unit = (string) $type['recurrence_unit'];
        $leadDays = (int) ($type['reminder_lead_days'] ?? 0);
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
