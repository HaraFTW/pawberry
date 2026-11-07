<?php

declare(strict_types=1);

/**
 * Phase 5 regression checks (CLI). Usage: php bin/test-phase5.php
 */

require dirname(__DIR__) . '/bootstrap.php';

use PawBerry\Core\Database;
use PawBerry\Repositories\EventRepository;
use PawBerry\Repositories\EventTypeRepository;
use PawBerry\Repositories\MemberRepository;
use PawBerry\Repositories\NotificationRepository;
use PawBerry\Services\RecurrenceCalculator;
use PawBerry\Services\RecurrenceService;
use PawBerry\Support\NotificationPresenter;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$fail = 0;
$pass = static function (string $label, bool $ok) use (&$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$ok) {
        $fail++;
    }
};

$c = new RecurrenceCalculator();
$pass('14 days', $c->nextDueDate('2026-10-01', 14, 'days') === '2026-10-15');
$pass('3 weeks = 21 days', $c->nextDueDate('2026-10-01', 3, 'weeks') === '2026-10-22');
$pass('Jan 31 + 1 month → Feb 28', $c->nextDueDate('2026-01-31', 1, 'months') === '2026-02-28');
$pass('Jan 31 + 1 month leap → Feb 29', $c->nextDueDate('2024-01-31', 1, 'months') === '2024-02-29');
$pass('Mar 31 + 1 month → Apr 30', $c->nextDueDate('2026-03-31', 1, 'months') === '2026-04-30');
$pass('Feb 29 + 1 year → Feb 28', $c->nextDueDate('2024-02-29', 1, 'years') === '2025-02-28');
$pass('1 year', $c->nextDueDate('2026-10-20', 1, 'years') === '2027-10-20');
$pass('reminder lead 4', $c->reminderStartDate('2026-10-20', 4) === '2026-10-16');
$pass('reminder lead 0', $c->reminderStartDate('2026-10-20', 0) === '2026-10-20');

$today = new DateTimeImmutable('today');
$pass(
    'display due_soon',
    NotificationPresenter::displayState([
        'due_date' => $today->modify('+4 days')->format('Y-m-d'),
        'reminder_start_date' => $today->format('Y-m-d'),
    ]) === 'due_soon'
);
$pass(
    'display due_today',
    NotificationPresenter::displayState([
        'due_date' => $today->format('Y-m-d'),
        'reminder_start_date' => $today->modify('-1 day')->format('Y-m-d'),
    ]) === 'due_today'
);
$pass(
    'display overdue',
    NotificationPresenter::displayState([
        'due_date' => $today->modify('-3 days')->format('Y-m-d'),
        'reminder_start_date' => $today->modify('-7 days')->format('Y-m-d'),
    ]) === 'overdue'
);
$pass(
    'display upcoming',
    NotificationPresenter::displayState([
        'due_date' => $today->modify('+10 days')->format('Y-m-d'),
        'reminder_start_date' => $today->modify('+6 days')->format('Y-m-d'),
    ]) === 'upcoming'
);

$pdo = Database::connection();
$members = new MemberRepository();
$types = new EventTypeRepository();
$events = new EventRepository();
$notifications = new NotificationRepository();
$recurrence = new RecurrenceService($events, $types, $notifications, $c);

$allMembers = $members->allActive();
$byName = [];
foreach ($allMembers as $m) {
    $byName[(string) $m['name']] = (int) $m['id'];
}

