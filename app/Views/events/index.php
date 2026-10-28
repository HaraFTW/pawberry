<?php

use PawBerry\Core\Url;
use PawBerry\Core\View;
use PawBerry\Support\EventPresenter;

/** @var list<array<string, mixed>> $events */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var list<int> $filterMemberIds */
/** @var int $filterTypeId */
/** @var bool $filtersActive */
/** @var list<array<string, mixed>> $allMembersForFilter */
/** @var list<array<string, mixed>> $allTypesForFilter */

$filterMemberIds = $filterMemberIds ?? [];
$filtersActive = !empty($filtersActive);

$query = static function (array $overrides = []) use ($filterMemberIds, $filterTypeId): string {
    $params = [];
    $members = array_key_exists('member', $overrides)
        ? (array) $overrides['member']
        : $filterMemberIds;
    $type = array_key_exists('type', $overrides) ? (int) $overrides['type'] : $filterTypeId;
    $pageNum = array_key_exists('page', $overrides) ? (int) $overrides['page'] : null;

    $memberParams = [];
    foreach ($members as $mid) {
        $id = (int) $mid;
        if ($id > 0) {
            $memberParams[] = $id;
        }
    }
    if (count($memberParams) === 1) {
        $params['member'] = $memberParams[0];
    } elseif (count($memberParams) > 1) {
        $params['member'] = $memberParams;
    }
    if ($type > 0) {
        $params['type'] = $type;
    }
    if ($pageNum !== null && $pageNum > 1) {
        $params['page'] = $pageNum;
    }
    return $params === [] ? '' : ('?' . http_build_query($params));
};

$selectedMap = array_fill_keys($filterMemberIds, true);
?>
<section class="page events-page" data-events-index data-filters-active="<?= $filtersActive ? '1' : '0' ?>">
    <header class="page__header events-page__header">
        <div class="events-page__title-row">
            <h1 class="page__title">Events</h1>
            <a class="btn btn--primary events-page__add" href="<?= View::e(Url::to('/events/new')) ?>">
                <i data-lucide="plus" aria-hidden="true"></i>
                <span>Add Event</span>
            </a>
        </div>
        <p class="page__lede">Care moments recorded for The Gang.</p>
    </header>

    <div class="event-filters-shell">
        <button
            type="button"
            class="btn btn--ghost btn--compact event-filters-toggle <?= $filtersActive ? 'is-expanded' : '' ?>"
            data-filters-toggle
            aria-expanded="<?= $filtersActive ? 'true' : 'false' ?>"
            aria-controls="event-filters-panel"
        >
            <i data-lucide="sliders-horizontal" aria-hidden="true"></i>
            <span>Filter</span>
            <i data-lucide="chevron-down" class="event-filters-toggle__chevron" aria-hidden="true"></i>
        </button>

        <form
            id="event-filters-panel"
            class="event-filters <?= $filtersActive ? 'is-open' : '' ?>"
            method="get"
            action="<?= View::e(Url::to('/events')) ?>"
            data-filters-panel
            <?= $filtersActive ? '' : 'hidden' ?>
        >
            <div class="field event-filters__members">
                <p class="field__label" id="filter-members-label">Members</p>
                <p class="field__hint">Select the exact group that was involved — only matching events will show.</p>
                <div
                    class="member-filter"
                    role="group"
                    aria-labelledby="filter-members-label"
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
                <label class="field__label" for="filter-type">Type</label>
                <select class="input" id="filter-type" name="type">
                    <option value="">All types</option>
                    <?php foreach ($allTypesForFilter as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= $filterTypeId === (int) $t['id'] ? 'selected' : '' ?>>
                            <?= View::e((string) $t['name']) ?><?= !empty($t['archived_at']) ? ' (archived)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="event-filters__actions">
                <button type="submit" class="btn btn--primary">Filter</button>
                <?php if ($filtersActive): ?>
                    <a class="btn btn--ghost" href="<?= View::e(Url::to('/events')) ?>">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($events === []): ?>
        <div class="empty-state">
            <div class="empty-state__icon" aria-hidden="true">
                <i data-lucide="notebook-pen"></i>
            </div>
            <p class="empty-state__text">
                <?= $filtersActive
                    ? 'No events match these filters.'
                    : 'No events yet.' ?>
            </p>
            <p class="empty-state__note">Record a vet visit, bath, or other care moment to begin.</p>
            <p class="empty-state__action">
                <a class="btn btn--primary" href="<?= View::e(Url::to('/events/new')) ?>">Add Event</a>
            </p>
        </div>
    <?php else: ?>
        <p class="event-list__count"><?= (int) $total ?> <?= $total === 1 ? 'event' : 'events' ?></p>
        <div class="event-timeline">
            <?php foreach ($events as $event): ?>
                <?php
                $title = EventPresenter::displayTitle($event);
                $dateLabel = EventPresenter::formatDate((string) $event['event_date']);
                $costLabel = EventPresenter::formatCost($event['cost'] ?? null);
                $notesPreview = EventPresenter::notesPreview($event['notes'] ?? null);
                $href = Url::to('/events/' . $event['id']);
                ?>
                <a class="event-card" href="<?= View::e($href) ?>">
                    <div class="event-card__icon" style="--etype-color: <?= View::e((string) $event['type_color']) ?>" aria-hidden="true">
                        <i data-lucide="<?= View::e((string) $event['type_icon']) ?>"></i>
                    </div>
                    <div class="event-card__body">
                        <div class="event-card__top">
                            <h2 class="event-card__title"><?= View::e($title) ?></h2>
                            <?php if ($dateLabel): ?>
                                <time class="event-card__date" datetime="<?= View::e((string) $event['event_date']) ?>"><?= View::e($dateLabel) ?></time>
                            <?php endif; ?>
                        </div>
                        <p class="event-card__type"><?= View::e((string) $event['type_name']) ?></p>
                        <?php if (!empty($event['members'])): ?>
                            <p class="event-card__members">
                                <?php
                                $names = array_map(static fn ($m) => (string) $m['name'], $event['members']);
                                echo View::e(implode(', ', $names));
                                ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($notesPreview): ?>
                            <p class="event-card__notes"><?= View::e($notesPreview) ?></p>
                        <?php endif; ?>
                        <?php if ($costLabel !== null): ?>
                            <p class="event-card__cost"><?= View::e($costLabel) ?></p>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="pagination" aria-label="Event pages">
                <?php if ($page > 1): ?>
                    <a class="btn btn--ghost btn--compact" href="<?= View::e(Url::to('/events') . $query(['page' => $page - 1])) ?>">Previous</a>
                <?php endif; ?>
                <span class="pagination__status">Page <?= (int) $page ?> of <?= (int) $pages ?></span>
                <?php if ($page < $pages): ?>
                    <a class="btn btn--ghost btn--compact" href="<?= View::e(Url::to('/events') . $query(['page' => $page + 1])) ?>">Next</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<script type="module" src="<?= View::e(Url::asset('js/events-index.js')) ?>"></script>
