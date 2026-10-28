<?php

use PawBerry\Core\Url;
use PawBerry\Core\View;

/** @var array<string, mixed> $calendar */
/** @var string $selectedDate */
/** @var array{events: list<array<string, mixed>>, notifications: list<array<string, mixed>>} $selectedDayData */
/** @var list<int> $filterMemberIds */
/** @var int $filterTypeId */
/** @var bool $showEvents */
/** @var bool $showDues */
/** @var bool $filtersActive */
/** @var list<array<string, mixed>> $allMembersForFilter */
/** @var list<array<string, mixed>> $allTypesForFilter */
/** @var int $prevYear */
/** @var int $prevMonth */
/** @var int $nextYear */
/** @var int $nextMonth */
/** @var array<string, mixed> $filterQuery */
/** @var list<string> $weekdayLabels */
/** @var bool $canAddForSelected */

$filterMemberIds = $filterMemberIds ?? [];
$filtersActive = !empty($filtersActive);
$selectedMap = array_fill_keys($filterMemberIds, true);

$buildUrl = static function (array $overrides = []) use ($filterQuery, $calendar, $selectedDate): string {
    $params = array_merge($filterQuery, [
        'year' => $calendar['year'],
        'month' => $calendar['month'],
        'day' => $selectedDate,
    ], $overrides);
    // Drop empty / default noise
    if (isset($params['events']) && (string) $params['events'] === '1') {
        unset($params['events']);
    }
    if (isset($params['dues']) && (string) $params['dues'] === '1') {
        unset($params['dues']);
    }
    return Url::to('/calendar') . '?' . http_build_query($params);
};

$monthNavUrl = static function (int $y, int $m) use ($buildUrl, $calendar, $selectedDate): string {
    $day = $selectedDate;
    // Keep day-of-month when possible while changing months.
    $dom = (int) substr($selectedDate, 8, 2);
    $last = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m)))->format('t');
    $clamped = min($dom, $last);
    $day = sprintf('%04d-%02d-%02d', $y, $m, $clamped);
    // If currently viewing today’s month and today is selected, prefer today in target only if same month jump via Today.
    return $buildUrl([
        'year' => $y,
        'month' => $m,
        'day' => $day,
    ]);
};

$todayUrl = $buildUrl([
    'year' => (int) substr((string) $calendar['today'], 0, 4),
    'month' => (int) substr((string) $calendar['today'], 5, 2),
    'day' => $calendar['today'],
]);

$addEventDate = $canAddForSelected ? $selectedDate : (string) $calendar['today'];
$addEventUrl = Url::to('/events/new?date=' . rawurlencode($addEventDate));

$selectedLabel = (new DateTimeImmutable($selectedDate))->format('l, M j, Y');
$dayPayload = [];
foreach ($calendar['by_date'] as $date => $bucket) {
    $dayPayload[$date] = $bucket;
}

$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
];

$boot = [
    'selected' => $selectedDate,
    'today' => $calendar['today'],
    'year' => (int) $calendar['year'],
    'month' => (int) $calendar['month'],
    'days' => $dayPayload,
    'urls' => [
        'notifications' => Url::to('/notifications'),
        'eventBase' => Url::to('/events'),
        'addEventBase' => Url::to('/events/new'),
        'calendarBase' => Url::to('/calendar'),
    ],
];
?>
<section
    class="page calendar-page"
    data-calendar
    data-filters-active="<?= $filtersActive ? '1' : '0' ?>"
    data-selected="<?= View::e($selectedDate) ?>"
    data-today="<?= View::e((string) $calendar['today']) ?>"
    data-year="<?= (int) $calendar['year'] ?>"
    data-month="<?= (int) $calendar['month'] ?>"