$independentMemberId = 0;
$independentTypeId = 0;
$independentEventIds = [];
try {
    $suffix = bin2hex(random_bytes(3));
    $independentMemberId = $members->create([
        'name' => 'P5 Solo ' . $suffix,
        'species' => 'dog',
        'breed' => null,
        'sex' => null,
        'birth_date' => null,
        'microchip_number' => null,
        'description' => null,
        'notes' => null,
    ]);
    $independentTypeId = $types->createWithFields([
        'name' => 'P5 Solo ' . $suffix,
        'description' => null,
        'icon' => 'paw-print',
        'color' => '#E4BB93',
        'recurrence_interval' => 14,
        'recurrence_unit' => 'days',
        'reminder_lead_days' => 2,
    ], []);

    $recentDate = $today->modify('-3 days')->format('Y-m-d');
    $boundaryDate = $today->modify('-14 days')->format('Y-m-d');
    $staleDate = $today->modify('-40 days')->format('Y-m-d');

    $make = static function (string $date, string $title) use ($recurrence, $independentTypeId, $independentMemberId, &$independentEventIds): int {
        $created = $recurrence->createEventWithOptionalCompletion(
            [
                'event_type_id' => $independentTypeId,
                'event_date' => $date,
                'title' => $title,
                'notes' => null,
                'cost' => null,
            ],
            [$independentMemberId],
            []
        );
        $independentEventIds[] = $created['event_id'];
        return $created['event_id'];
    };

    $recentId = $make($recentDate, 'P5 recent');
    $boundaryId = $make($boundaryDate, 'P5 boundary');
    $staleId = $make($staleDate, 'P5 stale');
    $secondRecent = $make($recentDate, 'P5 second recent');

    $recentRows = $notifications->findActiveForSourceEvent($recentId);
    $boundaryRows = $notifications->findActiveForSourceEvent($boundaryId);
    $staleRows = $notifications->findActiveForSourceEvent($staleId);
    $secondRows = $notifications->findActiveForSourceEvent($secondRecent);
    $pass('each event keeps its own reminder', count($recentRows) === 1 && count($secondRows) === 1);
    $pass('second event does not replace the first', (int) $recentRows[0]['source_event_id'] === $recentId);
    $pass('create notifies even when due is already past', count($staleRows) === 1);
    $pass('exactly one interval ago is due today', ($boundaryRows[0]['due_date'] ?? '') === $today->format('Y-m-d'));

    $pass('disable works', $notifications->disable((int) $recentRows[0]['id']));
    $unchanged = $events->find($recentId);
    $recurrence->syncAfterEventUpdated($recentId, $unchanged ?? []);
    $pass('unrelated save leaves a disabled reminder', $notifications->findActiveForSourceEvent($recentId) === []);

    $recurrence->applyTypeRecurrenceToEvents($independentTypeId, 14, 'days', 2);
    $revived = $notifications->findActiveForSourceEvent($recentId);
    $boundaryAfter = $notifications->findActiveForSourceEvent($boundaryId);
    $staleAfter = $notifications->findBySourceEvent($staleId);
    $pass('cascade reactivates a disabled in-window reminder', count($revived) === 1 && ($revived[0]['status'] ?? '') === 'active');
    $pass('cascade includes a due date of today', count($boundaryAfter) === 1);
    $pass(
        'cascade drops a reminder older than the interval',
        $notifications->findActiveForSourceEvent($staleId) === []
        && $staleAfter === []
    );

    $completeId = (int) ($boundaryAfter[0]['id'] ?? 0);
    $followUp = $recurrence->createEventWithOptionalCompletion(
        [
            'event_type_id' => $independentTypeId,
            'event_date' => $today->format('Y-m-d'),
            'title' => 'P5 follow up',
            'notes' => null,
            'cost' => null,
        ],
        [$independentMemberId],
        [],
        $completeId
    );
    $independentEventIds[] = $followUp['event_id'];
    $completedRow = $notifications->find($completeId);
    $pass('recording the event completes the old reminder', ($completedRow['status'] ?? '') === 'completed');
    $pass(
        'follow-up has its own reminder',
        count($notifications->findActiveForSourceEvent($followUp['event_id'])) === 1
    );
    $recurrence->applyTypeRecurrenceToEvents($independentTypeId, 14, 'days', 2);
    $completedAgain = $notifications->find($completeId);
    $pass('cascade leaves a completed reminder completed', ($completedAgain['status'] ?? '') === 'completed');

    $recurrence->applyTypeRecurrenceToEvents($independentTypeId, null, null, 0);
    $removed = $notifications->findBySourceEvent($recentId);
    $pass(
        'cascade remove marks the reminder canceled',
        $notifications->findActiveForSourceEvent($recentId) === []
        && ($removed[0]['status'] ?? '') === 'canceled'
    );

    $before = $events->find($recentId) ?? [];
    $events->update($recentId, [
        'event_type_id' => $independentTypeId,
        'event_date' => $recentDate,
        'title' => 'P5 individual',
        'notes' => null,
        'cost' => null,
        'recurrence_interval' => 5,
        'recurrence_unit' => 'days',
        'reminder_lead_days' => 1,
    ], [$independentMemberId], []);
    $recurrence->syncAfterEventUpdated($recentId, $before);
    $own = $notifications->findActiveForSourceEvent($recentId);
    $expectedDue = $c->nextDueDate($recentDate, 5, 'days');
    $pass(
        'individual recurrence schedules its own reminder',
        count($own) === 1 && ($own[0]['due_date'] ?? '') === $expectedDue
    );

    $beforeIndividual = $events->find($recentId) ?? [];
    $events->update($recentId, [
        'event_type_id' => $independentTypeId,
        'event_date' => $recentDate,
        'title' => 'P5 individual cleared',
        'notes' => null,
        'cost' => null,
        'recurrence_interval' => null,
        'recurrence_unit' => null,
        'reminder_lead_days' => 0,
    ], [$independentMemberId], []);
    $recurrence->syncAfterEventUpdated($recentId, $beforeIndividual);
    $cleared = $notifications->findBySourceEvent($recentId);
    $pass(
        'individual clear cancels the reminder',
        $notifications->findActiveForSourceEvent($recentId) === []
        && ($cleared[0]['status'] ?? '') === 'canceled'
    );
} catch (Throwable $e) {
    $pass('independent recurrence checks', false);
    fwrite(STDERR, $e->getMessage() . "\n");
} finally {
    foreach ($independentEventIds as $eid) {
        $pdo->prepare('DELETE FROM notifications WHERE source_event_id = ? OR completion_event_id = ?')
            ->execute([$eid, $eid]);
        try {
            $events->delete($eid);
        } catch (Throwable) {
        }
    }
    if ($independentTypeId > 0) {
        $pdo->prepare('DELETE FROM event_types WHERE id = ?')->execute([$independentTypeId]);
    }
    if ($independentMemberId > 0) {
        $pdo->prepare('DELETE FROM members WHERE id = ?')->execute([$independentMemberId]);
    }
}

