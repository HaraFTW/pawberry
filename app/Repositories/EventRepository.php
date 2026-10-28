<?php

declare(strict_types=1);

namespace PawBerry\Repositories;

use PawBerry\Core\Database;
use PDO;
use Throwable;

final class EventRepository
{
    public const PER_PAGE = 20;

    /**
     * @param array{
     *   member_id?: ?int,
     *   member_ids?: list<int>,
     *   event_type_id?: ?int,
     *   page?: int
     * } $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = self::PER_PAGE;
        $typeId = isset($filters['event_type_id']) ? (int) $filters['event_type_id'] : 0;

        $memberIds = [];
        if (isset($filters['member_ids']) && is_array($filters['member_ids'])) {
            foreach ($filters['member_ids'] as $mid) {
                $id = (int) $mid;
                if ($id > 0) {
                    $memberIds[$id] = $id;
                }
            }
        } elseif (isset($filters['member_id']) && (int) $filters['member_id'] > 0) {
            $memberIds[(int) $filters['member_id']] = (int) $filters['member_id'];
        }
        $memberIds = array_values($memberIds);

        $where = [];
        $params = [];

        if ($memberIds !== []) {
            // Exact member-set match: event members == selected members (order-independent).
            $count = count($memberIds);
            $placeholders = implode(',', array_fill(0, $count, '?'));
            $where[] = '(
                (SELECT COUNT(*) FROM event_members emc WHERE emc.event_id = e.id) = ?
                AND (SELECT COUNT(*) FROM event_members emm
                     WHERE emm.event_id = e.id AND emm.member_id IN (' . $placeholders . ')) = ?
            )';
            $params[] = $count;
            foreach ($memberIds as $mid) {
                $params[] = $mid;
            }
            $params[] = $count;
        }
        if ($typeId > 0) {
            $where[] = 'e.event_type_id = ?';
            $params[] = $typeId;
        }

        $whereSql = $where === [] ? '' : ('WHERE ' . implode(' AND ', $where));

        $countSql = "SELECT COUNT(*) FROM events e {$whereSql}";
        $countStmt = Database::connection()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT e.*,
                       et.name AS type_name,
                       et.icon AS type_icon,
                       et.color AS type_color,
                       et.archived_at AS type_archived_at
                FROM events e
                INNER JOIN event_types et ON et.id = e.event_type_id
                {$whereSql}
                ORDER BY e.event_date DESC, e.id DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        $this->hydrateMembers($items);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    /**
     * Most recently recorded events (by event_date, then id).
     *
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $stmt = Database::connection()->prepare(
            "SELECT e.*,
                    et.name AS type_name,
                    et.icon AS type_icon,
                    et.color AS type_color,
                    et.archived_at AS type_archived_at
             FROM events e
             INNER JOIN event_types et ON et.id = e.event_type_id
             ORDER BY e.event_date DESC, e.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute();
        $items = $stmt->fetchAll();
        $this->hydrateMembers($items);
        return $items;
    }

    /**
     * Events whose event_date falls in [from, to] inclusive.
     *
     * @param list<int> $memberIds exact-set filter; empty = no member filter
     * @return list<array<string, mixed>>
     */
    public function forDateRange(string $from, string $to, array $memberIds = [], ?int $eventTypeId = null): array
    {
        $where = ['e.event_date BETWEEN ? AND ?'];
        $params = [$from, $to];

        $memberIds = $this->normalizeMemberIds($memberIds);
        if ($memberIds !== []) {
            $count = count($memberIds);
            $placeholders = implode(',', array_fill(0, $count, '?'));
            $where[] = '(
                (SELECT COUNT(*) FROM event_members emc WHERE emc.event_id = e.id) = ?
                AND (SELECT COUNT(*) FROM event_members emm
                     WHERE emm.event_id = e.id AND emm.member_id IN (' . $placeholders . ')) = ?
            )';
            $params[] = $count;
            foreach ($memberIds as $mid) {
                $params[] = $mid;
            }
            $params[] = $count;
        }

        if ($eventTypeId !== null && $eventTypeId > 0) {
            $where[] = 'e.event_type_id = ?';
            $params[] = $eventTypeId;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $stmt = Database::connection()->prepare(
            "SELECT e.*,
                    et.name AS type_name,
                    et.icon AS type_icon,
                    et.color AS type_color,
                    et.archived_at AS type_archived_at
             FROM events e
             INNER JOIN event_types et ON et.id = e.event_type_id
             {$whereSql}
             ORDER BY e.event_date ASC, e.id ASC"
        );
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        $this->hydrateMembers($items);
        return $items;
    }