>
    <header class="page__header calendar-page__header">
        <h1 class="page__title">Calendar</h1>
    </header>

    <div class="event-filters-shell">
        <button
            type="button"
            class="btn btn--ghost btn--compact event-filters-toggle <?= $filtersActive ? 'is-expanded' : '' ?>"
            data-filters-toggle
            aria-expanded="<?= $filtersActive ? 'true' : 'false' ?>"
            aria-controls="calendar-filters-panel"
        >
            <i data-lucide="sliders-horizontal" aria-hidden="true"></i>
            <span>Filter</span>
            <i data-lucide="chevron-down" class="event-filters-toggle__chevron" aria-hidden="true"></i>
        </button>

        <form
            id="calendar-filters-panel"
            class="event-filters <?= $filtersActive ? 'is-open' : '' ?>"
            method="get"
            action="<?= View::e(Url::to('/calendar')) ?>"
            data-filters-panel
            <?= $filtersActive ? '' : 'hidden' ?>
        >
            <input type="hidden" name="year" value="<?= (int) $calendar['year'] ?>">
            <input type="hidden" name="month" value="<?= (int) $calendar['month'] ?>">
            <input type="hidden" name="day" value="<?= View::e($selectedDate) ?>">

            <div class="field event-filters__members">
                <p class="field__label" id="cal-filter-members-label">Members</p>
                <p class="field__hint">Exact group match for events and due dates.</p>
                <div
                    class="member-filter"
                    role="group"
                    aria-labelledby="cal-filter-members-label"
                    data-member-filter
                >
                    <?php foreach ($allMembersForFilter as $m): ?>
                        <?php
                        $mid = (int) $m['id'];
                        $checked = isset($selectedMap[$mid]);
                        $photoUrl = !empty($m['photo_cropped'])
                            ? Url::to('/gang/' . $mid . '/photo/cropped')
                            : null;
                        ?>
                        <label class="member-filter__option <?= $checked ? 'is-selected' : '' ?>">
                            <input
                                type="checkbox"
                                name="member[]"
                                value="<?= $mid ?>"
                                <?= $checked ? 'checked' : '' ?>
                                data-member-filter-check
                            >
                            <span class="member-filter__photo" aria-hidden="true">
                                <?php if ($photoUrl): ?>
                                    <img src="<?= View::e($photoUrl) ?>" alt="" width="64" height="64" loading="lazy">
                                <?php else: ?>
                                    <i data-lucide="paw-print"></i>
                                <?php endif; ?>
                            </span>
                            <span class="member-filter__name">
                                <?= View::e((string) $m['name']) ?>
                                <?php if (!empty($m['archived_at'])): ?>
                                    <span class="event-detail__archived">archived</span>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="field event-filters__type">
                <label class="field__label" for="cal-filter-type">Type</label>
                <select class="input" id="cal-filter-type" name="type">
                    <option value="">All types</option>
                    <?php foreach ($allTypesForFilter as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= $filterTypeId === (int) $t['id'] ? 'selected' : '' ?>>
                            <?= View::e((string) $t['name']) ?><?= !empty($t['archived_at']) ? ' (archived)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field calendar-filters__toggles">
                <p class="field__label">Show</p>
                <label class="check">
                    <input type="hidden" name="events" value="0">
                    <input type="checkbox" name="events" value="1" <?= $showEvents ? 'checked' : '' ?>>
                    <span>Recorded events</span>
                </label>
                <label class="check">
                    <input type="hidden" name="dues" value="0">
                    <input type="checkbox" name="dues" value="1" <?= $showDues ? 'checked' : '' ?>>
                    <span>Due dates</span>
                </label>
            </div>

            <div class="event-filters__actions">
                <button type="submit" class="btn btn--primary">Filter</button>
                <?php if ($filtersActive): ?>
                    <a class="btn btn--ghost" href="<?= View::e(Url::to('/calendar?year=' . (int) $calendar['year'] . '&month=' . (int) $calendar['month'] . '&day=' . rawurlencode($selectedDate))) ?>">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="cal-shell">
        <div class="cal-toolbar">
            <div class="cal-toolbar__nav" role="group" aria-label="Month navigation">
                <a
                    class="btn btn--ghost btn--compact cal-nav-btn"
                    href="<?= View::e($monthNavUrl($prevYear, $prevMonth)) ?>"
                    aria-label="Previous month"
                    data-cal-prev
                >
                    <i data-lucide="chevron-left" aria-hidden="true"></i>
                </a>

                <div class="cal-month-picker" data-cal-month-picker>
                    <button
                        type="button"
                        class="cal-toolbar__heading-btn"
                        id="cal-month-heading"
                        data-cal-month-toggle
                        aria-haspopup="dialog"
                        aria-expanded="false"
                        aria-controls="cal-month-picker-panel"
                    >
                        <span data-cal-month-label><?= View::e((string) $calendar['heading']) ?></span>
                        <i data-lucide="chevron-down" class="cal-toolbar__heading-chevron" aria-hidden="true"></i>
                    </button>

                    <div
                        id="cal-month-picker-panel"
                        class="cal-month-picker__panel"
                        role="dialog"
                        aria-label="Choose month and year"
                        hidden
                        data-cal-month-panel
                    >
                        <div class="cal-month-picker__year" role="group" aria-label="Year">
                            <button type="button" class="btn btn--ghost btn--compact cal-nav-btn" data-cal-year-prev aria-label="Previous year">
                                <i data-lucide="chevron-left" aria-hidden="true"></i>
                            </button>
                            <span class="cal-month-picker__year-label" data-cal-year-label><?= (int) $calendar['year'] ?></span>
                            <button type="button" class="btn btn--ghost btn--compact cal-nav-btn" data-cal-year-next aria-label="Next year">
                                <i data-lucide="chevron-right" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="cal-month-picker__grid" role="listbox" aria-label="Months" data-cal-month-grid>
                            <?php foreach ($monthNames as $num => $name): ?>
                                <button
                                    type="button"
                                    class="cal-month-picker__month<?= (int) $calendar['month'] === $num ? ' is-current' : '' ?>"
                                    role="option"
                                    aria-selected="<?= (int) $calendar['month'] === $num ? 'true' : 'false' ?>"
                                    data-cal-pick-month="<?= (int) $num ?>"
                                >
                                    <?= View::e(substr($name, 0, 3)) ?>
                                    <span class="visually-hidden"><?= View::e($name) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <a
                    class="btn btn--ghost btn--compact cal-nav-btn"
                    href="<?= View::e($monthNavUrl($nextYear, $nextMonth)) ?>"
                    aria-label="Next month"
                    data-cal-next
                >
                    <i data-lucide="chevron-right" aria-hidden="true"></i>
                </a>
            </div>
            <a class="btn btn--ghost btn--compact" href="<?= View::e($todayUrl) ?>" data-cal-today>Today</a>
        </div>

        <div class="cal-legend" aria-label="Legend">
            <span class="cal-legend__item">
                <span class="cal-swatch cal-swatch--filled" aria-hidden="true"></span>
                Recorded event
            </span>
            <span class="cal-legend__item">
                <span class="cal-swatch cal-swatch--outline" aria-hidden="true"></span>
                Due date
            </span>
        </div>

        <div class="cal-grid" role="grid" aria-labelledby="cal-month-heading">
            <div class="cal-grid__weekdays" role="row">
                <?php foreach ($weekdayLabels as $label): ?>
                    <div class="cal-grid__weekday" role="columnheader"><?= View::e($label) ?></div>
                <?php endforeach; ?>
            </div>

            <?php foreach ($calendar['weeks'] as $week): ?>
                <div class="cal-grid__week" role="row">
                    <?php foreach ($week as $cell): ?>
                        <?php
                        $classes = 'cal-day';
                        if (!$cell['in_month']) {
                            $classes .= ' cal-day--muted';
                        }
                        if ($cell['is_today']) {
                            $classes .= ' cal-day--today';
                        }
                        if ($cell['date'] === $selectedDate) {
                            $classes .= ' is-selected';
                        }
                        $ariaLabel = (new DateTimeImmutable($cell['date']))->format('l, F j, Y');
                        if ($cell['event_count'] > 0) {
                            $ariaLabel .= ', ' . $cell['event_count'] . ' recorded';
                        }
                        if ($cell['notification_count'] > 0) {
                            $ariaLabel .= ', ' . $cell['notification_count'] . ' due';
                        }
                        ?>
                        <button
                            type="button"
                            class="<?= $classes ?>"
                            role="gridcell"
                            data-cal-day="<?= View::e((string) $cell['date']) ?>"
                            aria-label="<?= View::e($ariaLabel) ?>"
                            aria-pressed="<?= $cell['date'] === $selectedDate ? 'true' : 'false' ?>"
                        >
                            <span class="cal-day__num"><?= (int) $cell['day'] ?></span>
                            <?php if ($cell['indicators'] !== [] || $cell['overflow'] > 0): ?>
                                <span class="cal-day__dots" aria-hidden="true">
                                    <?php foreach ($cell['indicators'] as $dot): ?>
                                        <span
                                            class="cal-dot cal-dot--<?= View::e((string) $dot['kind']) ?>"
                                            style="--dot-color: <?= View::e((string) $dot['color']) ?>"
                                            title="<?= View::e((string) $dot['label']) ?>"
                                        ></span>
                                    <?php endforeach; ?>
                                    <?php if ($cell['overflow'] > 0): ?>
                                        <span class="cal-day__more">+<?= (int) $cell['overflow'] ?></span>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <section class="cal-details" aria-live="polite" data-cal-details tabindex="-1">
        <div class="cal-details__head">
            <h2 class="cal-details__title" data-cal-details-title><?= View::e($selectedLabel) ?></h2>
            <a
                class="btn btn--primary btn--compact"
                href="<?= View::e($addEventUrl) ?>"
                data-cal-add-event
                <?= $canAddForSelected ? '' : 'data-cal-add-future' ?>
            >
                <i data-lucide="plus" aria-hidden="true"></i>
                <span>Add Event</span>
            </a>
        </div>

        <div data-cal-details-body>
            <?php
            $events = $selectedDayData['events'] ?? [];
            $notifications = $selectedDayData['notifications'] ?? [];
            $empty = $events === [] && $notifications === [];
            ?>

            <?php if ($empty): ?>
                <div class="cal-details__empty" data-cal-empty>
                    <p>Nothing recorded or due on this day.</p>
                </div>
            <?php else: ?>
                <?php if ($events !== []): ?>
                    <h3 class="cal-details__section-title">Recorded events</h3>
                    <ul class="cal-details__list">
                        <?php foreach ($events as $event): ?>
                            <li class="cal-item cal-item--event">
                                <div class="cal-item__icon" style="--etype-color: <?= View::e((string) $event['type_color']) ?>" aria-hidden="true">
                                    <i data-lucide="<?= View::e((string) $event['type_icon']) ?>"></i>
                                </div>
                                <div class="cal-item__body">
                                    <a class="cal-item__title" href="<?= View::e(Url::to('/events/' . (int) $event['id'])) ?>">
                                        <?= View::e((string) $event['title']) ?>
                                    </a>
                                    <p class="cal-item__meta">
                                        <?= View::e((string) $event['type_name']) ?>
                                        <span aria-hidden="true"> · </span>
                                        <?= View::e((string) $event['member_names']) ?>
                                    </p>
                                </div>
                                <span class="cal-item__badge">Recorded</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($notifications !== []): ?>
                    <h3 class="cal-details__section-title">Due dates</h3>
                    <ul class="cal-details__list">
                        <?php foreach ($notifications as $n): ?>
                            <li class="cal-item cal-item--due cal-item--<?= View::e((string) $n['status']) ?>">
                                <div class="cal-item__icon cal-item__icon--outline" style="--etype-color: <?= View::e((string) $n['type_color']) ?>" aria-hidden="true">
                                    <i data-lucide="<?= View::e((string) $n['type_icon']) ?>"></i>
                                </div>
                                <div class="cal-item__body">
                                    <a class="cal-item__title" href="<?= View::e(Url::to('/notifications')) ?>">
                                        <?= View::e((string) $n['activity']) ?>
                                    </a>
                                    <p class="cal-item__meta">
                                        <?= View::e((string) $n['type_name']) ?>
                                        <span aria-hidden="true"> · </span>
                                        <?= View::e((string) $n['member_names']) ?>
                                    </p>
                                </div>
                                <span class="cal-item__badge"><?= View::e((string) $n['status_label']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</section>

<script type="application/json" id="calendar-boot"><?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script type="module" src="<?= View::e(Url::asset('js/calendar.js')) ?>"></script>