foreach (['Berry', 'Mochi', 'Hara'] as $need) {
    if (!isset($byName[$need])) {
        echo "SKIP integration — missing member {$need}\n";
        echo $fail === 0 ? "Calculator checks passed.\n" : "FAILED {$fail}\n";
        exit($fail > 0 ? 1 : 0);
    }
}

$berry = $byName['Berry'];
$mochi = $byName['Mochi'];

// Ensure a recurring Deworming type (or create temporary).
$deworm = null;
foreach ($types->allActive() as $t) {
    if ((string) $t['name'] === 'Deworming') {
        $deworm = $t;
        break;
    }
}
if ($deworm === null) {
    echo "SKIP integration — missing Deworming type\n";
    exit($fail > 0 ? 1 : 0);
}

$originalRecurrence = [
    'interval' => $deworm['recurrence_interval'],
    'unit' => $deworm['recurrence_unit'],
    'lead' => $deworm['reminder_lead_days'],
];

// Force known recurrence for tests.
$pdo->prepare(
    'UPDATE event_types SET recurrence_interval = 30, recurrence_unit = ?, reminder_lead_days = 4 WHERE id = ?'
)->execute(['days', (int) $deworm['id']]);
$typeId = (int) $deworm['id'];

$createdEventIds = [];
$tempTypeId = 0;
$cleanup = static function () use ($pdo, &$createdEventIds, &$tempTypeId, $notifications, $events, $originalRecurrence, $typeId): void {
    foreach ($createdEventIds as $eid) {
        $pdo->prepare("DELETE FROM notifications WHERE source_event_id = ? OR completion_event_id = ?")
            ->execute([$eid, $eid]);
        try {
            $events->delete($eid);
        } catch (Throwable) {
            // ignore
        }
    }
    $pdo->prepare(
        'UPDATE event_types SET recurrence_interval = ?, recurrence_unit = ?, reminder_lead_days = ? WHERE id = ?'
    )->execute([
        $originalRecurrence['interval'],
        $originalRecurrence['unit'],
        $originalRecurrence['lead'] ?? 0,
        $typeId,
    ]);
    if ($tempTypeId > 0) {
        $pdo->prepare('DELETE FROM event_types WHERE id = ?')->execute([$tempTypeId]);
    }
};

