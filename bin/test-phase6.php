<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use PawBerry\Services\CalendarService;

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

$cal = new CalendarService();

// February 2024 leap year — 5 weeks Mon-first (Thu start → 5 rows? Feb 1 2024 was Thursday)
// Mon-first: Jan 29 - Mar 3 = 5 weeks
$feb2024 = $cal->buildMonth(2024, 2);
$pass('Feb 2024 leap has 5 weeks', count($feb2024['weeks']) === 5);
$pass('Feb 2024 grid starts Mon Jan 29', $feb2024['grid_start'] === '2024-01-29');
$pass('Feb 2024 grid ends Sun Mar 3', $feb2024['grid_end'] === '2024-03-03');
$pass('Feb 2024 heading', $feb2024['heading'] === 'February 2024');

// February 2023 non-leap — Wed start → 5 weeks (Jan 30 - Mar 5)
$feb2023 = $cal->buildMonth(2023, 2);
$pass('Feb 2023 non-leap 5 weeks', count($feb2023['weeks']) === 5);
$pass('Feb 29 2023 not in month cells as in_month', (static function () use ($feb2023): bool {
    foreach ($feb2023['weeks'] as $week) {
        foreach ($week as $cell) {
            if ($cell['date'] === '2023-02-29') {
                return false;
            }
        }
    }
    return true;
})());

// October 2026 — Thu start → 5 weeks
$oct = $cal->buildMonth(2026, 10);
$pass('Oct 2026 Monday-first start', $oct['grid_start'] === '2026-09-28');
$pass('Oct 2026 first weekday is Mon', (static function () use ($oct): bool {
    $first = $oct['weeks'][0][0]['date'];
    return (new DateTimeImmutable($first))->format('N') === '1';
})());

// June 2026 — Mon start → exactly 5 weeks? June 1 2026 is Monday → 5 weeks (30 days)
$jun = $cal->buildMonth(2026, 6);
$pass('Jun 2026 Mon-start 5 weeks', count($jun['weeks']) === 5);
$pass('Jun 2026 grid_start is Jun 1', $jun['grid_start'] === '2026-06-01');

// May 2021 — Sat start needs 6 weeks
$may = $cal->buildMonth(2021, 5);
$pass('May 2021 has 6 weeks', count($may['weeks']) === 6);

// Feb 2021 — Mon start, 28 days → 4 weeks
$feb2021 = $cal->buildMonth(2021, 2);
$pass('Feb 2021 has 4 weeks', count($feb2021['weeks']) === 4);

[$y, $m] = $cal->normalizeYearMonth(0, 99);
$pass('invalid month falls back to today-ish', $m >= 1 && $m <= 12 && $y >= 1970);

[$py, $pm] = $cal->adjacentMonth(2026, 1, -1);
$pass('prev of Jan 2026 is Dec 2025', $py === 2025 && $pm === 12);
[$ny, $nm] = $cal->adjacentMonth(2026, 12, 1);
$pass('next of Dec 2026 is Jan 2027', $ny === 2027 && $nm === 1);

// Integration: load current month without error
$now = $cal->buildMonth((int) date('Y'), (int) date('n'));
$pass('current month builds', isset($now['weeks']) && $now['weeks'] !== []);
$pass('by_date is array', is_array($now['by_date']));

echo $fail === 0 ? "All Phase 6 calendar checks passed.\n" : "FAILED {$fail} check(s).\n";
exit($fail > 0 ? 1 : 0);