    /** @return list<array<string, mixed>> */
    public function recentForMember(int $memberId, int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $stmt = Database::connection()->prepare(
            "SELECT e.*,
                    et.name AS type_name,
                    et.icon AS type_icon,
                    et.color AS type_color,
                    et.archived_at AS type_archived_at
             FROM events e
             INNER JOIN event_types et ON et.id = e.event_type_id
             INNER JOIN event_members em ON em.event_id = e.id
             WHERE em.member_id = ?
             ORDER BY e.event_date DESC, e.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$memberId]);
        $items = $stmt->fetchAll();
        $this->hydrateMembers($items);
        return $items;
    }

    public function countForMember(int $memberId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM event_members WHERE member_id = ?'
        );
        $stmt->execute([$memberId]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.*,
                    et.name AS type_name,
                    et.icon AS type_icon,
                    et.color AS type_color,
                    et.archived_at AS type_archived_at
             FROM events e
             INNER JOIN event_types et ON et.id = e.event_type_id
             WHERE e.id = ?
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

    /** @return list<array<string, mixed>> */
    public function fieldValues(int $eventId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT v.*,
                    f.deleted_at AS field_deleted_at,
                    f.is_required AS field_is_required,
                    f.sort_order AS field_sort_order
             FROM event_field_values v
             INNER JOIN event_type_fields f ON f.id = v.event_type_field_id
             WHERE v.event_id = ?
             ORDER BY
                CASE WHEN f.deleted_at IS NULL THEN 0 ELSE 1 END,
                f.sort_order ASC,
                v.id ASC'
        );
        $stmt->execute([$eventId]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function attachments(int $eventId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM event_attachments WHERE event_id = ? ORDER BY id ASC'
        );
        $stmt->execute([$eventId]);
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findAttachment(int $eventId, int $attachmentId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM event_attachments WHERE event_id = ? AND id = ? LIMIT 1'
        );
        $stmt->execute([$eventId, $attachmentId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Latest event for an exact member set + type (tie-break: higher id).
     *
     * @param list<int> $memberIds
     * @return array<string, mixed>|null
     */
    public function findLatestForTypeAndExactMembers(int $eventTypeId, array $memberIds): ?array
    {
        $ids = $this->idsForTypeAndExactMembers($eventTypeId, $memberIds);
        if ($ids === []) {
            return null;
        }
        // ids are ordered event_date DESC, id DESC
        return $this->find($ids[0]);
    }

    /**
     * Event IDs matching type + exact member set, newest first.
     *
     * @param list<int> $memberIds
     * @return list<int>
     */
    public function idsForTypeAndExactMembers(int $eventTypeId, array $memberIds): array
    {
        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
        sort($memberIds);
        if ($memberIds === [] || $eventTypeId < 1) {
            return [];
        }

        $count = count($memberIds);
        $placeholders = implode(',', array_fill(0, $count, '?'));
        $params = array_merge([$eventTypeId, $count], $memberIds, [$count]);

        $stmt = Database::connection()->prepare(
            "SELECT e.id
             FROM events e
             WHERE e.event_type_id = ?
               AND (SELECT COUNT(*) FROM event_members emc WHERE emc.event_id = e.id) = ?
               AND (SELECT COUNT(*) FROM event_members emm
                    WHERE emm.event_id = e.id AND emm.member_id IN ({$placeholders})) = ?
             ORDER BY e.event_date DESC, e.id DESC"
        );
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @param array{
     *   event_type_id: int,
     *   event_date: string,
     *   title: ?string,
     *   notes: ?string,
     *   cost: ?string
     * } $data
     * @param list<int> $memberIds
     * @param list<array{
     *   event_type_field_id: int,
     *   field_key: string,
     *   field_name: string,
     *   field_type: string,
     *   value_text: ?string
     * }> $fieldValues
     */
    public function create(array $data, array $memberIds, array $fieldValues): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $id = $this->insertEvent($pdo, $data, $memberIds, $fieldValues);
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Insert an event using the caller's open transaction (no begin/commit).
     *
     * @param array{
     *   event_type_id: int,
     *   event_date: string,
     *   title: ?string,
     *   notes: ?string,
     *   cost: ?string
     * } $data
     * @param list<int> $memberIds
     * @param list<array{
     *   event_type_field_id: int,
     *   field_key: string,
     *   field_name: string,
     *   field_type: string,
     *   value_text: ?string
     * }> $fieldValues
     */
    public function createInTransaction(PDO $pdo, array $data, array $memberIds, array $fieldValues): int
    {
        return $this->insertEvent($pdo, $data, $memberIds, $fieldValues);
    }

    /**
     * @param array{
     *   event_type_id: int,
     *   event_date: string,
     *   title: ?string,
     *   notes: ?string,
     *   cost: ?string
     * } $data
     * @param list<int> $memberIds
     * @param list<array{
     *   event_type_field_id: int,
     *   field_key: string,
     *   field_name: string,
     *   field_type: string,
     *   value_text: ?string
     * }> $fieldValues
     */
    private function insertEvent(PDO $pdo, array $data, array $memberIds, array $fieldValues): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO events (event_type_id, event_date, title, notes, cost, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['event_type_id'],
            $data['event_date'],
            $data['title'],
            $data['notes'],
            $data['cost'],
        ]);
        $id = (int) $pdo->lastInsertId();
        $this->replaceMembers($pdo, $id, $memberIds);
        $this->replaceFieldValues($pdo, $id, $fieldValues);
        return $id;
    }

    /**
     * @param list<array{
     *   original_filename: string,
     *   stored_path: string,
     *   mime_type: string,
     *   file_size: int
     * }> $attachments
     */
    public function addAttachments(int $eventId, array $attachments): void
    {
        if ($attachments === []) {
            return;
        }
        $pdo = Database::connection();
        $this->insertAttachments($pdo, $eventId, $attachments);
    }

    public function countAttachments(int $eventId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM event_attachments WHERE event_id = ?'
        );
        $stmt->execute([$eventId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array{
     *   event_type_id: int,
     *   event_date: string,
     *   title: ?string,
     *   notes: ?string,
     *   cost: ?string
     * } $data
     * @param list<int> $memberIds
     * @param list<array{
     *   event_type_field_id: int,
     *   field_key: string,
     *   field_name: string,
     *   field_type: string,
     *   value_text: ?string
     * }> $fieldValues
     * @param list<array{
     *   original_filename: string,
     *   stored_path: string,
     *   mime_type: string,
     *   file_size: int
     * }> $newAttachments
     * @param list<int> $removeAttachmentIds
     */
    public function update(
        int $id,
        array $data,
        array $memberIds,
        array $fieldValues,
        array $newAttachments = [],
        array $removeAttachmentIds = [],
        bool $replaceAllFieldValues = false
    ): void {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE events SET
                    event_type_id = ?, event_date = ?, title = ?, notes = ?, cost = ?,
                    updated_at = NOW()
                 WHERE id = ?'
            );
            $stmt->execute([
                $data['event_type_id'],
                $data['event_date'],
                $data['title'],
                $data['notes'],
                $data['cost'],
                $id,
            ]);
            $this->replaceMembers($pdo, $id, $memberIds);

            if ($replaceAllFieldValues) {
                $del = $pdo->prepare('DELETE FROM event_field_values WHERE event_id = ?');
                $del->execute([$id]);
                $this->replaceFieldValues($pdo, $id, $fieldValues);
            } else {
                $this->upsertFieldValues($pdo, $id, $fieldValues);
            }

            if ($removeAttachmentIds !== []) {
                $placeholders = implode(',', array_fill(0, count($removeAttachmentIds), '?'));
                $delA = $pdo->prepare(
                    "DELETE FROM event_attachments WHERE event_id = ? AND id IN ({$placeholders})"
                );
                $delA->execute(array_merge([$id], $removeAttachmentIds));
            }

            $this->insertAttachments($pdo, $id, $newAttachments);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> paths of attachments that were removed from DB */
    public function delete(int $id): array
    {
        $attachments = $this->attachments($id);
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('DELETE FROM events WHERE id = ?');
            $stmt->execute([$id]);
            $pdo->commit();
            return $attachments;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @param list<int> $memberIds @return list<int> */
    private function normalizeMemberIds(array $memberIds): array
    {
        $normalized = [];
        foreach ($memberIds as $mid) {
            $id = (int) $mid;
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }
        return array_values($normalized);
    }

    /** @param list<array<string, mixed>> $items */
    private function hydrateMembers(array &$items): void
    {
        if ($items === []) {
            return;
        }
        $ids = array_map(static fn (array $row): int => (int) $row['id'], $items);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT em.event_id, m.id, m.name, m.photo_cropped, m.archived_at
             FROM event_members em
             INNER JOIN members m ON m.id = em.member_id
             WHERE em.event_id IN ({$placeholders})
             ORDER BY m.name ASC, m.id ASC"
        );
        $stmt->execute($ids);
        $byEvent = [];
        foreach ($stmt->fetchAll() as $row) {
            $eid = (int) $row['event_id'];
            unset($row['event_id']);
            $byEvent[$eid][] = $row;
        }
        foreach ($items as &$item) {
            $item['members'] = $byEvent[(int) $item['id']] ?? [];
        }
        unset($item);
    }

    /** @param list<int> $memberIds */
    private function replaceMembers(PDO $pdo, int $eventId, array $memberIds): void
    {
        $del = $pdo->prepare('DELETE FROM event_members WHERE event_id = ?');
        $del->execute([$eventId]);
        $ins = $pdo->prepare('INSERT INTO event_members (event_id, member_id) VALUES (?, ?)');
        foreach ($memberIds as $memberId) {
            $ins->execute([$eventId, $memberId]);
        }
    }

    /**
     * @param list<array{
     *   event_type_field_id: int,
     *   field_key: string,
     *   field_name: string,
     *   field_type: string,
     *   value_text: ?string
     * }> $fieldValues
     */
    private function replaceFieldValues(PDO $pdo, int $eventId, array $fieldValues): void
    {
        $ins = $pdo->prepare(
            'INSERT INTO event_field_values (
                event_id, event_type_field_id, field_key, field_name, field_type, value_text,
                created_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        foreach ($fieldValues as $row) {
            if ($row['value_text'] === null || $row['value_text'] === '') {
                if ($row['field_type'] !== 'checkbox') {
                    continue;
                }
            }
            $ins->execute([
                $eventId,
                $row['event_type_field_id'],
                $row['field_key'],
                $row['field_name'],
                $row['field_type'],
                $row['value_text'],
            ]);
        }
    }

    /**
     * @param list<array{
     *   event_type_field_id: int,
     *   field_key: string,
     *   field_name: string,
     *   field_type: string,
     *   value_text: ?string
     * }> $fieldValues
     */
    private function upsertFieldValues(PDO $pdo, int $eventId, array $fieldValues): void
    {
        $upsert = $pdo->prepare(
            'INSERT INTO event_field_values (
                event_id, event_type_field_id, field_key, field_name, field_type, value_text,
                created_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                field_key = VALUES(field_key),
                field_name = VALUES(field_name),
                field_type = VALUES(field_type),
                value_text = VALUES(value_text),
                updated_at = NOW()'
        );
        $delete = $pdo->prepare(
            'DELETE FROM event_field_values WHERE event_id = ? AND event_type_field_id = ?'
        );

        foreach ($fieldValues as $row) {
            $empty = $row['value_text'] === null || $row['value_text'] === '';
            if ($empty && $row['field_type'] !== 'checkbox') {
                $delete->execute([$eventId, $row['event_type_field_id']]);
                continue;
            }
            $upsert->execute([
                $eventId,
                $row['event_type_field_id'],
                $row['field_key'],
                $row['field_name'],
                $row['field_type'],
                $row['value_text'],
            ]);
        }
    }

    /**
     * @param list<array{
     *   original_filename: string,
     *   stored_path: string,
     *   mime_type: string,
     *   file_size: int
     * }> $attachments
     */
    private function insertAttachments(PDO $pdo, int $eventId, array $attachments): void
    {
        if ($attachments === []) {
            return;
        }
        $ins = $pdo->prepare(
            'INSERT INTO event_attachments (
                event_id, original_filename, stored_path, mime_type, file_size, created_at
             ) VALUES (?, ?, ?, ?, ?, NOW())'
        );
        foreach ($attachments as $row) {
            $ins->execute([
                $eventId,
                $row['original_filename'],
                $row['stored_path'],
                $row['mime_type'],
                $row['file_size'],
            ]);
        }
    }
}
