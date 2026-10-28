<?php

declare(strict_types=1);

namespace PawBerry\Repositories;

use PawBerry\Core\Database;
use PawBerry\Support\EventTypeCatalog;
use PawBerry\Support\NotificationPresenter;
use PDO;
use RuntimeException;

final class NotificationRepository
{
    /** App-timezone calendar date used for reminder-window comparisons. */
    private function today(): string
    {
        return NotificationPresenter::todayString();
    }

    /**
     * Notifications whose due_date falls in [from, to] inclusive (all statuses).
     *
     * @param list<int> $memberIds exact-set filter on source event members; empty = no filter
     * @return list<array<string, mixed>>
     */
    public function forDueDateRange(string $from, string $to, array $memberIds = [], ?int $eventTypeId = null): array
    {
        $where = ['n.due_date BETWEEN ? AND ?'];
        $params = [$from, $to];

        $normalized = [];
        foreach ($memberIds as $mid) {
            $id = (int) $mid;
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }
        $memberIds = array_values($normalized);

        if ($memberIds !== []) {
            $count = count($memberIds);
            $placeholders = implode(',', array_fill(0, $count, '?'));
            $where[] = '(
                (SELECT COUNT(*) FROM event_members emc WHERE emc.event_id = n.source_event_id) = ?
                AND (SELECT COUNT(*) FROM event_members emm
                     WHERE emm.event_id = n.source_event_id AND emm.member_id IN (' . $placeholders . ')) = ?
            )';
            $params[] = $count;
            foreach ($memberIds as $mid) {
                $params[] = $mid;
            }
            $params[] = $count;
        }

        if ($eventTypeId !== null && $eventTypeId > 0) {
            $where[] = 'se.event_type_id = ?';
            $params[] = $eventTypeId;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $stmt = Database::connection()->prepare(
            "SELECT n.*,
                    se.event_date AS source_event_date,
                    se.title AS source_title,
                    se.event_type_id AS source_event_type_id,
                    et.name AS type_name,
                    et.icon AS type_icon,
                    et.color AS type_color,
                    et.archived_at AS type_archived_at
             FROM notifications n
             INNER JOIN events se ON se.id = n.source_event_id
             INNER JOIN event_types et ON et.id = se.event_type_id
             {$whereSql}
             ORDER BY n.due_date ASC, n.id ASC"
        );
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        $this->hydrateMembers($items);
        return $items;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT n.*,
                    se.event_date AS source_event_date,
                    se.title AS source_title,
                    se.event_type_id AS source_event_type_id,
                    se.notes AS source_notes,
                    et.name AS type_name,
                    et.icon AS type_icon,
                    et.color AS type_color,
                    et.archived_at AS type_archived_at,
                    ce.id AS completion_event_exists
             FROM notifications n
             INNER JOIN events se ON se.id = n.source_event_id
             INNER JOIN event_types et ON et.id = se.event_type_id
             LEFT JOIN events ce ON ce.id = n.completion_event_id
             WHERE n.id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $items = [$row];
        $this->hydrateMembers($items);
        return $items[0];
    }

    /**
     * All DB-active notifications (attention + upcoming). Prefer listAttention / listUpcoming for UI.
     *
     * @return list<array<string, mixed>>
     */
    public function listActive(): array
    {
        $today = $this->today();
        $stmt = Database::connection()->prepare(
            "SELECT n.*,
                    se.event_date AS source_event_date,
                    se.title AS source_title,
                    se.event_type_id AS source_event_type_id,
                    et.name AS type_name,
                    et.icon AS type_icon,
                    et.color AS type_color,
                    et.archived_at AS type_archived_at
             FROM notifications n
             INNER JOIN events se ON se.id = n.source_event_id
             INNER JOIN event_types et ON et.id = se.event_type_id
             WHERE n.status = 'active'
             ORDER BY
                CASE
                    WHEN n.due_date < ? THEN 0
                    WHEN n.due_date = ? THEN 1
                    WHEN n.reminder_start_date <= ? THEN 2
                    ELSE 3
                END ASC,
                n.due_date ASC,
                n.id ASC"
        );
        $stmt->execute([$today, $today, $today]);
        $items = $stmt->fetchAll();
        $this->hydrateMembers($items);
        return $items;
    }