try {
    // Non-recurring type → no notification
    $groom = null;
    foreach ($types->allActive() as $t) {
        if ((string) $t['name'] === 'Grooming') {
            $groom = $t;
            break;
        }
    }
    if ($groom !== null) {
        $pdo->prepare(
            'UPDATE event_types SET recurrence_interval = NULL, recurrence_unit = NULL WHERE id = ?'
        )->execute([(int) $groom['id']]);
        $res = $recurrence->createEventWithOptionalCompletion(
            [
                'event_type_id' => (int) $groom['id'],
                'event_date' => '2026-10-01',
                'title' => 'P5 non-recur test',
                'notes' => null,
                'cost' => null,
            ],
            [$berry],
            []
        );
        $createdEventIds[] = $res['event_id'];
        $actives = $notifications->findActiveForSourceEvent($res['event_id']);
        $pass('non-recurring → no notification', $actives === []);
    }

    // Recurring Berry only
    $res1 = $recurrence->createEventWithOptionalCompletion(
        [
            'event_type_id' => $typeId,
            'event_date' => '2026-10-01',
            'title' => 'P5 Berry deworm Oct1',
            'notes' => 'test',
            'cost' => null,
        ],
        [$berry],
        []
    );
    $createdEventIds[] = $e1 = $res1['event_id'];
    $n1 = $notifications->findActiveForSourceEvent($e1);
    $pass('recurring creates active', count($n1) === 1);
    $pass('due = event + 30 days', ($n1[0]['due_date'] ?? '') === '2026-10-31');
    $pass('reminder = due - 4', ($n1[0]['reminder_start_date'] ?? '') === '2026-10-27');
    $pass('snapshot interval', (int) ($n1[0]['recurrence_interval'] ?? 0) === 30);
    $pass('snapshot unit', ($n1[0]['recurrence_unit'] ?? '') === 'days');

    // Newer event same group replaces schedule
    $res2 = $recurrence->createEventWithOptionalCompletion(
        [
            'event_type_id' => $typeId,
            'event_date' => '2026-10-20',
            'title' => 'P5 Berry deworm Oct20',
            'notes' => null,
            'cost' => null,
        ],
        [$berry],
        []
    );
    $createdEventIds[] = $e2 = $res2['event_id'];
    $nOld = $notifications->findActiveForSourceEvent($e1);
    $nNew = $notifications->findActiveForSourceEvent($e2);
    $pass('older event keeps its own reminder', count($nOld) === 1 && ($nOld[0]['due_date'] ?? '') === '2026-10-31');
    $pass('newer event has its own reminder', count($nNew) === 1 && ($nNew[0]['due_date'] ?? '') === '2026-11-19');

    // Backdated older event does not override
    $res3 = $recurrence->createEventWithOptionalCompletion(
        [
            'event_type_id' => $typeId,
            'event_date' => '2026-09-01',
            'title' => 'P5 Berry backdated',
            'notes' => null,
            'cost' => null,
        ],
        [$berry],
        []
    );
    $createdEventIds[] = $e3 = $res3['event_id'];
    $still = $notifications->findActiveForSourceEvent($e2);
    $backdated = $notifications->findActiveForSourceEvent($e3);
    $pass('backdated event does not replace the newer one', count($still) === 1 && (int) $still[0]['source_event_id'] === $e2);
    $pass('backdated event has its own reminder', count($backdated) === 1);

    // Exact member-set independence: Berry+Mochi separate from Berry
    $res4 = $recurrence->createEventWithOptionalCompletion(
        [
            'event_type_id' => $typeId,
            'event_date' => '2026-10-05',
            'title' => 'P5 Berry+Mochi',
            'notes' => null,
            'cost' => null,
        ],
        [$berry, $mochi],
        []
    );
    $createdEventIds[] = $e4 = $res4['event_id'];
    $berryActive = $notifications->findActiveForSourceEvent($e2);
    $pairActive = $notifications->findActiveForSourceEvent($e4);
    $pass('Berry-only schedule intact', count($berryActive) === 1);
    $pass('Berry+Mochi has own schedule', count($pairActive) === 1);
    $pass('pair due from Oct5', ($pairActive[0]['due_date'] ?? '') === '2026-11-04');

    // Disable respects no reactivation on sync
    $nid = (int) $pairActive[0]['id'];
    $pass('disable works', $notifications->disable($nid));
    $pairEvent = $events->find($e4) ?? [];
    $recurrence->syncAfterEventUpdated($e4, $pairEvent);
    $pass('disabled not reactivated by an unrelated save', $notifications->findActiveForSourceEvent($e4) === []);

    // Completion flow
    $activeBerry = $notifications->findActiveForSourceEvent($e2);
    $pass('berry still active for completion', count($activeBerry) === 1);
    $completeId = (int) $activeBerry[0]['id'];
    $res5 = $recurrence->createEventWithOptionalCompletion(
        [
            'event_type_id' => $typeId,
            'event_date' => $today->format('Y-m-d'),
            'title' => 'P5 completion',
            'notes' => null,
            'cost' => null,
        ],
        [$berry],
        [],
        $completeId
    );
    $createdEventIds[] = $e5 = $res5['event_id'];
    $completed = $notifications->find($completeId);
    $pass('old notification completed', ($completed['status'] ?? '') === 'completed');
    $pass('completion_event linked', (int) ($completed['completion_event_id'] ?? 0) === $e5);
    $next = $notifications->findActiveForSourceEvent($e5);
    $pass('next notification from completion event', count($next) === 1);

    // Duplicate completion rejected
    $dupFailed = false;
    try {
        $recurrence->createEventWithOptionalCompletion(
            [
                'event_type_id' => $typeId,
                'event_date' => $today->format('Y-m-d'),
                'title' => 'P5 dup',
                'notes' => null,
                'cost' => null,
            ],
            [$berry],
            [],
            $completeId
        );
    } catch (RuntimeException) {
        $dupFailed = true;
    }
    $pass('duplicate completion rejected', $dupFailed);

    $tempTypeId = $types->createWithFields([
        'name' => 'P5 Recurrence ' . bin2hex(random_bytes(3)),
        'description' => null,
        'icon' => 'paw-print',
        'color' => '#E4BB93',
        'recurrence_interval' => 30,
        'recurrence_unit' => 'days',
        'reminder_lead_days' => 4,
    ], []);
    $hara = $byName['Hara'];
    $isolated = $recurrence->createEventWithOptionalCompletion(
        [
            'event_type_id' => $tempTypeId,
            'event_date' => '2026-03-01',
            'title' => 'P5 explicit none',
            'notes' => null,
            'cost' => null,
            'recurrence_interval' => null,
            'recurrence_unit' => null,
            'reminder_lead_days' => 0,
        ],
        [$hara],
        []
    );
    $createdEventIds[] = $isolatedId = $isolated['event_id'];
    $pass(
        'explicit none on recurring type → no notification',
        $notifications->findActiveForSourceEvent($isolatedId) === []
    );

    $inWindowDate = $today->modify('-3 days')->format('Y-m-d');
    $events->update($isolatedId, [
        'event_type_id' => $tempTypeId,
        'event_date' => $inWindowDate,
        'title' => 'P5 explicit none',
        'notes' => null,
        'cost' => null,
        'recurrence_interval' => null,
        'recurrence_unit' => null,
        'reminder_lead_days' => 0,
    ], [$hara], []);

    $recurrence->applyTypeRecurrenceToEvents($tempTypeId, 14, 'days', 2);
    $cascaded = $notifications->findActiveForSourceEvent($isolatedId);
    $expectedDue = $c->nextDueDate($inWindowDate, 14, 'days');
    $expectedStart = $c->reminderStartDate($expectedDue, 2);
    $pass(
        'cascade adds reminder',
        count($cascaded) === 1 && ($cascaded[0]['due_date'] ?? '') === $expectedDue
    );
    $pass('cascade reminder lead', ($cascaded[0]['reminder_start_date'] ?? '') === $expectedStart);

    $recurrence->applyTypeRecurrenceToEvents($tempTypeId, null, null, 0);
    $clearedCascade = $notifications->findBySourceEvent($isolatedId);
    $pass(
        'cascade remove clears reminder',
        $notifications->findActiveForSourceEvent($isolatedId) === []
        && ($clearedCascade[0]['status'] ?? '') === 'canceled'
    );

    $beforeIndividual = $events->find($isolatedId) ?? [];
    $events->update($isolatedId, [
        'event_type_id' => $tempTypeId,
        'event_date' => $inWindowDate,
        'title' => 'P5 individual',
        'notes' => null,
        'cost' => null,
        'recurrence_interval' => 5,
        'recurrence_unit' => 'days',
        'reminder_lead_days' => 1,
    ], [$hara], []);
    $recurrence->syncAfterEventUpdated($isolatedId, $beforeIndividual);
    $own = $notifications->findActiveForSourceEvent($isolatedId);
    $pass(
        'individual recurrence schedules',
        count($own) === 1 && ($own[0]['due_date'] ?? '') === $c->nextDueDate($inWindowDate, 5, 'days')
    );

    $beforeClear = $events->find($isolatedId) ?? [];
    $events->update($isolatedId, [
        'event_type_id' => $tempTypeId,
        'event_date' => $inWindowDate,
        'title' => 'P5 individual cleared',
        'notes' => null,
        'cost' => null,
        'recurrence_interval' => null,
        'recurrence_unit' => null,
        'reminder_lead_days' => 0,
    ], [$hara], []);
    $recurrence->syncAfterEventUpdated($isolatedId, $beforeClear);
    $clearedIndividual = $notifications->findBySourceEvent($isolatedId);
    $pass(
        'individual clear removes reminder',
        $notifications->findActiveForSourceEvent($isolatedId) === []
        && ($clearedIndividual[0]['status'] ?? '') === 'canceled'
    );
} finally {
    $cleanup();
}

echo $fail === 0 ? "All Phase 5 checks passed.\n" : "FAILED {$fail} check(s).\n";
exit($fail > 0 ? 1 : 0);
