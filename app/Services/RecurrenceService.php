<?php

declare(strict_types=1);

namespace PawBerry\Services;

use DateTimeImmutable;
use PawBerry\Core\Database;
use PawBerry\Repositories\EventRepository;
use PawBerry\Repositories\EventTypeRepository;
use PawBerry\Repositories\NotificationRepository;
use PawBerry\Support\EventTypePresenter;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Each event owns its own reminder. Events that share a type stay independent.
 * An event type's schedule is the default for new events. When the user applies
 * a type change to existing events, every event of that type is updated and its
 * reminder follows that event's new schedule.
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

            $this->syncEvent($eventId, $pdo, true, false);
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
     * Refresh one event's reminder after it is saved.
     * A schedule change (including the event date) replaces a disabled or canceled reminder.
     * Saving unrelated fields leaves a reminder the user disabled as it is.
     *
     * @param array<string, mixed> $before
     */
    public function syncAfterEventUpdated(int $eventId, array $before): void
    {
        $after = $this->events->find($eventId);
        if ($after === null) {
            return;
        }

        $changed = $this->scheduleSignature($before) !== $this->scheduleSignature($after);
        $this->syncEvent($eventId, null, $changed, false);
    }

    /**
     * Copy a type's repeat schedule onto every event of that type, then refresh
     * each event's own reminder.
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
            foreach ($this->events->idsForType($eventTypeId) as $eventId) {
                $this->syncEvent($eventId, $pdo, true, true);
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

    private function syncEvent(int $eventId, ?PDO $pdo, bool $reactivate, bool $fromCascade): void
    {
        $pdo ??= Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $event = $this->events->find($eventId);
            if ($event === null) {
                if ($ownsTransaction) {
                    $pdo->commit();
                }
                return;
            }

            $existing = $this->notifications->latestForSource($eventId, $pdo);
            if (!EventTypePresenter::isRecurring($event)) {
                if (
                    $existing !== null
                    && in_array((string) $existing['status'], ['active', 'disabled'], true)
                ) {
                    $this->notifications->markCanceled((int) $existing['id'], $pdo);
                }
            } else {
                $this->syncRecurringEvent($event, $existing, $pdo, $reactivate, $fromCascade);
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
     * @param array<string, mixed> $event
     * @param array<string, mixed>|null $existing
     */
    private function syncRecurringEvent(
        array $event,
        ?array $existing,
        PDO $pdo,
        bool $reactivate,
        bool $fromCascade
    ): void {
        $interval = (int) $event['recurrence_interval'];
        $unit = (string) $event['recurrence_unit'];
        $leadDays = (int) ($event['reminder_lead_days'] ?? 0);
        $dueDate = $this->calculator->nextDueDate((string) $event['event_date'], $interval, $unit);
        $inWindow = $this->dueIsCurrent($dueDate);
        $status = $existing === null ? null : (string) $existing['status'];

        if ($fromCascade && !$inWindow) {
            if ($status === 'active') {
                $this->notifications->deleteActiveForSource((int) $event['id'], $pdo);
            }
            return;
        }

        if ($fromCascade && $status === 'completed') {
            return;
        }

        if (
            !$fromCascade
            && !$reactivate
            && $existing !== null
            && in_array($status, ['disabled', 'canceled', 'completed'], true)
        ) {
            return;
        }

        $this->notifications->upsertActive([
            'source_event_id' => (int) $event['id'],
            'due_date' => $dueDate,
            'reminder_start_date' => $this->calculator->reminderStartDate($dueDate, $leadDays),
            'recurrence_interval' => $interval,
            'recurrence_unit' => $unit,
            'reminder_lead_days' => $leadDays,
        ], $pdo);
    }

    /** @param array<string, mixed> $event */
    private function scheduleSignature(array $event): string
    {
        $date = (string) ($event['event_date'] ?? '');
        if (!EventTypePresenter::isRecurring($event)) {
            return 'off|' . $date;
        }

        return implode('|', [
            (int) $event['recurrence_interval'],
            (string) $event['recurrence_unit'],
            (int) ($event['reminder_lead_days'] ?? 0),
            $date,
        ]);
    }

    private function dueIsCurrent(string $dueDate): bool
    {
        return $dueDate >= (new DateTimeImmutable('today'))->format('Y-m-d');
    }
}