    /**
     * Active notifications that currently require attention (reminder window started).
     *
     * @return list<array<string, mixed>>
     */
    public function listAttention(): array
    {
        $today = $this->today();
        $stmt = Database::connection()->prepare(
            "SELECT n.*,
                    se.event_date AS source_event_date,
                    se.title AS source_title,
                    se.event_type_id AS source_event_type_id,
                    et.name AS type_name,
                    et.icon AS type_icon,
                    et.color AS type_color,
                    et.archived_at AS type_archived_at
             FROM notifications n
             INNER JOIN events se ON se.id = n.source_event_id
             INNER JOIN event_types et ON et.id = se.event_type_id
             WHERE n.status = 'active'
               AND n.reminder_start_date <= ?
             ORDER BY
                CASE
                    WHEN n.due_date < ? THEN 0
                    WHEN n.due_date = ? THEN 1
                    ELSE 2
                END ASC,
                n.due_date ASC,
                n.id ASC"
        );
        $stmt->execute([$today, $today, $today]);
        $items = $stmt->fetchAll();
        $this->hydrateMembers($items);
        return $items;
    }

    /**
     * Active notifications whose reminder period has not started yet.
     *
     * @return list<array<string, mixed>>
     */
    public function listUpcoming(): array
    {
        $today = $this->today();
        $stmt = Database::connection()->prepare(
            "SELECT n.*,
                    se.event_date AS source_event_date,
                    se.title AS source_title,
                    se.event_type_id AS source_event_type_id,
                    et.name AS type_name,
                    et.icon AS type_icon,
                    et.color AS type_color,
                    et.archived_at AS type_archived_at
             FROM notifications n
             INNER JOIN events se ON se.id = n.source_event_id
             INNER JOIN event_types et ON et.id = se.event_type_id
             WHERE n.status = 'active'
               AND n.reminder_start_date > ?
             ORDER BY n.due_date ASC, n.id ASC"
        );
        $stmt->execute([$today]);
        $items = $stmt->fetchAll();
        $this->hydrateMembers($items);
        return $items;
    }

    /** @return list<array<string, mixed>> */
    public function listHistory(): array
    {
        $stmt = Database::connection()->query(
            "SELECT n.*,
                    se.event_date AS source_event_date,
                    se.title AS source_title,
                    se.event_type_id AS source_event_type_id,
                    et.name AS type_name,
                    et.icon AS type_icon,
                    et.color AS type_color,
                    et.archived_at AS type_archived_at
             FROM notifications n
             INNER JOIN events se ON se.id = n.source_event_id
             INNER JOIN event_types et ON et.id = se.event_type_id
             WHERE n.status IN ('completed', 'disabled')
             ORDER BY
                COALESCE(n.completed_at, n.disabled_at, n.updated_at) DESC,
                n.id DESC"
        );
        $items = $stmt->fetchAll();
        $this->hydrateMembers($items);
        return $items;
    }

    /**
     * Active notifications whose reminder window has started (for in-app popups).
     *
     * @return list<array<string, mixed>>
     */
    public function popupCandidates(int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $today = $this->today();
        $stmt = Database::connection()->prepare(
            "SELECT n.*,
                    se.event_date AS source_event_date,
                    se.title AS source_title,
                    se.event_type_id AS source_event_type_id,
                    et.name AS type_name,
                    et.icon AS type_icon,
                    et.color AS type_color,
                    et.archived_at AS type_archived_at
             FROM notifications n
             INNER JOIN events se ON se.id = n.source_event_id
             INNER JOIN event_types et ON et.id = se.event_type_id
             WHERE n.status = 'active'
               AND n.reminder_start_date <= ?
               AND (n.popup_snoozed_through_date IS NULL OR n.popup_snoozed_through_date < ?)
             ORDER BY
                CASE
                    WHEN n.due_date < ? THEN 0
                    WHEN n.due_date = ? THEN 1
                    ELSE 2
                END ASC,
                n.due_date ASC,
                n.id ASC
             LIMIT {$limit}"
        );
        $stmt->execute([$today, $today, $today, $today]);
        $items = $stmt->fetchAll();
        $this->hydrateMembers($items);
        return $items;
    }

