<?php

declare(strict_types=1);

namespace PawBerry\Repositories;

use PawBerry\Core\Database;
use PDO;
use Throwable;

final class EventTypeRepository
{
    /** @return list<array<string, mixed>> */
    public function allActive(): array
    {
        $stmt = Database::connection()->query(
            'SELECT et.*,
                    (SELECT COUNT(*) FROM event_type_fields f
                     WHERE f.event_type_id = et.id AND f.deleted_at IS NULL) AS field_count
             FROM event_types et
             WHERE et.archived_at IS NULL
             ORDER BY et.name ASC, et.id ASC'
        );
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function allArchived(): array
    {
        $stmt = Database::connection()->query(
            'SELECT et.*,
                    (SELECT COUNT(*) FROM event_type_fields f
                     WHERE f.event_type_id = et.id AND f.deleted_at IS NULL) AS field_count
             FROM event_types et
             WHERE et.archived_at IS NOT NULL
             ORDER BY et.archived_at DESC, et.name ASC'
        );
        return $stmt->fetchAll();
    }

    public function countArchived(): int
    {
        $stmt = Database::connection()->query(
            'SELECT COUNT(*) FROM event_types WHERE archived_at IS NOT NULL'
        );
        return (int) $stmt->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM event_types WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function fieldsForType(int $eventTypeId, bool $includeDeleted = false): array
    {
        $sql = 'SELECT * FROM event_type_fields WHERE event_type_id = ?';
        if (!$includeDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$eventTypeId]);
        return $stmt->fetchAll();
    }

    /** Active name uniqueness (case-insensitive), excluding an id when editing. */
    public function activeNameExists(string $name, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM event_types
                WHERE archived_at IS NULL
                  AND LOWER(name) = LOWER(?)';
        $params = [$name];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $sql .= ' LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }

    /**
     * @param array{
     *   name: string,
     *   description: ?string,
     *   icon: string,
     *   color: string,
     *   recurrence_interval: ?int,
     *   recurrence_unit: ?string,
     *   reminder_lead_days: int
     * } $data
     * @param list<array{
     *   id: ?int,
     *   field_key: ?string,
     *   name: string,
     *   field_type: string,
     *   is_required: bool
     * }> $fields
     */
    public function createWithFields(array $data, array $fields): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO event_types (
                    name, description, icon, color,
                    recurrence_interval, recurrence_unit, reminder_lead_days,
                    created_at, updated_at
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([
                $data['name'],
                $data['description'],
                $data['icon'],
                $data['color'],
                $data['recurrence_interval'],
                $data['recurrence_unit'],
                $data['reminder_lead_days'],
            ]);
            $id = (int) $pdo->lastInsertId();
            $this->syncFields($pdo, $id, $fields, []);
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
     * @param array{
     *   name: string,
     *   description: ?string,
     *   icon: string,
     *   color: string,
     *   recurrence_interval: ?int,
     *   recurrence_unit: ?string,
     *   reminder_lead_days: int
     * } $data
     * @param list<array{
     *   id: ?int,
     *   field_key: ?string,
     *   name: string,
     *   field_type: string,
     *   is_required: bool
     * }> $fields
     */
    public function updateWithFields(int $id, array $data, array $fields): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'UPDATE event_types SET
                    name = ?, description = ?, icon = ?, color = ?,
                    recurrence_interval = ?, recurrence_unit = ?, reminder_lead_days = ?,
                    updated_at = NOW()
                 WHERE id = ?'
            );
            $stmt->execute([
                $data['name'],
                $data['description'],
                $data['icon'],
                $data['color'],
                $data['recurrence_interval'],
                $data['recurrence_unit'],
                $data['reminder_lead_days'],
                $id,
            ]);

            $existing = $this->fieldsForType($id, true);
            $this->syncFields($pdo, $id, $fields, $existing);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function archive(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE event_types SET archived_at = NOW(), updated_at = NOW()
             WHERE id = ? AND archived_at IS NULL'
        );
        $stmt->execute([$id]);
    }

    public function restore(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE event_types SET archived_at = NULL, updated_at = NOW()
             WHERE id = ? AND archived_at IS NOT NULL'
        );
        $stmt->execute([$id]);
    }

    /**
     * @param list<array{
     *   id: ?int,
     *   field_key: ?string,
     *   name: string,
     *   field_type: string,
     *   is_required: bool
     * }> $fields
     * @param list<array<string, mixed>> $existing
     */
    private function syncFields(PDO $pdo, int $eventTypeId, array $fields, array $existing): void
    {
        $byId = [];
        $activeKeys = [];
        foreach ($existing as $row) {
            $byId[(int) $row['id']] = $row;
            if ($row['deleted_at'] === null) {
                $activeKeys[(int) $row['id']] = (string) $row['field_key'];
            }
        }

        $allKeys = array_map(
            static fn (array $row): string => (string) $row['field_key'],
            $existing
        );

        $keptIds = [];
        $insert = $pdo->prepare(
            'INSERT INTO event_type_fields (
                event_type_id, name, field_key, field_type, is_required, sort_order,
                created_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $update = $pdo->prepare(
            'UPDATE event_type_fields SET
                name = ?, field_type = ?, is_required = ?, sort_order = ?,
                deleted_at = NULL, updated_at = NOW()
             WHERE id = ? AND event_type_id = ?'
        );

        foreach ($fields as $index => $field) {
            $sort = $index;
            $required = $field['is_required'] ? 1 : 0;
            $id = $field['id'];

            if ($id !== null && isset($byId[$id])) {
                $update->execute([
                    $field['name'],
                    $field['field_type'],
                    $required,
                    $sort,
                    $id,
                    $eventTypeId,
                ]);
                $keptIds[$id] = true;
                continue;
            }

            $key = $field['field_key'];
            if (!is_string($key) || $key === '') {
                $key = \PawBerry\Support\EventTypeCatalog::makeFieldKey($field['name'], $allKeys);
            }
            if (in_array($key, $allKeys, true)) {
                $key = \PawBerry\Support\EventTypeCatalog::makeFieldKey($field['name'], $allKeys);
            }
            $allKeys[] = $key;

            $insert->execute([
                $eventTypeId,
                $field['name'],
                $key,
                $field['field_type'],
                $required,
                $sort,
            ]);
        }

        $softDelete = $pdo->prepare(
            'UPDATE event_type_fields SET deleted_at = NOW(), updated_at = NOW()
             WHERE id = ? AND event_type_id = ? AND deleted_at IS NULL'
        );
        foreach ($activeKeys as $existingId => $_key) {
            if (!isset($keptIds[$existingId])) {
                $softDelete->execute([$existingId, $eventTypeId]);
            }
        }
    }
}
