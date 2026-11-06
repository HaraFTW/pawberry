<?php

declare(strict_types=1);

return new class {
    public function up(PDO $pdo): void
    {
        $pdo->exec(
            'ALTER TABLE events
                ADD COLUMN recurrence_interval INT UNSIGNED NULL AFTER cost,
                ADD COLUMN recurrence_unit VARCHAR(16) NULL AFTER recurrence_interval,
                ADD COLUMN reminder_lead_days INT UNSIGNED NOT NULL DEFAULT 0 AFTER recurrence_unit'
        );

        $this->backfillSchedulesAlreadyInUse($pdo);
    }

    /**
     * Events that already belong to a scheduled group keep that group's current
     * type schedule, so existing reminders still resolve from the event itself.
     * Events that never received a reminder stay unset.
     */
    private function backfillSchedulesAlreadyInUse(PDO $pdo): void
    {
        $types = [];
        foreach ($pdo->query('SELECT id, recurrence_interval, recurrence_unit, reminder_lead_days FROM event_types') as $type) {
            $types[(int) $type['id']] = $type;
        }

        $membersByEvent = [];
        foreach ($pdo->query('SELECT event_id, member_id FROM event_members') as $row) {
            $membersByEvent[(int) $row['event_id']][] = (int) $row['member_id'];
        }

        $events = [];
        foreach ($pdo->query('SELECT id, event_type_id FROM events') as $event) {
            $id = (int) $event['id'];
            $memberIds = $membersByEvent[$id] ?? [];
            sort($memberIds);
            $events[$id] = [
                'type_id' => (int) $event['event_type_id'],
                'members' => $memberIds,
            ];
        }

        $groupIds = [];
        foreach ($events as $id => $event) {
            $key = $event['type_id'] . ':' . implode(',', $event['members']);
            $groupIds[$key][] = $id;
        }

        $update = $pdo->prepare(
            'UPDATE events
             SET recurrence_interval = ?, recurrence_unit = ?, reminder_lead_days = ?
             WHERE id = ?'
        );

        $seen = [];
        $sources = $pdo->query('SELECT DISTINCT source_event_id FROM notifications');
        foreach ($sources as $row) {
            $sourceId = (int) $row['source_event_id'];
            $event = $events[$sourceId] ?? null;
            if ($event === null) {
                continue;
            }

            $type = $types[$event['type_id']] ?? null;
            if (
                $type === null
                || $type['recurrence_interval'] === null
                || $type['recurrence_unit'] === null
            ) {
                continue;
            }

            $key = $event['type_id'] . ':' . implode(',', $event['members']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            foreach ($groupIds[$key] ?? [] as $eventId) {
                $update->execute([
                    (int) $type['recurrence_interval'],
                    (string) $type['recurrence_unit'],
                    (int) ($type['reminder_lead_days'] ?? 0),
                    $eventId,
                ]);
            }
        }
    }
};