    /**
     * Snooze one active notification's popup through the given calendar date (usually today).
     * Idempotent if already snoozed through that date or later.
     */
    public function snoozePopupThrough(int $id, string $throughDate): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE notifications
             SET popup_snoozed_through_date = ?,
                 updated_at = NOW()
             WHERE id = ?
               AND status = 'active'
               AND (popup_snoozed_through_date IS NULL OR popup_snoozed_through_date < ?)"
        );
        $stmt->execute([$throughDate, $id, $throughDate]);
        if ($stmt->rowCount() === 1) {
            return true;
        }

        // Already snoozed through today (or later) still counts as success.
        $check = Database::connection()->prepare(
            "SELECT 1 FROM notifications
             WHERE id = ?
               AND status = 'active'
               AND popup_snoozed_through_date IS NOT NULL
               AND popup_snoozed_through_date >= ?
             LIMIT 1"
        );
        $check->execute([$id, $throughDate]);
        return $check->fetchColumn() !== false;
    }

    /**
     * Snooze every currently popup-eligible active notification through the given date.
     *
     * @return int number of rows newly snoozed
     */
    public function snoozeAllPopupEligible(string $throughDate): int
    {
        $today = $this->today();
        $stmt = Database::connection()->prepare(
            "UPDATE notifications
             SET popup_snoozed_through_date = ?,
                 updated_at = NOW()
             WHERE status = 'active'
               AND reminder_start_date <= ?
               AND (popup_snoozed_through_date IS NULL OR popup_snoozed_through_date < ?)"
        );
        $stmt->execute([$throughDate, $today, $throughDate]);
        return $stmt->rowCount();
    }

    public function countActive(): int
    {
        $stmt = Database::connection()->query(
            "SELECT COUNT(*) FROM notifications WHERE status = 'active'"
        );
        return (int) $stmt->fetchColumn();
    }

    /** Active notifications that currently require attention. */
    public function countAttention(): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM notifications
             WHERE status = 'active' AND reminder_start_date <= ?"
        );
        $stmt->execute([$this->today()]);
        return (int) $stmt->fetchColumn();
    }

    /** @deprecated Use countAttention() — kept for call-site compatibility. */
    public function countInReminderWindow(): int
    {
        return $this->countAttention();
    }

    public function countUpcoming(): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM notifications
             WHERE status = 'active' AND reminder_start_date > ?"
        );
        $stmt->execute([$this->today()]);
        return (int) $stmt->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function findActiveForSourceEvent(int $sourceEventId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM notifications WHERE source_event_id = ? AND status = 'active'"
        );
        $stmt->execute([$sourceEventId]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function findBySourceEvent(int $sourceEventId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM notifications WHERE source_event_id = ? ORDER BY id ASC'
        );
        $stmt->execute([$sourceEventId]);
        return $stmt->fetchAll();
    }

    public function countHistoryReferencingEvent(int $eventId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM notifications
             WHERE status IN ('completed', 'disabled')
               AND (source_event_id = ? OR completion_event_id = ?)"
        );
        $stmt->execute([$eventId, $eventId]);
        return (int) $stmt->fetchColumn();
    }

    public function countReferencingEvent(int $eventId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM notifications
             WHERE source_event_id = ? OR completion_event_id = ?'
        );
        $stmt->execute([$eventId, $eventId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Create the single active notification for a source event.
     * Locks the source event row and replaces any existing active row for it.
     *
     * @param array{
     *   source_event_id: int,
     *   due_date: string,
     *   reminder_start_date: string,
     *   recurrence_interval: int,
     *   recurrence_unit: string,
     *   reminder_lead_days: int
     * } $data
     */
    public function createActive(array $data, ?PDO $pdo = null): int
    {
        $sourceEventId = (int) $data['source_event_id'];
        $interval = (int) $data['recurrence_interval'];
        $unit = (string) $data['recurrence_unit'];

        if ($sourceEventId < 1) {
            throw new RuntimeException('A source event is required for an active reminder.');
        }
        if ($interval < 1) {
            throw new RuntimeException('Recurrence interval must be at least 1.');
        }
        if (!in_array($unit, EventTypeCatalog::RECURRENCE_UNITS, true)) {
            throw new RuntimeException('Recurrence unit must be days, weeks, months, or years.');
        }

        $pdo ??= Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            // Serialize writers for this source event (replaces former generated unique guard).
            $lock = $pdo->prepare('SELECT id FROM events WHERE id = ? FOR UPDATE');
            $lock->execute([$sourceEventId]);
            if ($lock->fetch() === false) {
                throw new RuntimeException('Source event not found.');
            }

            $this->deleteActiveForSource($sourceEventId, $pdo);

            $stmt = $pdo->prepare(
                'INSERT INTO notifications (
                    source_event_id, completion_event_id,
                    due_date, reminder_start_date,
                    recurrence_interval, recurrence_unit, reminder_lead_days,
                    popup_snoozed_through_date,
                    status, completed_at, disabled_at, created_at, updated_at
                 ) VALUES (?, NULL, ?, ?, ?, ?, ?, NULL, \'active\', NULL, NULL, NOW(), NOW())'
            );
            $stmt->execute([
                $sourceEventId,
                $data['due_date'],
                $data['reminder_start_date'],
                $interval,
                $unit,
                (int) $data['reminder_lead_days'],
            ]);
            $id = (int) $pdo->lastInsertId();

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return $id;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function deleteActiveForSource(int $sourceEventId, ?PDO $pdo = null): int
    {
        $pdo ??= Database::connection();
        $stmt = $pdo->prepare(
            "DELETE FROM notifications WHERE source_event_id = ? AND status = 'active'"
        );
        $stmt->execute([$sourceEventId]);
        return $stmt->rowCount();
    }

    public function hasDisabledForSource(int $sourceEventId, ?PDO $pdo = null): bool
    {
        $pdo ??= Database::connection();
        $stmt = $pdo->prepare(
            "SELECT 1 FROM notifications
             WHERE source_event_id = ? AND status = 'disabled'
             LIMIT 1"
        );
        $stmt->execute([$sourceEventId]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Remove superseded active notifications for the given source event IDs
     * (used when a newer event in the same recurrence group takes over).
     *
     * @param list<int> $sourceEventIds
     */
    public function deleteActiveForSources(array $sourceEventIds, ?PDO $pdo = null): int
    {
        if ($sourceEventIds === []) {
            return 0;
        }
        $pdo ??= Database::connection();
        $placeholders = implode(',', array_fill(0, count($sourceEventIds), '?'));
        $stmt = $pdo->prepare(
            "DELETE FROM notifications
             WHERE status = 'active' AND source_event_id IN ({$placeholders})"
        );
        $stmt->execute($sourceEventIds);
        return $stmt->rowCount();
    }

    public function disable(int $id, ?PDO $pdo = null): bool
    {
        $pdo ??= Database::connection();
        $stmt = $pdo->prepare(
            "UPDATE notifications
             SET status = 'disabled',
                 disabled_at = NOW(),
                 completed_at = NULL,
                 completion_event_id = NULL,
                 updated_at = NOW()
             WHERE id = ? AND status = 'active'"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() === 1;
    }

    /**
     * Atomically mark an active notification completed. Returns false if already resolved.
     */
    public function markCompleted(int $id, int $completionEventId, ?PDO $pdo = null): bool
    {
        if ($completionEventId < 1) {
            return false;
        }

        $pdo ??= Database::connection();
        $stmt = $pdo->prepare(
            "UPDATE notifications
             SET status = 'completed',
                 completion_event_id = ?,
                 completed_at = NOW(),
                 disabled_at = NULL,
                 updated_at = NOW()
             WHERE id = ? AND status = 'active'"
        );
        $stmt->execute([$completionEventId, $id]);
        return $stmt->rowCount() === 1;
    }

    /** @return array<string, mixed>|null */
    public function lockActiveForUpdate(int $id, PDO $pdo): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT * FROM notifications WHERE id = ? AND status = 'active' FOR UPDATE"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Delete history notifications that reference an event (after explicit confirmation).
     * Active notifications for the event as source should be cleared separately.
     */
    public function deleteHistoryReferencingEvent(int $eventId, ?PDO $pdo = null): int
    {
        $pdo ??= Database::connection();
        $stmt = $pdo->prepare(
            "DELETE FROM notifications
             WHERE status IN ('completed', 'disabled')
               AND (source_event_id = ? OR completion_event_id = ?)"
        );
        $stmt->execute([$eventId, $eventId]);
        return $stmt->rowCount();
    }

    /**
     * Clear completion links only (when event is deleted as completion, FK SET NULL handles it).
     * Also delete any remaining active rows for the event as source.
     */
    public function prepareEventDeletion(int $eventId, bool $purgeHistory, ?PDO $pdo = null): void
    {
        $pdo ??= Database::connection();
        $this->deleteActiveForSource($eventId, $pdo);
        if ($purgeHistory) {
            $this->deleteHistoryReferencingEvent($eventId, $pdo);
        }
    }

    /**
     * Dashboard / summary: overdue and due-soon active notifications.
     *
     * @return array{overdue: int, due_today: int, due_soon: int, upcoming: int, items: list<array<string, mixed>>}
     */
    /**
     * @return array{overdue: int, due_today: int, due_soon: int, upcoming: int, attention: int, items: list<array<string, mixed>>}
     */
    public function dashboardSummary(int $itemLimit = 5): array
    {
        $attention = $this->listAttention();
        $upcomingItems = $this->listUpcoming();
        $overdue = 0;
        $dueToday = 0;
        $dueSoon = 0;

        foreach ($attention as $row) {
            $state = NotificationPresenter::displayState($row);
            if ($state === 'overdue') {
                $overdue++;
            } elseif ($state === 'due_today') {
                $dueToday++;
            } else {
                $dueSoon++;
            }
        }

        return [
            'overdue' => $overdue,
            'due_today' => $dueToday,
            'due_soon' => $dueSoon,
            'upcoming' => count($upcomingItems),
            'attention' => count($attention),
            'items' => array_slice($attention, 0, max(1, min(10, $itemLimit))),
        ];
    }

    /** @param list<array<string, mixed>> $items */
    private function hydrateMembers(array &$items): void
    {
        if ($items === []) {
            return;
        }
        $eventIds = [];
        foreach ($items as $row) {
            $eventIds[(int) $row['source_event_id']] = (int) $row['source_event_id'];
        }
        $eventIds = array_values($eventIds);
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT em.event_id, m.id, m.name, m.photo_cropped, m.archived_at
             FROM event_members em
             INNER JOIN members m ON m.id = em.member_id
             WHERE em.event_id IN ({$placeholders})
             ORDER BY m.name ASC, m.id ASC"
        );
        $stmt->execute($eventIds);
        $byEvent = [];
        foreach ($stmt->fetchAll() as $row) {
            $eid = (int) $row['event_id'];
            unset($row['event_id']);
            $byEvent[$eid][] = $row;
        }
        foreach ($items as &$item) {
            $item['members'] = $byEvent[(int) $item['source_event_id']] ?? [];
        }
        unset($item);
    }
}
